<?php

/**
 * @package     GeoMembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Database\DatabaseInterface;

/**
 * Script du paquet GeoMembers : active le plugin de protection des catégories.
 */
class Pkg_GeomembersInstallerScript
{
    public function postflight(string $type, InstallerAdapter $parent): void
    {
        if (!\in_array($type, ['install', 'update', 'discover_install'], true)) {
            return;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('content'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('geomembers'));
        $db->setQuery($query)->execute();
    }
}
