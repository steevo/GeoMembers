<?php

/**
 * @package     GeoMembers
 * @subpackage  com_geomembers
 * @copyright   Copyright (C) 2026 Philippe Ousset. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$this->document->getWebAssetManager()->useScript('multiselect');
?>
<form action="<?php echo Route::_('index.php?option=com_geomembers&view=datasets'); ?>" method="post" name="adminForm" id="adminForm">
	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info">
			<span class="icon-info-circle" aria-hidden="true"></span>
			<?php echo Text::_('COM_GEOMEMBERS_DATASETS_EMPTY'); ?>
		</div>
	<?php else : ?>
		<table class="table" id="datasetList">
			<caption class="visually-hidden"><?php echo Text::_('COM_GEOMEMBERS_SUBMENU_DATASETS'); ?></caption>
			<thead>
				<tr>
					<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
					<th scope="col" class="w-1 text-center"><?php echo Text::_('JSTATUS'); ?></th>
					<th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
					<th scope="col" class="w-10"><?php echo Text::_('JCATEGORY'); ?></th>
					<th scope="col" class="w-5 text-center"><?php echo Text::_('COM_GEOMEMBERS_FIELD_YEAR_LABEL'); ?></th>
					<th scope="col" class="w-10 text-end"><?php echo Text::_('COM_GEOMEMBERS_TOTAL'); ?></th>
					<th scope="col" class="w-15"><?php echo Text::_('COM_GEOMEMBERS_COUNTS'); ?></th>
					<th scope="col" class="w-10 d-none d-md-table-cell"><?php echo Text::_('JGRID_HEADING_ACCESS'); ?></th>
					<th scope="col" class="w-3 d-none d-lg-table-cell"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $i => $item) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?></td>
						<td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'datasets.', true, 'cb'); ?></td>
						<th scope="row">
							<a href="<?php echo Route::_('index.php?option=com_geomembers&task=dataset.edit&id=' . (int) $item->id); ?>">
								<?php echo $this->escape($item->title); ?>
							</a>
						</th>
						<td><?php echo $this->escape((string) $item->category_title); ?></td>
						<td class="text-center"><?php echo $item->year ? (int) $item->year : ''; ?></td>
						<td class="text-end"><?php echo (int) $item->total; ?></td>
						<td>
							<a class="btn btn-sm btn-primary" href="<?php echo Route::_('index.php?option=com_geomembers&view=counts&dataset_id=' . (int) $item->id); ?>">
								<span class="icon-pencil" aria-hidden="true"></span> <?php echo Text::_('COM_GEOMEMBERS_ENTER_COUNTS'); ?>
							</a>
						</td>
						<td class="d-none d-md-table-cell"><?php echo $this->escape($item->access_level); ?></td>
						<td class="d-none d-lg-table-cell"><?php echo (int) $item->id; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php echo $this->pagination->getListFooter(); ?>
	<?php endif; ?>
	<input type="hidden" name="task" value="">
	<input type="hidden" name="boxchecked" value="0">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
