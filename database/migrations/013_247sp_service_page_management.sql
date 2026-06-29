ALTER TABLE `247sp_service_pages`
    ADD COLUMN parent_service_page_id BIGINT UNSIGNED NULL AFTER service_number,
    ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER parent_service_page_id,
    ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER sort_order,
    ADD COLUMN slug VARCHAR(255) NULL AFTER short_description,
    ADD INDEX idx_247sp_service_pages_parent (parent_service_page_id),
    ADD INDEX idx_247sp_service_pages_status_order (business_id, status, sort_order),
    ADD UNIQUE KEY uq_247sp_service_pages_business_slug (business_id, slug),
    ADD CONSTRAINT fk_247sp_service_pages_parent
        FOREIGN KEY (parent_service_page_id) REFERENCES `247sp_service_pages` (id)
        ON DELETE SET NULL;

UPDATE `247sp_service_pages`
SET sort_order = service_number * 10
WHERE sort_order = 0;
