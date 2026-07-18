ALTER TABLE `#__gpsportal_geofences`
  ADD COLUMN IF NOT EXISTS `zone_type` varchar(20) NOT NULL DEFAULT 'address' AFTER `name`,
  ADD COLUMN IF NOT EXISTS `address` varchar(500) NOT NULL DEFAULT '' AFTER `zone_type`,
  ADD COLUMN IF NOT EXISTS `country_code` char(2) DEFAULT NULL AFTER `address`,
  ADD COLUMN IF NOT EXISTS `status_color` varchar(20) NOT NULL DEFAULT 'green' AFTER `country_code`,
  ADD COLUMN IF NOT EXISTS `warning_buffer_km` smallint unsigned NOT NULL DEFAULT 0 AFTER `status_color`,
  ADD COLUMN IF NOT EXISTS `geometry_json` longtext DEFAULT NULL AFTER `warning_buffer_km`,
  ADD COLUMN IF NOT EXISTS `modified` datetime DEFAULT NULL,
  ADD KEY IF NOT EXISTS `idx_geofence_type` (`zone_type`),
  ADD KEY IF NOT EXISTS `idx_geofence_country` (`country_code`),
  ADD KEY IF NOT EXISTS `idx_geofence_color` (`status_color`);

UPDATE `#__gpsportal_geofences`
SET `zone_type` = 'address',
    `status_color` = 'green',
    `address` = CASE
      WHEN `address` = '' THEN CONCAT('Bestehende Geozone bei ', `latitude`, ', ', `longitude`)
      ELSE `address`
    END
WHERE `zone_type` IS NULL OR `zone_type` = '' OR `address` = '';
