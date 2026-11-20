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

$this->document->getWebAssetManager()->useScript('keepalive');
$datasetId = (int) $this->dataset->id;
?>
<form action="<?php echo Route::_('index.php?option=com_geomembers&view=counts&dataset_id=' . $datasetId); ?>" method="post" name="adminForm" id="adminForm">
	<p class="text-body-secondary"><?php echo Text::_('COM_GEOMEMBERS_COUNTS_DESC'); ?></p>
	<div class="d-flex flex-wrap gap-3 align-items-center mb-3">
		<input type="search" class="form-control geomembers-filter" style="max-width:20rem"
			placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>" aria-label="<?php echo Text::_('JSEARCH_FILTER'); ?>">
		<label class="form-check">
			<input type="checkbox" class="form-check-input geomembers-nonzero">
			<?php echo Text::_('COM_GEOMEMBERS_ONLY_NONZERO'); ?>
		</label>
		<strong class="ms-auto"><?php echo Text::_('COM_GEOMEMBERS_TOTAL'); ?> : <span class="geomembers-grand-total">0</span></strong>
	</div>

	<?php foreach ($this->layers as $layer) : ?>
		<details class="card mb-3" open>
			<summary class="card-header">
				<?php echo $this->escape($layer->title); ?> —
				<?php echo Text::_('COM_GEOMEMBERS_TOTAL'); ?> : <strong class="geomembers-layer-total" data-layer="<?php echo (int) $layer->id; ?>"><?php echo (int) $layer->total; ?></strong>
			</summary>
			<div class="card-body">
				<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-2">
					<?php foreach ($layer->zones as $zone) : ?>
						<div class="col geomembers-zone" data-search="<?php echo $this->escape(mb_strtolower($zone->code . ' ' . $zone->title)); ?>">
							<div class="d-flex align-items-center gap-2 border-bottom pb-1">
								<label class="flex-grow-1 text-truncate small mb-0" for="count<?php echo (int) $zone->id; ?>" title="<?php echo $this->escape($zone->code . ' – ' . $zone->title); ?>">
									<span class="text-body-secondary font-monospace me-1"><?php echo $this->escape($zone->code); ?></span><?php echo $this->escape($zone->title); ?>
								</label>
								<?php if ($zone->children) : ?>
									<span class="badge bg-secondary" title="<?php echo Text::_('COM_GEOMEMBERS_CHILDREN_TOTAL'); ?>">+<?php echo (int) $zone->children; ?></span>
								<?php endif; ?>
								<input type="number" min="0" step="1" class="form-control form-control-sm text-end geomembers-count"
									style="width:5.5rem;flex:0 0 5.5rem"
									data-layer="<?php echo (int) $layer->id; ?>"
									name="counts[<?php echo (int) $zone->id; ?>]" id="count<?php echo (int) $zone->id; ?>"
									value="<?php echo (int) $zone->value ?: ''; ?>">
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</details>
	<?php endforeach; ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="dataset_id" value="<?php echo $datasetId; ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<form action="<?php echo Route::_('index.php?option=com_geomembers'); ?>" method="post" enctype="multipart/form-data" class="card card-body mt-4">
	<h2 class="h5"><?php echo Text::_('COM_GEOMEMBERS_IMPORT_CSV'); ?></h2>
	<p class="small text-body-secondary"><?php echo Text::_('COM_GEOMEMBERS_IMPORT_CSV_DESC'); ?></p>
	<div class="input-group" style="max-width:40rem">
		<input type="file" name="csvfile" accept=".csv,text/csv,text/plain" class="form-control" required>
		<button type="submit" class="btn btn-secondary"><span class="icon-upload" aria-hidden="true"></span> <?php echo Text::_('COM_GEOMEMBERS_IMPORT'); ?></button>
	</div>
	<input type="hidden" name="task" value="counts.import">
	<input type="hidden" name="dataset_id" value="<?php echo $datasetId; ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
(() => {
	const inputs = [...document.querySelectorAll('.geomembers-count')];
	const filter = document.querySelector('.geomembers-filter');
	const nonzero = document.querySelector('.geomembers-nonzero');
	const totals = () => {
		const byLayer = {};
		let all = 0;
		inputs.forEach((i) => {
			const v = parseInt(i.value, 10) || 0;
			byLayer[i.dataset.layer] = (byLayer[i.dataset.layer] || 0) + v;
			all += v;
		});
		document.querySelectorAll('.geomembers-layer-total').forEach((el) => { el.textContent = byLayer[el.dataset.layer] || 0; });
		document.querySelector('.geomembers-grand-total').textContent = all;
	};
	const apply = () => {
		const q = filter.value.trim().toLowerCase();
		document.querySelectorAll('.geomembers-zone').forEach((el) => {
			const v = parseInt(el.querySelector('input').value, 10) || 0;
			el.hidden = (q !== '' && !el.dataset.search.includes(q)) || (nonzero.checked && v === 0);
		});
	};
	inputs.forEach((i) => i.addEventListener('input', totals));
	filter.addEventListener('input', apply);
	nonzero.addEventListener('change', apply);
	totals();
})();
</script>
