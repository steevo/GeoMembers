<?php

/**
 * @package     GeoMembers
 * @subpackage  mod_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Module\GeoMembers\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Steevo\Component\GeoMembers\Administrator\Helper\MapDataHelper;

/**
 * Affiche une carte GeoMembers dans une position de module.
 */
class Dispatcher extends AbstractModuleDispatcher
{
    protected function getLayoutData(): array
    {
        $data = parent::getLayoutData();

        $data['html'] = '';

        if (!ComponentHelper::isEnabled('com_geomembers') || !class_exists(MapDataHelper::class)) {
            return $data;
        }

        $this->getApplication()->getLanguage()->load('com_geomembers', JPATH_ADMINISTRATOR . '/components/com_geomembers');

        $config = MapDataHelper::build($data['params']);

        if ($config !== null) {
            $data['html'] = MapDataHelper::render($config, 'geomembers-mod-' . (int) $data['module']->id, (int) $data['params']->get('height', 0));
        }

        return $data;
    }
}
