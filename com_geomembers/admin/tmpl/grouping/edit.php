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

$this->document->getWebAssetManager()->useScript('keepalive')->useScript('form.validate');

$layerId = (int) $this->item->layer_id;

// Le découpage principal du regroupement en premier
$assignments = $this->assignments;

if (isset($assignments[$layerId])) {
    $assignments = [$layerId => $assignments[$layerId]] + $assignments;
}
?>
<form action="<?php echo Route::_('index.php?option=com_geomembers&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="item-form" class="form-validate">
	<div class="row">
		<div class="col-lg-9"><?php echo $this->form->renderFieldset('main'); ?></div>
		<div class="col-lg-3"><?php echo $this->form->renderFieldset('side'); ?></div>
	</div>

	<h2 class="h4 mt-4"><?php echo Text::_('COM_GEOMEMBERS_ASSIGNMENTS'); ?></h2>
	<?php if (empty($this->item->id)) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_GEOMEMBERS_ASSIGNMENTS_SAVE_FIRST'); ?></div>
	<?php elseif (empty($this->groups)) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_GEOMEMBERS_ASSIGNMENTS_NO_GROUPS'); ?></div>
	<?php else : ?>
		<p class="text-body-secondary"><?php echo Text::_('COM_GEOMEMBERS_ASSIGNMENTS_DESC'); ?></p>
		<input type="search" class="form-control mb-3 geomembers-filter" style="max-width:20rem"
			placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>" aria-label="<?php echo Text::_('JSEARCH_FILTER'); ?>">
		<?php foreach ($assignments as $lid => $zones) :
			$assigned = count(array_filter($zones, static fn ($z) => (int) $z->group_id)); ?>
			<details class="card mb-3" <?php echo ($lid === $layerId || $assigned) ? 'open' : ''; ?>>
				<summary class="card-header">
					<?php echo $this->escape($zones[0]->layer_title); ?>
					<span class="badge bg-secondary"><?php echo $assigned; ?> / <?php echo count($zones); ?></span>
				</summary>
				<div class="card-body">
					<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2">
						<?php foreach ($zones as $zone) : ?>
							<div class="col geomembers-zone" data-search="<?php echo $this->escape(mb_strtolower($zone->code . ' ' . $zone->title)); ?>">
								<div class="d-flex align-items-center gap-2 border-bottom pb-1">
									<label class="w-50 text-truncate small mb-0" for="assign<?php echo (int) $zone->id; ?>" title="<?php echo $this->escape($zone->code . ' – ' . $zone->title); ?>">
										<span class="text-body-secondary font-monospace me-1"><?php echo $this->escape($zone->code); ?></span><?php echo $this->escape($zone->title); ?>
									</label>
									<select class="form-select form-select-sm w-50" name="assign[<?php echo (int) $zone->id; ?>]" id="assign<?php echo (int) $zone->id; ?>">
										<option value="0">–</option>
										<?php foreach ($this->groups as $group) : ?>
											<option value="<?php echo (int) $group->id; ?>" <?php echo (int) $zone->group_id === (int) $group->id ? 'selected' : ''; ?>>
												<?php echo $this->escape($group->title); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</details>
		<?php endforeach; ?>
		<script>
			document.querySelector('.geomembers-filter').addEventListener('input', (e) => {
				const q = e.target.value.trim().toLowerCase();
				document.querySelectorAll('.geomembers-zone').forEach((el) => {
					el.hidden = q !== '' && !el.dataset.search.includes(q);
				});
				if (q !== '') {
					document.querySelectorAll('#item-form details').forEach((d) => { d.open = true; });
				}
			});
		</script>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
