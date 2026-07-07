ALTER TABLE `247sp_website_configurations`
    ADD COLUMN service_area_radius_miles INT UNSIGNED NULL AFTER service_area_business,
    ADD COLUMN service_area_radius_is_custom TINYINT(1) NOT NULL DEFAULT 0 AFTER service_area_radius_miles;

UPDATE `247sp_website_configurations`
SET service_area_radius_miles = 25,
    service_area_radius_is_custom = 0
WHERE service_area_business = 1
  AND service_area_radius_miles IS NULL;
