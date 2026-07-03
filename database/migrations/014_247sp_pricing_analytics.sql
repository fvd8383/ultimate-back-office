ALTER TABLE `247sp_website_branding`
    ADD COLUMN ga_measurement_id VARCHAR(32) NULL AFTER about_image_path;

UPDATE plans
SET monthly_fee = 47.00
WHERE product_key = '247sp';
