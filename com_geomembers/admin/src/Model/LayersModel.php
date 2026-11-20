<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

class LayersModel extends ListModel
{
    public function __construct($config = [], ?\Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'a.id', 'title', 'a.title', 'published', 'a.published', 'ordering', 'a.ordering'];
        }

        parent::__construct($config, $factory);
    }

    protected function populateState($ordering = 'a.ordering', $direction = 'ASC'): void
    {
        parent::populateState($ordering, $direction);
    }

    protected function getListQuery(): QueryInterface
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('a.*')
            ->from($db->quoteName('#__geomembers_layers', 'a'));

        $query->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__geomembers_zones', 'z') . ' WHERE z.layer_id = a.id) AS zones_count');

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where($db->quoteName('a.title') . ' LIKE :search')->bind(':search', $like);
        }

        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        $query->order($db->escape($this->getState('list.ordering', 'a.ordering')) . ' ' . $db->escape($this->getState('list.direction', 'ASC')));

        return $query;
    }
}
