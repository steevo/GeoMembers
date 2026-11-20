<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Categories\Categories;

/**
 * Arbre des catégories de jeux de données.
 */
class Category extends Categories
{
    public function __construct($options = [])
    {
        $options['table']      = '#__geomembers_datasets';
        $options['extension']  = 'com_geomembers';
        $options['statefield'] = 'published';

        parent::__construct($options);
    }
}
