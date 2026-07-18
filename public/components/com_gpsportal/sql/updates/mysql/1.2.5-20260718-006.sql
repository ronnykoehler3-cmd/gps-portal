ALTER TABLE `#__gpsportal_demo_vehicles`
  ADD COLUMN `working_weekdays` varchar(30) NOT NULL DEFAULT '0,1,2,3,4,5' AFTER `maximum_speed_kmh`,
  ADD COLUMN `workday_start` time NOT NULL DEFAULT '06:30:00' AFTER `working_weekdays`,
  ADD COLUMN `workday_end` time NOT NULL DEFAULT '18:30:00' AFTER `workday_start`,
  ADD COLUMN `minimum_stop_minutes` smallint unsigned NOT NULL DEFAULT 15 AFTER `workday_end`,
  ADD COLUMN `maximum_stop_minutes` smallint unsigned NOT NULL DEFAULT 240 AFTER `minimum_stop_minutes`,
  ADD COLUMN `long_stop_probability` decimal(4,3) NOT NULL DEFAULT 0.220 AFTER `maximum_stop_minutes`;

CREATE TABLE IF NOT EXISTS `#__gpsportal_demo_settings` (
  `id` tinyint unsigned NOT NULL DEFAULT 1,
  `working_weekdays` varchar(30) NOT NULL DEFAULT '0,1,2,3,4,5',
  `workday_start` time NOT NULL DEFAULT '06:30:00',
  `workday_end` time NOT NULL DEFAULT '18:30:00',
  `minimum_stop_minutes` smallint unsigned NOT NULL DEFAULT 15,
  `maximum_stop_minutes` smallint unsigned NOT NULL DEFAULT 240,
  `long_stop_probability` decimal(4,3) NOT NULL DEFAULT 0.220,
  `modified_by` int unsigned DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__gpsportal_demo_settings`
  (`id`, `working_weekdays`, `workday_start`, `workday_end`, `minimum_stop_minutes`, `maximum_stop_minutes`, `long_stop_probability`)
VALUES
  (1, '0,1,2,3,4,5', '06:30:00', '18:30:00', 15, 240, 0.220);
