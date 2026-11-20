CREATE TABLE IF NOT EXISTS `#__geomembers_layers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(190) NOT NULL DEFAULT '',
  `geometry` varchar(255) NOT NULL DEFAULT '',
  `object` varchar(100) NOT NULL DEFAULT 'zones',
  `code_property` varchar(100) NOT NULL DEFAULT 'code',
  `name_property` varchar(100) NOT NULL DEFAULT 'name',
  `projection` varchar(50) NOT NULL DEFAULT 'equalEarth',
  `parent_code` varchar(20) NOT NULL DEFAULT '',
  `foreign_layer_id` int unsigned NOT NULL DEFAULT 0,
  `insets` text,
  `placements` text,
  `attribution` varchar(400) NOT NULL DEFAULT '',
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime,
  PRIMARY KEY (`id`),
  KEY `idx_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__geomembers_zones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `layer_id` int unsigned NOT NULL DEFAULT 0,
  `code` varchar(20) NOT NULL DEFAULT '',
  `title` varchar(255) NOT NULL DEFAULT '',
  `parent_id` int unsigned NOT NULL DEFAULT 0,
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_layer_code` (`layer_id`, `code`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__geomembers_groupings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `layer_id` int unsigned NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(190) NOT NULL DEFAULT '',
  `description` text,
  `published` tinyint NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime,
  PRIMARY KEY (`id`),
  KEY `idx_layer` (`layer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__geomembers_groups` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `grouping_id` int unsigned NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `code` varchar(50) NOT NULL DEFAULT '',
  `color` varchar(20) NOT NULL DEFAULT '',
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_grouping` (`grouping_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__geomembers_group_zones` (
  `grouping_id` int unsigned NOT NULL,
  `zone_id` int unsigned NOT NULL,
  `group_id` int unsigned NOT NULL,
  PRIMARY KEY (`grouping_id`, `zone_id`),
  KEY `idx_group` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__geomembers_datasets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `alias` varchar(190) NOT NULL DEFAULT '',
  `catid` int unsigned NOT NULL DEFAULT 0,
  `year` smallint unsigned NOT NULL DEFAULT 0,
  `description` text,
  `published` tinyint NOT NULL DEFAULT 1,
  `access` int unsigned NOT NULL DEFAULT 1,
  `ordering` int NOT NULL DEFAULT 0,
  `created` datetime NOT NULL,
  `created_by` int unsigned NOT NULL DEFAULT 0,
  `modified` datetime NOT NULL,
  `modified_by` int unsigned NOT NULL DEFAULT 0,
  `checked_out` int unsigned,
  `checked_out_time` datetime,
  PRIMARY KEY (`id`),
  KEY `idx_access` (`access`),
  KEY `idx_catid` (`catid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__geomembers_counts` (
  `dataset_id` int unsigned NOT NULL,
  `zone_id` int unsigned NOT NULL,
  `value` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`dataset_id`, `zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
