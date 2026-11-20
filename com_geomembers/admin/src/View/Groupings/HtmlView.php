<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\View\Groupings;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    protected $items = [];

    protected $pagination;

    protected $state;

    public function display($tpl = null): void
    {
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $user = Factory::getApplication()->getIdentity();

        ToolbarHelper::title(Text::_('COM_GEOMEMBERS') . ' : ' . Text::_('COM_GEOMEMBERS_SUBMENU_GROUPINGS'), 'layer-group');

        if ($user->authorise('core.create', 'com_geomembers')) {
            ToolbarHelper::addNew('grouping.add');
        }

        if ($user->authorise('core.edit.state', 'com_geomembers')) {
            ToolbarHelper::publish('groupings.publish', 'JTOOLBAR_PUBLISH', true);
            ToolbarHelper::unpublish('groupings.unpublish', 'JTOOLBAR_UNPUBLISH', true);
        }

        if ($user->authorise('core.delete', 'com_geomembers')) {
            ToolbarHelper::deleteList('COM_GEOMEMBERS_CONFIRM_DELETE', 'groupings.delete');
        }

        if ($user->authorise('core.admin', 'com_geomembers') || $user->authorise('core.options', 'com_geomembers')) {
            ToolbarHelper::preferences('com_geomembers');
        }
    }
}
