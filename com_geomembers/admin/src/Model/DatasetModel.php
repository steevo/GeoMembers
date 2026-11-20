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
use Steevo\Component\GeoMembers\Administrator\Helper\CategoryHelper;

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

    public function save($data): bool
    {
        // Catégorie saisie dans le champ (valeur « #new#Titre », ou titre seul) : créée si elle n'existe pas
        $catid = trim((string) ($data['catid'] ?? ''));

        if ($catid !== '' && !ctype_digit($catid)) {
            if (!Factory::getApplication()->getIdentity()->authorise('core.create', 'com_geomembers')) {
                $this->setError(\Joomla\CMS\Language\Text::_('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED'));

                return false;
            }

            $title         = str_starts_with($catid, '#new#') ? substr($catid, 5) : $catid;
            $data['catid'] = CategoryHelper::findOrCreate($this->getDatabase(), $title);

            if (!$data['catid']) {
                $this->setError(\Joomla\CMS\Language\Text::_('JLIB_APPLICATION_ERROR_SAVE_FAILED'));

                return false;
            }
        }

        return parent::save($data);
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
