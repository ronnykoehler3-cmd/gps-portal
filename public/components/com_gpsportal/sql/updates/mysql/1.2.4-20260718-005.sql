CREATE TABLE IF NOT EXISTS `#__gpsportal_customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `customer_number` varchar(100) DEFAULT NULL,
  `published` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customer_number` (`customer_number`),
  KEY `idx_customer_published` (`published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__gpsportal_customer_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customer_user` (`customer_id`, `user_id`),
  UNIQUE KEY `uniq_user_customer` (`user_id`),
  KEY `idx_customer_user_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__gpsportal_customer_devices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int unsigned NOT NULL,
  `device_id` int NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_customer_device` (`customer_id`, `device_id`),
  UNIQUE KEY `uniq_device_customer` (`device_id`),
  KEY `idx_customer_device_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__gpsportal_user_hidden_devices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `device_id` int NOT NULL,
  `created` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_hidden_user_device` (`user_id`, `device_id`),
  KEY `idx_hidden_user` (`user_id`),
  KEY `idx_hidden_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__gpsportal_demo_vehicles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `device_id` int NOT NULL,
  `region` varchar(255) NOT NULL,
  `start_address` varchar(500) NOT NULL,
  `start_latitude` decimal(10,7) NOT NULL,
  `start_longitude` decimal(10,7) NOT NULL,
  `destinations_json` longtext NOT NULL,
  `minimum_speed_kmh` smallint unsigned NOT NULL DEFAULT 20,
  `maximum_speed_kmh` smallint unsigned NOT NULL DEFAULT 100,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sync_status` varchar(30) NOT NULL DEFAULT 'pending',
  `sync_message` text DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_demo_device` (`device_id`),
  KEY `idx_demo_active` (`active`),
  KEY `idx_demo_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__gpsportal_demo_vehicle_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `device_id` int NOT NULL,
  `user_id` int unsigned NOT NULL,
  `fixed_assignment` tinyint(1) NOT NULL DEFAULT 0,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_demo_vehicle_assignment` (`device_id`),
  KEY `idx_demo_assignment_user` (`user_id`),
  KEY `idx_demo_assignment_fixed` (`fixed_assignment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
