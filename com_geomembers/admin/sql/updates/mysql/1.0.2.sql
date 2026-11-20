-- Catégories Joomla : la colonne texte « category » est migrée vers catid par script.php puis supprimée
ALTER TABLE `#__geomembers_datasets` ADD COLUMN `catid` int unsigned NOT NULL DEFAULT 0 AFTER `alias`;
ALTER TABLE `#__geomembers_datasets` ADD INDEX `idx_catid` (`catid`);
