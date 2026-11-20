<?php

/**
 * @package     GeoMembers
 * @subpackage  plg_content_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Plugin\Content\GeoMembers\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Event\Model\BeforeDeleteEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;

/**
 * Empêche la suppression d'une catégorie GeoMembers qui contient encore des jeux de données
 * (le plugin « Contenu - Joomla » ne protège que les catégories des composants du cœur).
 */
final class GeoMembers extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return ['onContentBeforeDelete' => 'onContentBeforeDelete'];
    }

    public function onContentBeforeDelete(BeforeDeleteEvent $event): void
    {
        $item = $event->getItem();

        if ($event->getContext() !== 'com_categories.category' || ($item->extension ?? '') !== 'com_geomembers') {
            return;
        }

        // Jeux de données de la catégorie et de ses sous-catégories
        $db    = $this->getDatabase();
        $lft   = (int) $item->lft;
        $rgt   = (int) $item->rgt;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__geomembers_datasets', 'd'))
            ->join('INNER', $db->quoteName('#__categories', 'c'), 'c.id = d.catid')
            ->where($db->quoteName('c.lft') . ' >= :lft')
            ->where($db->quoteName('c.rgt') . ' <= :rgt')
            ->bind(':lft', $lft)
            ->bind(':rgt', $rgt);
        $count = (int) $db->setQuery($query)->loadResult();

        if ($count > 0) {
            $this->getApplication()->enqueueMessage(
                Text::sprintf('COM_CATEGORIES_DELETE_NOT_ALLOWED', $item->title) . ' ' . Text::plural('COM_CATEGORIES_N_ITEMS_ASSIGNED', $count),
                'error'
            );
            $event->addResult(false);
        }
    }
}
