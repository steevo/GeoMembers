<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\View\Counts;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Grille de saisie des effectifs d'un jeu de données.
 */
class HtmlView extends BaseHtmlView
{
    protected $dataset;

    protected $layers = [];

    public function display($tpl = null): void
    {
        $app       = Factory::getApplication();
        $datasetId = $app->getInput()->getInt('dataset_id');

        /** @var \Steevo\Component\GeoMembers\Administrator\Model\CountsModel $model */
        $model         = $this->getModel();
        $this->dataset = $model->getDataset($datasetId);

        if (!$this->dataset) {
            $app->enqueueMessage(Text::_('COM_GEOMEMBERS_DATASET_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_geomembers&view=datasets', false));
        }

        $this->layers = $model->getLayers($datasetId);

        $app->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::_('COM_GEOMEMBERS') . ' : ' . Text::sprintf('COM_GEOMEMBERS_COUNTS_TITLE', $this->dataset->title), 'database');
        ToolbarHelper::apply('counts.save');
        ToolbarHelper::save('counts.close');
        ToolbarHelper::link(
            Route::_('index.php?option=com_geomembers&task=counts.export&dataset_id=' . $datasetId),
            'COM_GEOMEMBERS_EXPORT_CSV',
            'download'
        );
        ToolbarHelper::cancel('counts.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
