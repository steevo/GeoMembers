<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Category;
use Joomla\Database\DatabaseInterface;

/**
 * Catégories Joomla des jeux de données (extension com_geomembers).
 */
abstract class CategoryHelper
{
    public const EXTENSION = 'com_geomembers';

    /**
     * Renvoie l'identifiant de la catégorie de ce titre, en la créant si besoin.
     */
    public static function findOrCreate(DatabaseInterface $db, string $title): int
    {
        $title = trim($title);

        if ($title === '') {
            return 0;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('extension') . ' = :ext')
            ->where($db->quoteName('title') . ' = :title')
            ->where($db->quoteName('published') . ' >= 0')
            ->bind(':ext', $ext)
            ->bind(':title', $title);
        $ext = self::EXTENSION;
        $id  = (int) $db->setQuery($query)->loadResult();

        if ($id) {
            return $id;
        }

        $table = new Category($db);
        $table->setLocation(1, 'last-child');
        $table->bind([
            'title'     => $title,
            'alias'     => OutputFilter::stringURLSafe($title) ?: date('Y-m-d-H-i-s'),
            'extension' => self::EXTENSION,
            'published' => 1,
            'access'    => 1,
            'language'  => '*',
            'params'    => '{}',
            'metadata'  => '{}',
        ]);

        if (!$table->check() || !$table->store()) {
            return 0;
        }

        $table->rebuildPath($table->id);

        return (int) $table->id;
    }
}
