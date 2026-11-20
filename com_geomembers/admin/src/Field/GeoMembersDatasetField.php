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

class GeoMembersDatasetField extends ListField
{
    protected $type = 'GeoMembersDataset';

    protected function getOptions(): array
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id', 'value'))
            ->select("CONCAT(" . $db->quoteName('title') . ", IF(" . $db->quoteName('published') . " = 1, '', ' (–)')) AS text")
            ->from($db->quoteName('#__geomembers_datasets'))
            ->order($db->quoteName('year') . ' DESC, ' . $db->quoteName('title'));

        $options = parent::getOptions();

        foreach ($db->setQuery($query)->loadObjectList() as $row) {
            $options[] = HTMLHelper::_('select.option', $row->value, $row->text);
        }

        return $options;
    }
}
