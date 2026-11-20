<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Steevo\Component\GeoMembers\Administrator\Extension\GeoMembersComponent;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $namespace = '\Steevo\Component\GeoMembers';
        $container->registerServiceProvider(new CategoryFactory($namespace));
        $container->registerServiceProvider(new MVCFactory($namespace));
        $container->registerServiceProvider(new ComponentDispatcherFactory($namespace));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new GeoMembersComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setCategoryFactory($container->get(CategoryFactoryInterface::class));

                return $component;
            }
        );
    }
};
