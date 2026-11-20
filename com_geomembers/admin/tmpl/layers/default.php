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
<form action="<?php echo Route::_('index.php?option=com_geomembers&view=layers'); ?>" method="post" name="adminForm" id="adminForm">
	<p class="text-body-secondary"><?php echo Text::_('COM_GEOMEMBERS_LAYERS_INTRO'); ?></p>
	<?php if (empty($this->items)) : ?>
		<div class="alert alert-info"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></div>
	<?php else : ?>
		<table class="table" id="layerList">
			<caption class="visually-hidden"><?php echo Text::_('COM_GEOMEMBERS_SUBMENU_LAYERS'); ?></caption>
			<thead>
				<tr>
					<td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
					<th scope="col" class="w-1 text-center"><?php echo Text::_('JSTATUS'); ?></th>
					<th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
					<th scope="col" class="w-20"><?php echo Text::_('COM_GEOMEMBERS_FIELD_GEOMETRY_LABEL'); ?></th>
					<th scope="col" class="w-10"><?php echo Text::_('COM_GEOMEMBERS_FIELD_PROJECTION_LABEL'); ?></th>
					<th scope="col" class="w-10 text-end"><?php echo Text::_('COM_GEOMEMBERS_ZONES'); ?></th>
					<th scope="col" class="w-3 d-none d-lg-table-cell"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $i => $item) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?></td>
						<td class="text-center"><?php echo HTMLHelper::_('jgrid.published', $item->published, $i, 'layers.', true, 'cb'); ?></td>
						<th scope="row">
							<a href="<?php echo Route::_('index.php?option=com_geomembers&task=layer.edit&id=' . (int) $item->id); ?>">
								<?php echo $this->escape($item->title); ?>
							</a>
							<div class="small text-body-secondary"><?php echo $this->escape($item->attribution); ?></div>
						</th>
						<td><code><?php echo $this->escape($item->geometry); ?></code></td>
						<td><?php echo $this->escape($item->projection); ?></td>
						<td class="text-end"><?php echo (int) $item->zones_count; ?></td>
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
