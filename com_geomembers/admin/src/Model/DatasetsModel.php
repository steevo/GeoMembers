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

class DatasetsModel extends ListModel
{
    public function __construct($config = [], ?\Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = ['id', 'a.id', 'title', 'a.title', 'year', 'a.year', 'category_title', 'c.title', 'catid', 'a.catid', 'published', 'a.published', 'ordering', 'a.ordering'];
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
            ->from($db->quoteName('#__geomembers_datasets', 'a'));

        $query->select('(SELECT SUM(c.value) FROM ' . $db->quoteName('#__geomembers_counts', 'c') . ' WHERE c.dataset_id = a.id) AS total')
            ->select($db->quoteName('ag.title', 'access_level'))
            ->join('LEFT', $db->quoteName('#__viewlevels', 'ag'), 'ag.id = a.access')
            ->select($db->quoteName('c.title', 'category_title'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), 'c.id = a.catid');

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where('(' . $db->quoteName('a.title') . ' LIKE :search OR ' . $db->quoteName('c.title') . ' LIKE :search2)')
                ->bind(':search', $like)
                ->bind(':search2', $like);
        }

        $published = (string) $this->getState('filter.published');

        if (is_numeric($published)) {
            $query->where($db->quoteName('a.published') . ' = ' . (int) $published);
        }

        $query->order($db->escape($this->getState('list.ordering', 'a.ordering')) . ' ' . $db->escape($this->getState('list.direction', 'ASC')));

        return $query;
    }
}
