<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Choix d'un jeu de données, regroupés par catégorie.
 */
class GeoMembersDatasetField extends GroupedlistField
{
    protected $type = 'GeoMembersDataset';

    protected function getGroups(): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['d.id', 'd.title', 'd.published']))
            ->select($db->quoteName('c.title', 'category'))
            ->from($db->quoteName('#__geomembers_datasets', 'd'))
            ->join('LEFT', $db->quoteName('#__categories', 'c'), 'c.id = d.catid')
            ->order($db->quoteName('c.lft') . ', ' . $db->quoteName('d.year') . ' DESC, ' . $db->quoteName('d.title'));

        $groups = parent::getGroups();

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $label            = $row->category ?: Text::_('JNONE');
            $groups[$label][] = HTMLHelper::_('select.option', $row->id, $row->title . ((int) $row->published === 1 ? '' : ' (–)'));
        }

        return $groups;
    }
}
