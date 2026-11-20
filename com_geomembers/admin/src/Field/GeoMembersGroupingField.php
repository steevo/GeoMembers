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
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

class GeoMembersGroupingField extends ListField
{
    protected $type = 'GeoMembersGrouping';

    protected function getOptions(): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('g.id', 'value'))
            ->select("CONCAT(" . $db->quoteName('g.title') . ", ' – ', " . $db->quoteName('l.title') . ") AS text")
            ->from($db->quoteName('#__geomembers_groupings', 'g'))
            ->join('LEFT', $db->quoteName('#__geomembers_layers', 'l'), 'l.id = g.layer_id')
            ->where($db->quoteName('g.published') . ' = 1')
            ->order($db->quoteName('l.ordering') . ', ' . $db->quoteName('g.ordering') . ', ' . $db->quoteName('g.title'));

        $options = parent::getOptions();

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $options[] = HTMLHelper::_('select.option', $row->value, $row->text);
        }

        return $options;
    }
}
