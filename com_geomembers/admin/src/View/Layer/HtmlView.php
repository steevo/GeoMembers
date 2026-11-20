<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\View\Layer;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;

    protected $item;

    public function display($tpl = null): void
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');
        $this->prepare();

        Factory::getApplication()->getInput()->set('hidemainmenu', true);
        $isNew = empty($this->item->id);

        ToolbarHelper::title(
            Text::_('COM_GEOMEMBERS') . ' : ' . Text::_('COM_GEOMEMBERS_LAYER_' . ($isNew ? 'NEW' : 'EDIT')),
            'map'
        );
        ToolbarHelper::apply('layer.apply');
        ToolbarHelper::save('layer.save');
        ToolbarHelper::save2new('layer.save2new');
        ToolbarHelper::cancel('layer.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }

    protected function prepare(): void
    {
    }
}
