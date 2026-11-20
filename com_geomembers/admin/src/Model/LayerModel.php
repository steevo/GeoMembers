<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Steevo\Component\GeoMembers\Administrator\Helper\GeometryHelper;

/**
 * Découpage géographique : un fichier de géométrie (TopoJSON/GeoJSON) et ses zones.
 */
class LayerModel extends AdminModel
{
    public $typeAlias = 'com_geomembers.layer';

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_geomembers.layer', 'layer', ['control' => 'jform', 'load_data' => $loadData]);

        return $form ?: false;
    }

    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_geomembers.edit.layer.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_geomembers.layer', $data);

        return $data;
    }

    public function save($data): bool
    {
        if (!parent::save($data)) {
            return false;
        }

        // Crée les zones manquantes à partir du fichier de géométrie
        try {
            $created = GeometryHelper::syncZones((int) $this->getState($this->getName() . '.id'));

            if ($created) {
                Factory::getApplication()->enqueueMessage(Text::plural('COM_GEOMEMBERS_N_ZONES_CREATED', $created));
            }
        } catch (\RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        return true;
    }

    public function delete(&$pks): bool
    {
        $pks = (array) $pks;

        if (!parent::delete($pks)) {
            return false;
        }

        $db  = $this->getDatabase();
        $ids = array_map('intval', $pks);

        $zoneIds = array_map('intval', $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__geomembers_zones'))
                ->whereIn($db->quoteName('layer_id'), $ids)
        )->loadColumn());

        if ($zoneIds) {
            foreach (['#__geomembers_counts', '#__geomembers_group_zones'] as $table) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName($table))
                        ->whereIn($db->quoteName('zone_id'), $zoneIds)
                )->execute();
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__geomembers_zones'))
                    ->whereIn($db->quoteName('layer_id'), $ids)
            )->execute();
        }

        return true;
    }
}
