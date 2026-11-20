<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;

/**
 * Jeu de données : un ensemble de comptages (adhérents 2026, présents à l'AG…).
 */
class DatasetModel extends AdminModel
{
    public $typeAlias = 'com_geomembers.dataset';

    public function getForm($data = [], $loadData = true): Form|false
    {
        $form = $this->loadForm('com_geomembers.dataset', 'dataset', ['control' => 'jform', 'load_data' => $loadData]);

        return $form ?: false;
    }

    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_geomembers.edit.dataset.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        $this->preprocessData('com_geomembers.dataset', $data);

        return $data;
    }

    public function delete(&$pks): bool
    {
        $pks = (array) $pks;

        if (!parent::delete($pks)) {
            return false;
        }

        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__geomembers_counts'))
                ->whereIn($db->quoteName('dataset_id'), array_map('intval', $pks))
        )->execute();

        return true;
    }
}
