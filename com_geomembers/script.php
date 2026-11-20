<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Database\DatabaseInterface;
use Steevo\Component\GeoMembers\Administrator\Helper\GeometryHelper;

class Com_GeomembersInstallerScript
{
    protected $minimumPhp = '8.1';

    protected $minimumJoomla = '5.0';

    public const EUROPE = 'IS,NO,SE,FI,DK,GB,IE,NL,BE,LU,FR,DE,PL,CZ,SK,AT,CH,LI,IT,SI,HR,BA,RS,ME,XK,AL,MK,GR,BG,RO,HU,MD,UA,BY,LT,LV,EE,ES,PT,AD,MC,SM,VA,MT,CY';

    public function preflight(string $type, InstallerAdapter $parent): bool
    {
        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            Factory::getApplication()->enqueueMessage('GeoMembers : PHP ' . $this->minimumPhp . ' minimum.', 'error');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomla, '<')) {
            Factory::getApplication()->enqueueMessage('GeoMembers : Joomla ' . $this->minimumJoomla . ' minimum.', 'error');

            return false;
        }

        return true;
    }

    public function postflight(string $type, InstallerAdapter $parent): void
    {
        if ($type !== 'install') {
            return;
        }

        if (!class_exists(GeometryHelper::class)) {
            require_once JPATH_ADMINISTRATOR . '/components/com_geomembers/src/Helper/GeometryHelper.php';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        // Ne rien créer si des découpages existent déjà (réinstallation sans désinstallation des tables)
        $count = (int) $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__geomembers_layers'))->loadResult();

        if ($count) {
            return;
        }

        $lang = substr((string) \Joomla\CMS\Component\ComponentHelper::getParams("com_languages")->get("site", "en-GB"), 0, 2);
        $fr   = $lang === 'fr';

        $world = (object) [
            'title'            => $fr ? 'Pays du monde' : 'World countries',
            'alias'            => 'world',
            'geometry'         => 'world-countries.json',
            'object'           => 'zones',
            'code_property'    => 'code',
            'name_property'    => 'name',
            'projection'       => 'equalEarth',
            'parent_code'      => '',
            'foreign_layer_id' => 0,
            'insets'           => 'zoom|Europe|' . self::EUROPE,
            'attribution'      => 'Natural Earth (domaine public)',
            'published'        => 1,
            'ordering'         => 1,
        ];
        $db->insertObject('#__geomembers_layers', $world);
        $worldId = (int) $db->insertid();
        GeometryHelper::syncZones($worldId, $lang);

        $france = (object) [
            'title'            => $fr ? 'France – départements' : 'France – departments',
            'alias'            => 'france',
            'geometry'         => 'fr-departements.json',
            'object'           => 'zones',
            'code_property'    => 'code',
            'name_property'    => 'name',
            'projection'       => 'lambert93',
            'parent_code'      => 'FR',
            'foreign_layer_id' => $worldId,
            'insets'           => GeometryHelper::FRANCE_INSETS,
            'attribution'      => 'IGN ADMIN EXPRESS – Licence Ouverte Etalab 2.0',
            'published'        => 1,
            'ordering'         => 2,
        ];
        $db->insertObject('#__geomembers_layers', $france);
        $franceId = (int) $db->insertid();
        GeometryHelper::syncZones($franceId, $lang);

        GeometryHelper::createGroupingFromProperty($franceId, $fr ? 'Régions' : 'Regions', 'region', GeometryHelper::FRENCH_REGIONS);

        $continents = $fr ? [
            'Africa'                  => 'Afrique',
            'Antarctica'              => 'Antarctique',
            'Asia'                    => 'Asie',
            'Europe'                  => 'Europe',
            'North America'           => 'Amérique du Nord',
            'Oceania'                 => 'Océanie',
            'Seven seas (open ocean)' => 'Océans',
            'South America'           => 'Amérique du Sud',
        ] : [];
        GeometryHelper::createGroupingFromProperty($worldId, $fr ? 'Continents' : 'Continents', 'continent', $continents);
    }
}
