<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Steevo\Component\GeoMembers\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * Saisie, import et export des effectifs d'un jeu de données.
 */
class CountsController extends BaseController
{
    protected function redirectToGrid(int $datasetId): void
    {
        $this->setRedirect(Route::_('index.php?option=com_geomembers&view=counts&dataset_id=' . $datasetId, false));
    }

    protected function checkAccess(): void
    {
        if (!$this->app->getIdentity()->authorise('core.edit', 'com_geomembers')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }
    }

    public function save(): void
    {
        $this->checkToken();
        $this->checkAccess();

        $datasetId = $this->input->getInt('dataset_id');
        $values    = $this->input->post->get('counts', [], 'array');

        /** @var \Steevo\Component\GeoMembers\Administrator\Model\CountsModel $model */
        $model = $this->getModel('Counts', 'Administrator');
        $model->saveCounts($datasetId, $values);

        $this->app->enqueueMessage(Text::_('COM_GEOMEMBERS_COUNTS_SAVED'));

        if ($this->getTask() === 'close') {
            $this->setRedirect(Route::_('index.php?option=com_geomembers&view=datasets', false));

            return;
        }

        $this->redirectToGrid($datasetId);
    }

    public function close(): void
    {
        $this->save();
    }

    public function cancel(): void
    {
        $this->setRedirect(Route::_('index.php?option=com_geomembers&view=datasets', false));
    }

    public function import(): void
    {
        $this->checkToken();
        $this->checkAccess();

        $datasetId = $this->input->getInt('dataset_id');
        $file      = $this->input->files->get('csvfile', [], 'raw');

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $this->app->enqueueMessage(Text::_('COM_GEOMEMBERS_IMPORT_NO_FILE'), 'warning');
            $this->redirectToGrid($datasetId);

            return;
        }

        $model = $this->getModel('Counts', 'Administrator');
        [$count, $unknown] = $model->importCsv($datasetId, (string) file_get_contents($file['tmp_name']));

        $this->app->enqueueMessage(Text::sprintf('COM_GEOMEMBERS_IMPORT_DONE', $count));

        if ($unknown) {
            $this->app->enqueueMessage(Text::sprintf('COM_GEOMEMBERS_IMPORT_UNKNOWN', implode(', ', $unknown)), 'warning');
        }

        $this->redirectToGrid($datasetId);
    }

    public function export(): void
    {
        $this->checkAccess();

        $datasetId = $this->input->getInt('dataset_id');
        $model     = $this->getModel('Counts', 'Administrator');
        $dataset   = $model->getDataset($datasetId);

        if (!$dataset) {
            throw new \RuntimeException(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['layer', 'code', 'name', 'value'], ';', '"', '\\');

        foreach ($model->exportRows($datasetId) as $row) {
            fputcsv($out, $row, ';', '"', '\\');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $this->app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $this->app->setHeader('Content-Disposition', 'attachment; filename="geomembers-' . ($dataset->alias ?: $datasetId) . '.csv"', true);
        $this->app->sendHeaders();
        echo $csv;
        $this->app->close();
    }
}
