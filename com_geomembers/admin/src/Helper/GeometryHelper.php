<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Outils liés aux fonds de carte (TopoJSON) et aux zones.
 */
class GeometryHelper
{
    /**
     * Noms officiels des régions françaises (code INSEE).
     */
    public const FRENCH_REGIONS = [
        '01' => 'Guadeloupe',
        '02' => 'Martinique',
        '03' => 'Guyane',
        '04' => 'La Réunion',
        '06' => 'Mayotte',
        '11' => 'Île-de-France',
        '24' => 'Centre-Val de Loire',
        '27' => 'Bourgogne-Franche-Comté',
        '28' => 'Normandie',
        '32' => 'Hauts-de-France',
        '44' => 'Grand Est',
        '52' => 'Pays de la Loire',
        '53' => 'Bretagne',
        '75' => 'Nouvelle-Aquitaine',
        '76' => 'Occitanie',
        '84' => 'Auvergne-Rhône-Alpes',
        '93' => "Provence-Alpes-Côte d'Azur",
        '94' => 'Corse',
    ];

    public const FRANCE_INSETS = "detached|Guadeloupe|971\ndetached|Martinique|972\ndetached|Guyane|973\n"
        . "detached|La Réunion|974\ndetached|Mayotte|976\nzoom|Île-de-France|75,77,78,91,92,93,94,95";

    /**
     * Chemin absolu du fichier de géométrie d'un découpage.
     */
    public static function getPath(string $geometry): string
    {
        $geometry = ltrim(str_replace('\\', '/', $geometry), '/');

        if (strpos($geometry, '/') === false) {
            $geometry = 'media/com_geomembers/geo/' . $geometry;
        }

        return JPATH_ROOT . '/' . $geometry;
    }

    /**
     * URL publique du fichier de géométrie d'un découpage.
     */
    public static function getUrl(string $geometry): string
    {
        $geometry = ltrim(str_replace('\\', '/', $geometry), '/');

        if (strpos($geometry, '/') === false) {
            $geometry = 'media/com_geomembers/geo/' . $geometry;
        }

        $path = JPATH_ROOT . '/' . $geometry;
        $version = is_file($path) ? '?v=' . filemtime($path) : '';

        return Uri::root(true) . '/' . $geometry . $version;
    }

    /**
     * Lit les propriétés des entités d'un TopoJSON (ou d'un GeoJSON).
     *
     * @return  array  Liste de tableaux de propriétés
     */
    public static function readFeatures(object $layer): array
    {
        $path = self::getPath($layer->geometry);

        if (!is_file($path)) {
            throw new \RuntimeException('Fichier de géométrie introuvable : ' . $layer->geometry);
        }

        $json = json_decode(file_get_contents($path), true);

        if (!\is_array($json)) {
            throw new \RuntimeException('Fichier de géométrie invalide : ' . $layer->geometry);
        }

        if (($json['type'] ?? '') === 'Topology') {
            $object = $json['objects'][$layer->object] ?? reset($json['objects']);
            $items  = $object['geometries'] ?? [];
        } else {
            $items = $json['features'] ?? [];
        }

        $result = [];

        foreach ($items as $item) {
            $result[] = $item['properties'] ?? [];
        }

        return $result;
    }

    /**
     * Crée les zones manquantes d'un découpage à partir de son fichier de géométrie.
     *
     * @return  int  Nombre de zones créées
     */
    public static function syncZones(int $layerId, string $lang = ''): int
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $layer = self::getLayer($layerId);

        if (!$layer) {
            return 0;
        }

        $lang = $lang ?: substr((string) \Joomla\CMS\Component\ComponentHelper::getParams("com_languages")->get("site", "en-GB"), 0, 2);

        $existing = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['code', 'id']))
                ->from($db->quoteName('#__geomembers_zones'))
                ->where($db->quoteName('layer_id') . ' = ' . (int) $layerId)
        )->loadAssocList('code', 'id');

        $parentId = 0;

        if ($layer->foreign_layer_id && $layer->parent_code !== '') {
            $parentId = (int) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__geomembers_zones'))
                    ->where($db->quoteName('layer_id') . ' = ' . (int) $layer->foreign_layer_id)
                    ->where($db->quoteName('code') . ' = :code')
                    ->bind(':code', $layer->parent_code)
            )->loadResult();
        }

        $created  = 0;
        $ordering = \count($existing);

        foreach (self::readFeatures($layer) as $props) {
            $code = trim((string) ($props[$layer->code_property] ?? ''));

            if ($code === '' || isset($existing[$code])) {
                continue;
            }

            $title = $props[$layer->name_property . '_' . $lang] ?? $props[$layer->name_property] ?? $code;

            $zone = (object) [
                'layer_id'  => $layerId,
                'code'      => $code,
                'title'     => (string) $title,
                'parent_id' => $parentId,
                'published' => 1,
                'ordering'  => ++$ordering,
            ];
            $db->insertObject('#__geomembers_zones', $zone);
            $existing[$code] = $db->insertid();
            $created++;
        }

        return $created;
    }

    /**
     * Crée un regroupement à partir d'une propriété des entités (ex. code de région).
     *
     * @param   array  $names  Libellés des groupes indexés par valeur de la propriété
     *
     * @return  int  Identifiant du regroupement créé
     */
    public static function createGroupingFromProperty(int $layerId, string $title, string $property, array $names = []): int
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $layer = self::getLayer($layerId);

        $grouping = (object) [
            'layer_id'    => $layerId,
            'title'       => $title,
            'alias'       => \Joomla\CMS\Filter\OutputFilter::stringURLSafe($title),
            'description' => '',
            'published'   => 1,
            'ordering'    => 0,
        ];
        $db->insertObject('#__geomembers_groupings', $grouping);
        $groupingId = (int) $db->insertid();

        $zones = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['code', 'id']))
                ->from($db->quoteName('#__geomembers_zones'))
                ->where($db->quoteName('layer_id') . ' = ' . (int) $layerId)
        )->loadAssocList('code', 'id');

        $groups = [];
        $linked = [];

        foreach (self::readFeatures($layer) as $props) {
            $code  = (string) ($props[$layer->code_property] ?? '');
            $value = (string) ($props[$property] ?? '');

            if ($value === '' || !isset($zones[$code]) || isset($linked[$code])) {
                continue;
            }

            $linked[$code] = true;

            if (!isset($groups[$value])) {
                $group = (object) [
                    'grouping_id' => $groupingId,
                    'title'       => $names[$value] ?? $value,
                    'code'        => $value,
                    'color'       => '',
                    'ordering'    => \count($groups) + 1,
                ];
                $db->insertObject('#__geomembers_groups', $group);
                $groups[$value] = (int) $db->insertid();
            }

            $link = (object) ['grouping_id' => $groupingId, 'zone_id' => (int) $zones[$code], 'group_id' => $groups[$value]];
            $db->insertObject('#__geomembers_group_zones', $link);
        }

        // Ordre alphabétique des groupes
        $titles = [];

        foreach ($groups as $value => $id) {
            $titles[$id] = $names[$value] ?? $value;
        }

        asort($titles, SORT_LOCALE_STRING);
        $i = 0;

        foreach (array_keys($titles) as $id) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__geomembers_groups'))
                    ->set($db->quoteName('ordering') . ' = ' . ++$i)
                    ->where($db->quoteName('id') . ' = ' . (int) $id)
            )->execute();
        }

        return $groupingId;
    }

    public static function getLayer(int $layerId): ?object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        return $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__geomembers_layers'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $layerId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }
}
