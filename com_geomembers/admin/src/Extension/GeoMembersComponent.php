<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Extension\MVCComponent;

/**
 * Composant GeoMembers : cartes de répartition des adhérents.
 * Les jeux de données sont classés dans les catégories Joomla (extension com_geomembers).
 */
class GeoMembersComponent extends MVCComponent implements CategoryServiceInterface
{
    use CategoryServiceTrait;

    /**
     * Table des éléments classés en catégories (comptage dans le gestionnaire de catégories).
     */
    protected function getTableNameForSection(?string $section = null)
    {
        return 'geomembers_datasets';
    }

    protected function getStateColumnForSection(?string $section = null)
    {
        return 'published';
    }
}
