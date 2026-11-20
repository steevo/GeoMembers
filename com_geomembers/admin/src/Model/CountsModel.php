<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * Saisie des effectifs d'un jeu de données, zone par zone.
 */
class CountsModel extends BaseDatabaseModel
{
    public function getDataset(int $datasetId): ?object
    {
        $db = $this->getDatabase();

        return $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__geomembers_datasets'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $datasetId, ParameterType::INTEGER)
        )->loadObject() ?: null;
    }

    /**
     * Découpages avec leurs zones et les effectifs saisis.
     *
     * Chaque zone porte `value` (saisie directe) et `children` (somme des zones rattachées,
     * par exemple les départements pour la France).
     */
    public function getLayers(int $datasetId): array
    {
        $db = $this->getDatabase();

        $layers = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'alias']))
                ->from($db->quoteName('#__geomembers_layers'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('ordering'))
        )->loadObjectList('id');

        $zones = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['z.id', 'z.layer_id', 'z.code', 'z.title', 'z.parent_id']))
                ->select('COALESCE(' . $db->quoteName('c.value') . ', 0) AS ' . $db->quoteName('value'))
                ->from($db->quoteName('#__geomembers_zones', 'z'))
                ->join(
                    'LEFT',
                    $db->quoteName('#__geomembers_counts', 'c'),
                    'c.zone_id = z.id AND c.dataset_id = ' . (int) $datasetId
                )
                ->where($db->quoteName('z.published') . ' = 1')
                ->order($db->quoteName('z.code'))
        )->loadObjectList('id');

        $children = [];

        foreach ($zones as $zone) {
            if ($zone->parent_id) {
                $children[$zone->parent_id] = ($children[$zone->parent_id] ?? 0) + (int) $zone->value;
            }
        }

        foreach ($layers as $layer) {
            $layer->zones = [];
            $layer->total = 0;
        }

        foreach ($zones as $zone) {
            if (!isset($layers[$zone->layer_id])) {
                continue;
            }

            $zone->children = $children[$zone->id] ?? 0;
            $layers[$zone->layer_id]->zones[] = $zone;
            $layers[$zone->layer_id]->total += (int) $zone->value;
        }

        return $layers;
    }

    /**
     * Enregistre les effectifs (zone_id => valeur). Les valeurs vides ou nulles sont supprimées.
     */
    public function saveCounts(int $datasetId, array $values): int
    {
        $db    = $this->getDatabase();
        $saved = 0;

        foreach ($values as $zoneId => $value) {
            $zoneId = (int) $zoneId;
            $value  = max(0, (int) $value);

            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__geomembers_counts'))
                    ->where($db->quoteName('dataset_id') . ' = ' . $datasetId)
                    ->where($db->quoteName('zone_id') . ' = ' . $zoneId)
            )->execute();

            if ($value > 0) {
                $row = (object) ['dataset_id' => $datasetId, 'zone_id' => $zoneId, 'value' => $value];
                $db->insertObject('#__geomembers_counts', $row);
                $saved++;
            }
        }

        return $saved;
    }

    /**
     * Importe un CSV « découpage;code;effectif » ou « code;effectif ».
     *
     * @return  array  [nombre importé, codes inconnus]
     */
    public function importCsv(int $datasetId, string $content): array
    {
        $db = $this->getDatabase();

        $rows = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['z.id', 'z.code']))
                ->select($db->quoteName('l.alias', 'layer'))
                ->from($db->quoteName('#__geomembers_zones', 'z'))
                ->join('INNER', $db->quoteName('#__geomembers_layers', 'l'), 'l.id = z.layer_id')
        )->loadObjectList();

        $byLayer = [];
        $byCode  = [];

        foreach ($rows as $row) {
            $byLayer[strtolower($row->layer) . '|' . strtoupper($row->code)] = (int) $row->id;
            $byCode[strtoupper($row->code)][] = (int) $row->id;
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // CSV enregistré en ANSI par Excel
        }

        $values  = [];
        $unknown = [];

        foreach (preg_split('/\R/u', $content) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $sep   = substr_count($line, ';') >= substr_count($line, ',') ? ';' : ',';
            $cells = array_map('trim', str_getcsv($line, $sep, '"', '\\'));

            if (\count($cells) >= 4) {
                // Format de l'export : découpage;code;nom;effectif
                [$layer, $code, , $value] = $cells;
            } elseif (\count($cells) === 3) {
                [$layer, $code, $value] = $cells;
            } elseif (\count($cells) === 2) {
                [$code, $value] = $cells;
                $layer = '';
            } else {
                continue;
            }

            if (!is_numeric($value)) {
                continue; // ligne d'en-tête
            }

            $code   = strtoupper($code);
            $zoneId = 0;

            if ($layer !== '' && isset($byLayer[strtolower($layer) . '|' . $code])) {
                $zoneId = $byLayer[strtolower($layer) . '|' . $code];
            } elseif (isset($byCode[$code]) && \count($byCode[$code]) === 1) {
                $zoneId = $byCode[$code][0];
            } elseif (preg_match('/^\d$/', $code) && isset($byCode['0' . $code]) && \count($byCode['0' . $code]) === 1) {
                $zoneId = $byCode['0' . $code][0]; // départements saisis sans zéro initial
            }

            if (!$zoneId) {
                $unknown[] = ($layer !== '' ? $layer . '/' : '') . $code;
                continue;
            }

            $values[$zoneId] = (int) $value;
        }

        $this->saveCounts($datasetId, $values);

        return [\count($values), $unknown];
    }

    /**
     * Lignes de l'export CSV.
     */
    public function exportRows(int $datasetId): array
    {
        $rows = [];

        foreach ($this->getLayers($datasetId) as $layer) {
            foreach ($layer->zones as $zone) {
                $rows[] = [$layer->alias, $zone->code, $zone->title, (int) $zone->value];
            }
        }

        return $rows;
    }
}
