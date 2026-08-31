CREATE TABLE sites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_key CHAR(36) NOT NULL,
    purpose VARCHAR(32) NOT NULL,
    lifecycle_status VARCHAR(40) NOT NULL DEFAULT 'draft',
    current_published_revision_id BIGINT UNSIGNED NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    suspended_at DATETIME NULL,
    archived_at DATETIME NULL,
    lock_version INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sites_site_key (site_key),
    INDEX idx_sites_purpose_lifecycle (purpose, lifecycle_status),
    INDEX idx_sites_lifecycle (lifecycle_status),
    INDEX idx_sites_current_published_revision (current_published_revision_id, id),
    CONSTRAINT chk_sites_purpose CHECK (purpose IN ('247sp', 'emd', 'internal_demo')),
    CONSTRAINT chk_sites_lifecycle CHECK (lifecycle_status IN (
        'draft', 'demo', 'pending_customer', 'pending_internal_review', 'approved',
        'active', 'suspended', 'cancellation_pending', 'conversion_pending', 'archived'
    )),
    CONSTRAINT fk_sites_created_by_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_business_associations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NOT NULL,
    association_role VARCHAR(32) NOT NULL DEFAULT 'customer',
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    effective_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    reason VARCHAR(500) NULL,
    correlation_id VARCHAR(100) NULL,
    active_customer_site_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE
            WHEN association_role = 'customer' AND status = 'active' THEN site_id
            ELSE NULL
        END
    ) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_business_active_customer (active_customer_site_id),
    INDEX idx_site_business_business_status (business_id, status),
    INDEX idx_site_business_site_status (site_id, status),
    INDEX idx_site_business_correlation (correlation_id),
    CONSTRAINT fk_site_business_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_business_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_business_actor FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    page_key VARCHAR(100) NOT NULL,
    retired_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_pages_site_key (site_id, page_key),
    UNIQUE KEY uq_site_pages_id_site (id, site_id),
    INDEX idx_site_pages_site_retired (site_id, retired_at),
    CONSTRAINT fk_site_pages_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_generation_briefs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    brief_version INT UNSIGNED NOT NULL,
    state VARCHAR(32) NOT NULL DEFAULT 'imported',
    brief_json JSON NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    source_reference VARCHAR(191) NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    superseded_at DATETIME NULL,
    content_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_generation_briefs_version (site_id, brief_version),
    UNIQUE KEY uq_site_generation_briefs_hash (site_id, content_hash),
    UNIQUE KEY uq_site_generation_briefs_id_site (id, site_id),
    INDEX idx_site_generation_briefs_state (site_id, state),
    CONSTRAINT fk_site_generation_briefs_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_generation_briefs_actor FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_revisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    revision_number INT UNSIGNED NOT NULL,
    lifecycle_status VARCHAR(40) NOT NULL DEFAULT 'draft',
    based_on_revision_id BIGINT UNSIGNED NULL,
    restored_from_revision_id BIGINT UNSIGNED NULL,
    generation_brief_id BIGINT UNSIGNED NULL,
    materiality VARCHAR(24) NOT NULL DEFAULT 'undetermined',
    snapshot_schema_version INT UNSIGNED NOT NULL,
    facts_snapshot_json JSON NOT NULL,
    source_references_json JSON NOT NULL,
    snapshot_hash CHAR(64) NOT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    review_ready_at DATETIME NULL,
    published_at DATETIME NULL,
    superseded_at DATETIME NULL,
    correlation_id VARCHAR(100) NULL,
    published_site_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN lifecycle_status = 'published' THEN site_id ELSE NULL END
    ) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_revisions_number (site_id, revision_number),
    INDEX idx_site_revisions_snapshot (site_id, snapshot_hash),
    UNIQUE KEY uq_site_revisions_published (published_site_id),
    UNIQUE KEY uq_site_revisions_id_site (id, site_id),
    INDEX idx_site_revisions_site_status (site_id, lifecycle_status),
    INDEX idx_site_revisions_correlation (correlation_id),
    CONSTRAINT chk_site_revisions_lifecycle CHECK (lifecycle_status IN (
        'draft', 'validation_failed', 'ready_for_review', 'changes_requested',
        'customer_approved', 'internally_approved', 'published', 'superseded', 'restored'
    )),
    CONSTRAINT chk_site_revisions_materiality CHECK (materiality IN ('material', 'non_material', 'undetermined')),
    CONSTRAINT fk_site_revisions_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_revisions_based_on_site FOREIGN KEY (based_on_revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_revisions_restored_from_site FOREIGN KEY (restored_from_revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_revisions_brief_site FOREIGN KEY (generation_brief_id, site_id) REFERENCES site_generation_briefs (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_revisions_actor FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sites
    ADD CONSTRAINT fk_sites_current_published_revision
        FOREIGN KEY (current_published_revision_id, id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT;

CREATE TABLE component_definitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component_key VARCHAR(100) NOT NULL,
    label VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    implementation_version VARCHAR(50) NOT NULL,
    configuration_schema_version INT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    metadata_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_component_definitions_key (component_key),
    INDEX idx_component_definitions_status_category (status, category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE component_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component_definition_id BIGINT UNSIGNED NOT NULL,
    variant_key VARCHAR(100) NOT NULL,
    label VARCHAR(150) NOT NULL,
    configuration_schema_version INT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'active',
    metadata_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_component_variants_definition_key (component_definition_id, variant_key),
    INDEX idx_component_variants_status (status),
    CONSTRAINT fk_component_variants_definition FOREIGN KEY (component_definition_id) REFERENCES component_definitions (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_revision_pages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    revision_id BIGINT UNSIGNED NOT NULL,
    site_page_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    page_type VARCHAR(50) NOT NULL,
    navigation_label VARCHAR(150) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    seo_json JSON NULL,
    presentation_json JSON NOT NULL,
    content_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_revision_pages_page (revision_id, site_page_id),
    UNIQUE KEY uq_site_revision_pages_slug (revision_id, slug),
    UNIQUE KEY uq_site_revision_pages_order (revision_id, sort_order),
    UNIQUE KEY uq_site_revision_pages_id_site (id, site_id),
    UNIQUE KEY uq_site_revision_pages_id_revision_site (id, revision_id, site_id),
    INDEX idx_site_revision_pages_revision_order (revision_id, sort_order),
    CONSTRAINT fk_site_revision_pages_revision_site FOREIGN KEY (revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_revision_pages_page_site FOREIGN KEY (site_page_id, site_id) REFERENCES site_pages (id, site_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_page_sections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    revision_id BIGINT UNSIGNED NOT NULL,
    revision_page_id BIGINT UNSIGNED NOT NULL,
    section_key VARCHAR(100) NOT NULL,
    component_variant_id BIGINT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    configuration_schema_version INT UNSIGNED NOT NULL,
    configuration_json JSON NOT NULL,
    content_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_page_sections_key (revision_page_id, section_key),
    UNIQUE KEY uq_site_page_sections_order (revision_page_id, sort_order),
    UNIQUE KEY uq_site_page_sections_id_site (id, site_id),
    UNIQUE KEY uq_site_page_sections_id_revision_site (id, revision_id, site_id),
    INDEX idx_site_page_sections_variant (component_variant_id),
    CONSTRAINT fk_site_page_sections_revision_page_site FOREIGN KEY (revision_page_id, revision_id, site_id) REFERENCES site_revision_pages (id, revision_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_page_sections_variant FOREIGN KEY (component_variant_id) REFERENCES component_variants (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_themes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    revision_id BIGINT UNSIGNED NOT NULL,
    theme_key VARCHAR(100) NOT NULL,
    theme_version INT UNSIGNED NOT NULL,
    primary_color VARCHAR(7) NULL,
    secondary_color VARCHAR(7) NULL,
    typography_json JSON NULL,
    configuration_json JSON NOT NULL,
    content_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_themes_revision (revision_id),
    INDEX idx_site_themes_key_version (theme_key, theme_version),
    CONSTRAINT fk_site_themes_revision_site FOREIGN KEY (revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NULL,
    business_id BIGINT UNSIGNED NULL,
    asset_key CHAR(36) NOT NULL,
    asset_type VARCHAR(50) NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    checksum_sha256 CHAR(64) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    width_pixels INT UNSIGNED NULL,
    height_pixels INT UNSIGNED NULL,
    source VARCHAR(50) NOT NULL,
    rights_classification VARCHAR(50) NOT NULL,
    rights_metadata_json JSON NULL,
    rights_expires_at DATETIME NULL,
    lifecycle_status VARCHAR(32) NOT NULL DEFAULT 'ready',
    retention_hold TINYINT(1) NOT NULL DEFAULT 0,
    uploaded_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_assets_asset_key (asset_key),
    UNIQUE KEY uq_site_assets_id_site (id, site_id),
    INDEX idx_site_assets_site_lifecycle (site_id, lifecycle_status),
    INDEX idx_site_assets_business_lifecycle (business_id, lifecycle_status),
    INDEX idx_site_assets_checksum (checksum_sha256),
    CONSTRAINT fk_site_assets_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE SET NULL,
    CONSTRAINT fk_site_assets_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE SET NULL,
    CONSTRAINT fk_site_assets_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_revision_assets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    revision_id BIGINT UNSIGNED NOT NULL,
    asset_id BIGINT UNSIGNED NOT NULL,
    usage_key VARCHAR(100) NOT NULL,
    site_revision_page_id BIGINT UNSIGNED NULL,
    site_page_section_id BIGINT UNSIGNED NULL,
    source_reference VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_revision_assets_usage (revision_id, asset_id, usage_key),
    INDEX idx_site_revision_assets_asset (asset_id),
    INDEX idx_site_revision_assets_page (site_revision_page_id, revision_id, site_id),
    INDEX idx_site_revision_assets_section (site_page_section_id, revision_id, site_id),
    CONSTRAINT fk_site_revision_assets_revision_site FOREIGN KEY (revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_revision_assets_asset_site FOREIGN KEY (asset_id, site_id) REFERENCES site_assets (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_revision_assets_page_site FOREIGN KEY (site_revision_page_id, revision_id, site_id) REFERENCES site_revision_pages (id, revision_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_revision_assets_section_site FOREIGN KEY (site_page_section_id, revision_id, site_id) REFERENCES site_page_sections (id, revision_id, site_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_approvals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    revision_id BIGINT UNSIGNED NOT NULL,
    approval_type VARCHAR(32) NOT NULL,
    state VARCHAR(24) NOT NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(24) NOT NULL,
    comments TEXT NULL,
    reason VARCHAR(500) NULL,
    supersedes_approval_id BIGINT UNSIGNED NULL,
    requested_at DATETIME NOT NULL,
    decided_at DATETIME NULL,
    revoked_at DATETIME NULL,
    correlation_id VARCHAR(100) NULL,
    metadata_json JSON NULL,
    current_approved_revision_id BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN state = 'approved' AND revoked_at IS NULL THEN revision_id ELSE NULL END
    ) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_site_approvals_current (current_approved_revision_id, approval_type),
    UNIQUE KEY uq_site_approvals_id_site (id, site_id),
    INDEX idx_site_approvals_revision_type_state (revision_id, approval_type, state),
    INDEX idx_site_approvals_site_date (site_id, requested_at),
    INDEX idx_site_approvals_correlation (correlation_id),
    CONSTRAINT chk_site_approvals_type CHECK (approval_type IN ('customer', 'internal', 'production', 'conversion')),
    CONSTRAINT chk_site_approvals_state CHECK (state IN ('requested', 'approved', 'rejected', 'revoked', 'superseded')),
    CONSTRAINT fk_site_approvals_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_approvals_revision_site FOREIGN KEY (revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_approvals_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_site_approvals_supersedes_site FOREIGN KEY (supersedes_approval_id, site_id) REFERENCES site_approvals (id, site_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_site_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legacy_website_id BIGINT UNSIGNED NOT NULL,
    site_id BIGINT UNSIGNED NULL,
    import_revision_id BIGINT UNSIGNED NULL,
    import_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    source_hash CHAR(64) NULL,
    imported_hash CHAR(64) NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_attempted_at DATETIME NULL,
    imported_at DATETIME NULL,
    quarantined_at DATETIME NULL,
    error_code VARCHAR(64) NULL,
    error_summary VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_legacy_site_mappings_website (legacy_website_id),
    UNIQUE KEY uq_legacy_site_mappings_site (site_id),
    UNIQUE KEY uq_legacy_site_mappings_id_site (id, site_id),
    INDEX idx_legacy_site_mappings_status_attempted (import_status, last_attempted_at),
    INDEX idx_legacy_site_mappings_source_hash (source_hash),
    CONSTRAINT chk_legacy_site_mappings_status CHECK (import_status IN ('pending', 'imported', 'quarantined')),
    CONSTRAINT fk_legacy_site_mappings_website FOREIGN KEY (legacy_website_id) REFERENCES `247sp_generated_websites` (id) ON DELETE RESTRICT,
    CONSTRAINT fk_legacy_site_mappings_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT fk_legacy_site_mappings_revision_site FOREIGN KEY (import_revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legacy_site_page_mappings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    legacy_mapping_id BIGINT UNSIGNED NOT NULL,
    legacy_page_id BIGINT UNSIGNED NOT NULL,
    site_id BIGINT UNSIGNED NOT NULL,
    site_page_id BIGINT UNSIGNED NOT NULL,
    site_revision_page_id BIGINT UNSIGNED NOT NULL,
    source_hash CHAR(64) NOT NULL,
    imported_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_legacy_site_page_mappings_page (legacy_page_id),
    UNIQUE KEY uq_legacy_site_page_mappings_revision_page (site_revision_page_id),
    INDEX idx_legacy_site_page_mappings_mapping (legacy_mapping_id, site_id),
    CONSTRAINT fk_legacy_site_page_mappings_mapping_site FOREIGN KEY (legacy_mapping_id, site_id) REFERENCES legacy_site_mappings (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_legacy_site_page_mappings_legacy_page FOREIGN KEY (legacy_page_id) REFERENCES `247sp_generated_pages` (id) ON DELETE RESTRICT,
    CONSTRAINT fk_legacy_site_page_mappings_page_site FOREIGN KEY (site_page_id, site_id) REFERENCES site_pages (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_legacy_site_page_mappings_revision_page_site FOREIGN KEY (site_revision_page_id, site_id) REFERENCES site_revision_pages (id, site_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    revision_id BIGINT UNSIGNED NULL,
    actor_user_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(24) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    result VARCHAR(32) NOT NULL,
    reason VARCHAR(500) NULL,
    correlation_id VARCHAR(100) NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_site_events_site_date (site_id, created_at),
    INDEX idx_site_events_revision (revision_id),
    INDEX idx_site_events_type_result (event_type, result),
    INDEX idx_site_events_correlation (correlation_id),
    CONSTRAINT fk_site_events_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_events_revision_site FOREIGN KEY (revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_site_events_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO component_definitions (
    component_key, label, category, implementation_version,
    configuration_schema_version, status, metadata_json, created_at, updated_at
) VALUES (
    'legacy_247sp_page',
    'Legacy 247SP Page Snapshot',
    'legacy_import',
    'legacy-preview-v1',
    1,
    'active',
    JSON_OBJECT('source', 'legacy_247sp_preview', 'snapshot_only', TRUE),
    NOW(),
    NOW()
);

INSERT INTO component_variants (
    component_definition_id, variant_key, label, configuration_schema_version,
    status, metadata_json, created_at, updated_at
)
SELECT
    definition.id,
    seeded.variant_key,
    seeded.label,
    1,
    'active',
    JSON_OBJECT('legacy_page_type', seeded.variant_key, 'snapshot_only', TRUE),
    NOW(),
    NOW()
FROM component_definitions definition
INNER JOIN (
    SELECT 'home' AS variant_key, 'Legacy Home Page' AS label
    UNION ALL SELECT 'service', 'Legacy Service Page'
    UNION ALL SELECT 'about', 'Legacy About Page'
    UNION ALL SELECT 'contact', 'Legacy Contact Page'
) seeded ON 1 = 1
WHERE definition.component_key = 'legacy_247sp_page';
