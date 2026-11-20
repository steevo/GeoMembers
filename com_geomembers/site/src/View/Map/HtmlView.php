<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Site\View\Map;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Steevo\Component\GeoMembers\Administrator\Helper\MapDataHelper;

/**
 * Carte de répartition (élément de menu).
 */
class HtmlView extends BaseHtmlView
{
    protected $params;

    protected $config;

    protected $html = '';

    public function display($tpl = null): void
    {
        $app          = Factory::getApplication();
        $this->params = $app->getParams();
        $app->getLanguage()->load('com_geomembers', JPATH_ADMINISTRATOR . '/components/com_geomembers');

        $this->config = MapDataHelper::build($this->params);

        if ($this->config === null) {
            throw new \Exception(Text::_('COM_GEOMEMBERS_MAP_UNAVAILABLE'), 404);
        }

        $menu = $app->getMenu()->getActive();
        $this->html = MapDataHelper::render($this->config, 'geomembers-' . ($menu ? $menu->id : 'map'), (int) $this->params->get('height', 0));

        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        parent::display($tpl);
    }
}
