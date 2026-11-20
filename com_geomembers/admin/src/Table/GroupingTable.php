<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;

class GroupingTable extends Table
{
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        parent::__construct('#__geomembers_groupings', 'id', $db, $dispatcher);
        $this->setColumnAlias('published', 'published');
    }

    public function check(): bool
    {
        if (trim((string) $this->title) === '') {
            $this->setError(\Joomla\CMS\Language\Text::_('COM_GEOMEMBERS_ERROR_TITLE_REQUIRED'));

            return false;
        }

        $this->alias = OutputFilter::stringURLSafe($this->alias ?: $this->title);

        return true;
    }
}
