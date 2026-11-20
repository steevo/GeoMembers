<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Prépare les données d'une carte (composant ou module) pour le script de rendu.
 */
class MapDataHelper
{
    /**
     * Construit la configuration JSON d'une carte.
     *
     * @param   Registry  $params  Paramètres de l'élément de menu ou du module
     *
     * @return  array|null  Configuration, ou null si la carte ne peut pas être affichée
     */
    public static function build(Registry $params): ?array
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $app     = Factory::getApplication();
        $user    = $app->getIdentity();
        $global  = ComponentHelper::getParams('com_geomembers');
        $layerId = (int) $params->get('layer_id');
        $layer   = GeometryHelper::getLayer($layerId);

        if (!$layer || !$layer->published) {
            return null;
        }

        $datasetId = (int) $params->get('dataset_id');
        $dataset   = $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__geomembers_datasets'))
                ->where($db->quoteName('id') . ' = ' . $datasetId)
                ->where($db->quoteName('published') . ' = 1')
        )->loadObject();

        if (!$dataset || !\in_array((int) $dataset->access, $user->getAuthorisedViewLevels(), true)) {
            return null;
        }

        // Effectifs saisis et zones de tous les découpages concernés
        $layerIds = array_filter([(int) $layer->id, (int) $layer->foreign_layer_id]);
        $zones    = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['z.id', 'z.layer_id', 'z.code', 'z.title', 'z.parent_id']))
                ->select('COALESCE(' . $db->quoteName('c.value') . ', 0) AS ' . $db->quoteName('value'))
                ->from($db->quoteName('#__geomembers_zones', 'z'))
                ->join('LEFT', $db->quoteName('#__geomembers_counts', 'c'), 'c.zone_id = z.id AND c.dataset_id = ' . $datasetId)
                ->where($db->quoteName('z.published') . ' = 1')
        )->loadObjectList('id');

        // Valeurs agrégées : propre + zones rattachées (ex. France = somme des départements)
        $aggregated = [];

        foreach ($zones as $zone) {
            $aggregated[$zone->id] = ($aggregated[$zone->id] ?? 0) + (int) $zone->value;
            $parent                = (int) $zone->parent_id;
            $guard                 = 0;

            while ($parent && isset($zones[$parent]) && $guard++ < 10) {
                $aggregated[$parent] = ($aggregated[$parent] ?? 0) + (int) $zone->value;
                $parent              = (int) $zones[$parent]->parent_id;
            }
        }

        $base        = [];
        $foreign     = [];
        $codeById    = [];
        $unallocated = null;
        $parentZone  = null;

        foreach ($zones as $zone) {
            $codeById[$zone->id] = [(int) $zone->layer_id, $zone->code];

            if ((int) $zone->layer_id === (int) $layer->id) {
                $base[$zone->code] = ['name' => $zone->title, 'value' => (int) ($aggregated[$zone->id] ?? 0)];
            } elseif ((int) $zone->layer_id === (int) $layer->foreign_layer_id) {
                if ($layer->parent_code !== '' && $zone->code === $layer->parent_code) {
                    $parentZone = $zone;

                    if ((int) $zone->value > 0) {
                        $unallocated = ['name' => $zone->title, 'value' => (int) $zone->value];
                    }

                    continue;
                }

                if (($aggregated[$zone->id] ?? 0) > 0) {
                    $foreign[$zone->code] = ['name' => $zone->title, 'value' => (int) $aggregated[$zone->id]];
                }
            }
        }

        // Regroupements proposés
        $groupingIds = array_map('intval', (array) $params->get('groupings', []));
        $default     = (int) $params->get('grouping_id', 0);

        if ($default && !\in_array($default, $groupingIds, true)) {
            array_unshift($groupingIds, $default);
        }

        $groupings = [];

        if ((int) $params->get('show_zones', 1) || !$groupingIds) {
            $groupings[] = ['id' => 0, 'title' => $params->get('zones_label') ?: Text::_('COM_GEOMEMBERS_MAP_ZONES'), 'groups' => null];
        }

        if ($groupingIds) {
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__geomembers_groupings'))
                    ->whereIn($db->quoteName('id'), $groupingIds)
                    ->where($db->quoteName('published') . ' = 1')
            )->loadObjectList('id');

            $groups = $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__geomembers_groups'))
                    ->whereIn($db->quoteName('grouping_id'), $groupingIds)
                    ->order($db->quoteName('ordering'))
            )->loadObjectList('id');

            $links = $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__geomembers_group_zones'))
                    ->whereIn($db->quoteName('grouping_id'), $groupingIds)
            )->loadObjectList();

            $members = [];

            foreach ($links as $link) {
                if (!isset($codeById[$link->zone_id])) {
                    continue;
                }

                [$lid, $code] = $codeById[$link->zone_id];
                $key          = $lid === (int) $layer->id ? 'zones' : 'foreign';

                if ($key === 'foreign' && $lid !== (int) $layer->foreign_layer_id) {
                    continue;
                }

                $members[$link->group_id][$key][] = $code;
            }

            foreach ($groupingIds as $gid) {
                if (!isset($rows[$gid])) {
                    continue;
                }

                $list = [];

                foreach ($groups as $group) {
                    if ((int) $group->grouping_id !== $gid) {
                        continue;
                    }

                    $list[] = [
                        'id'      => (int) $group->id,
                        'title'   => $group->title,
                        'color'   => $group->color,
                        'zones'   => $members[$group->id]['zones'] ?? [],
                        'foreign' => $members[$group->id]['foreign'] ?? [],
                    ];
                }

                $groupings[] = ['id' => $gid, 'title' => $rows[$gid]->title, 'groups' => $list];
            }
        }

        $foreignLayer = null;

        if ($layer->foreign_layer_id && (int) $params->get('show_foreign', 1)) {
            $fl = GeometryHelper::getLayer((int) $layer->foreign_layer_id);

            if ($fl) {
                $foreignLayer = [
                    'geometry'     => GeometryHelper::getUrl($fl->geometry),
                    'object'       => $fl->object,
                    'codeProperty' => $fl->code_property,
                    'nameProperty' => $fl->name_property,
                    'parentCode'   => $layer->parent_code,
                    'zones'        => $foreign,
                    'placements'   => self::parsePlacements((string) ($layer->placements ?? '')),
                ];
            }
        }

        $insets = [];

        // Les encarts sont toujours transmis : sans eux, les zones « détachées » (outre-mer) sont masquées
        {
            foreach (preg_split('/\R/u', (string) $layer->insets) as $line) {
                $parts = array_map('trim', explode('|', $line));

                if (\count($parts) === 3 && \in_array($parts[0], ['zoom', 'detached'], true)) {
                    $insets[] = ['type' => $parts[0], 'title' => $parts[1], 'codes' => array_values(array_filter(array_map('trim', explode(',', $parts[2])), 'strlen'))];
                }
            }
        }

        $total = array_sum(array_column($base, 'value')) + ($foreignLayer ? array_sum(array_column($foreign, 'value')) : 0) + ($unallocated['value'] ?? 0);

        $threshold = $params->get('threshold', '');

        return [
            'lang'    => substr($app->getLanguage()->getTag(), 0, 2),
            'flags'   => Uri::root(true) . '/media/com_geomembers/flags/',
            'dataset' => ['title' => $dataset->title, 'description' => $dataset->description, 'total' => $total],
            'layer'   => [
                'title'        => $layer->title,
                'geometry'     => GeometryHelper::getUrl($layer->geometry),
                'object'       => $layer->object,
                'codeProperty' => $layer->code_property,
                'nameProperty' => $layer->name_property,
                'projection'   => $layer->projection,
                'attribution'  => $layer->attribution,
                'insets'       => $insets,
            ],
            'zones'       => $base,
            'unallocated' => $unallocated,
            'foreign'     => $foreignLayer,
            'groupings'   => $groupings,
            'grouping'    => $default,
            'options'     => [
                'mode'       => $params->get('mode', 'choropleth'),
                'scheme'     => $params->get('scheme') ?: $global->get('scheme', 'Blues'),
                'threshold'  => (int) ($threshold === '' || $threshold === null ? $global->get('threshold', 0) : $threshold),
                'labels'     => $params->get('labels', 'value'),
                'zeroLabels' => (int) $params->get('zero_labels', 0),
                'hatchZero'  => (int) $params->get('hatch_zero', 1),
                'switcher'   => (int) $params->get('switcher', 1),
                'table'      => (int) $params->get('show_table', 1),
                'legend'     => (int) $params->get('show_legend', 1),
                'download'   => (int) $params->get('show_download', 1),
                'title'      => (int) $params->get('show_dataset_title', 1),
                'insets'     => (int) $params->get('show_insets', 1),
            ],
            'strings' => [
                'total'       => Text::_('COM_GEOMEMBERS_MAP_TOTAL'),
                'grouping'    => Text::_('COM_GEOMEMBERS_MAP_GROUPING'),
                'zone'        => Text::_('COM_GEOMEMBERS_MAP_ZONE'),
                'value'       => Text::_('COM_GEOMEMBERS_MAP_VALUE'),
                'abroad'      => Text::_('COM_GEOMEMBERS_MAP_ABROAD'),
                'unallocated' => Text::_('COM_GEOMEMBERS_MAP_UNALLOCATED'),
                'unassigned'  => Text::_('COM_GEOMEMBERS_MAP_UNASSIGNED'),
                'download'    => Text::_('COM_GEOMEMBERS_MAP_DOWNLOAD'),
                'table'       => Text::_('COM_GEOMEMBERS_MAP_TABLE'),
                'less'        => Text::_('COM_GEOMEMBERS_MAP_LESS_THAN'),
                'none'        => Text::_('COM_GEOMEMBERS_MAP_NONE'),
                'sources'     => Text::_('COM_GEOMEMBERS_MAP_SOURCES'),
            ],
        ];
    }

    /**
     * Lit le placement manuel des pays étrangers, un pays par ligne : code|position|option.
     * Position « map » (option : longitude,latitude), « left » ou « right » (option : rang dans la marge).
     *
     * @return  object  code => ['position' => ..., 'point' => [lon, lat]|null, 'order' => int|null]
     */
    public static function parsePlacements(string $text): object
    {
        $aliases = ['carte' => 'map', 'gauche' => 'left', 'droite' => 'right'];
        $out     = [];

        foreach (preg_split('/\R/u', $text) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $code  = strtoupper($parts[0] ?? '');
            $pos   = strtolower($parts[1] ?? '');
            $pos   = $aliases[$pos] ?? $pos;

            if ($code === '' || !\in_array($pos, ['map', 'left', 'right'], true)) {
                continue;
            }

            $option = $parts[2] ?? '';
            $item   = ['position' => $pos, 'point' => null, 'order' => null];

            if ($pos === 'map' && preg_match('/^(-?\d+(?:\.\d+)?)\s*[,;]\s*(-?\d+(?:\.\d+)?)$/', $option, $m)) {
                $item['point'] = [(float) $m[1], (float) $m[2]];
            } elseif ($pos !== 'map' && is_numeric($option)) {
                $item['order'] = (int) $option;
            }

            $out[$code] = $item;
        }

        return (object) $out;
    }

    /**
     * Charge les ressources et renvoie le HTML du conteneur de la carte.
     */
    public static function render(array $config, string $id, int $height = 0): string
    {
        $doc = Factory::getApplication()->getDocument();
        $wa  = $doc->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_geomembers');
        $wa->useStyle('com_geomembers.map')->useScript('com_geomembers.map');
        $doc->addScriptOptions('com_geomembers.' . $id, $config);

        $style = $height ? ' style="--geomembers-height:' . $height . 'px"' : '';

        return '<div class="geomembers" id="' . htmlspecialchars($id) . '" data-geomembers="' . htmlspecialchars($id) . '"' . $style . '>'
            . '<div class="geomembers-loading">' . Text::_('COM_GEOMEMBERS_MAP_LOADING') . '</div></div>';
    }
}
