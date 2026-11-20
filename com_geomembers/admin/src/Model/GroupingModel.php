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
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Database\ParameterType;

/**
 * Regroupement : ensemble de groupes, chacun composé de zones (départements, pays…).
 */
class GroupingModel extends AdminModel
{
    public $typeAlias = 'com_geomembers.grouping';

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_geomembers.grouping', 'grouping', ['control' => 'jform', 'load_data' => $loadData]);

        return $form ?: false;
    }

    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_geomembers.edit.grouping.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_geomembers.grouping', $data);

        return $data;
    }

    public function getItem($pk = null): object|false
    {
        $item = parent::getItem($pk);

        if ($item && $item->id) {
            $groups = [];

            foreach ($this->getGroups((int) $item->id) as $i => $group) {
                $groups['groups' . $i] = (array) $group;
            }

            $item->groups = $groups;
        }

        return $item;
    }

    /**
     * Groupes d'un regroupement.
     */
    public function getGroups(int $groupingId): array
    {
        $db = $this->getDatabase();

        return $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['id', 'title', 'code', 'color', 'ordering']))
                ->from($db->quoteName('#__geomembers_groups'))
                ->where($db->quoteName('grouping_id') . ' = :id')
                ->bind(':id', $groupingId, ParameterType::INTEGER)
                ->order($db->quoteName('ordering'))
        )->loadObjectList();
    }

    /**
     * Zones affectables (zones de tous les découpages), avec leur groupe actuel.
     *
     * @return  array  Listes d'objets indexées par identifiant de découpage
     */
    public function getAssignments(int $groupingId): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['z.id', 'z.code', 'z.title', 'z.layer_id']))
            ->select($db->quoteName('l.title', 'layer_title'))
            ->select($db->quoteName('gz.group_id'))
            ->from($db->quoteName('#__geomembers_zones', 'z'))
            ->join('INNER', $db->quoteName('#__geomembers_layers', 'l'), 'l.id = z.layer_id')
            ->join(
                'LEFT',
                $db->quoteName('#__geomembers_group_zones', 'gz'),
                'gz.zone_id = z.id AND gz.grouping_id = ' . (int) $groupingId
            )
            ->where($db->quoteName('z.published') . ' = 1')
            ->order($db->quoteName('l.ordering') . ', ' . $db->quoteName('z.code'));

        $result = [];

        foreach ($db->setQuery($query)->loadObjectList() as $zone) {
            $result[$zone->layer_id][] = $zone;
        }

        return $result;
    }

    public function save($data): bool
    {
        $groups = $data['groups'] ?? [];
        unset($data['groups']);

        if (!parent::save($data)) {
            return false;
        }

        $groupingId = (int) $this->getState($this->getName() . '.id');
        $db         = $this->getDatabase();

        // Groupes : mise à jour, création, suppression
        $keep     = [];
        $ordering = 0;

        foreach ($groups as $group) {
            $title = trim((string) ($group['title'] ?? ''));

            if ($title === '') {
                continue;
            }

            $row = (object) [
                'id'          => (int) ($group['id'] ?? 0),
                'grouping_id' => $groupingId,
                'title'       => $title,
                'code'        => trim((string) ($group['code'] ?? '')),
                'color'       => trim((string) ($group['color'] ?? '')),
                'ordering'    => ++$ordering,
            ];

            if ($row->id) {
                $db->updateObject('#__geomembers_groups', $row, 'id');
            } else {
                unset($row->id);
                $db->insertObject('#__geomembers_groups', $row);
                $row->id = (int) $db->insertid();
            }

            $keep[] = (int) $row->id;
        }

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__geomembers_groups'))
            ->where($db->quoteName('grouping_id') . ' = ' . $groupingId);

        if ($keep) {
            $query->whereNotIn($db->quoteName('id'), $keep);
        }

        $db->setQuery($query)->execute();

        // Affectations des zones (grille sous le formulaire)
        $assign = Factory::getApplication()->getInput()->post->get('assign', null, 'array');

        if (\is_array($assign)) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__geomembers_group_zones'))
                    ->where($db->quoteName('grouping_id') . ' = ' . $groupingId)
            )->execute();

            foreach ($assign as $zoneId => $groupId) {
                $groupId = (int) $groupId;

                if ($groupId && \in_array($groupId, $keep, true)) {
                    $link = (object) ['grouping_id' => $groupingId, 'zone_id' => (int) $zoneId, 'group_id' => $groupId];
                    $db->insertObject('#__geomembers_group_zones', $link);
                }
            }
        } else {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__geomembers_group_zones'))
                ->where($db->quoteName('grouping_id') . ' = ' . $groupingId);

            if ($keep) {
                $query->whereNotIn($db->quoteName('group_id'), $keep);
            }

            $db->setQuery($query)->execute();
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

        foreach (['#__geomembers_groups', '#__geomembers_group_zones'] as $table) {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->whereIn($db->quoteName('grouping_id'), $ids)
            )->execute();
        }

        return true;
    }
}
