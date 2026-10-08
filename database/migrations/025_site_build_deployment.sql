-- Sprint 8.8 M6B. Additive schema only; no targets, approvals, jobs or releases seeded.
-- MySQL DDL commits implicitly. Execute only through separately authorized migration.
-- UTC DATETIME(6); history is RESTRICT, nullable user provenance is SET NULL.

ALTER TABLE site_business_associations
    ADD UNIQUE KEY uq_site_business_id_site_business (id, site_id, business_id);
ALTER TABLE site_approvals
    ADD UNIQUE KEY uq_site_approvals_id_revision_site (id, revision_id, site_id);

CREATE TABLE site_build_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    job_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    release_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    revision_id BIGINT UNSIGNED NOT NULL,
    business_id BIGINT UNSIGNED NULL,
    association_id BIGINT UNSIGNED NULL,
    snapshot_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    build_input_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    builder_version VARCHAR(64) NOT NULL,
    builder_code_sha CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    build_profile VARCHAR(40) NOT NULL,
    build_options_json JSON NOT NULL,
    input_manifest_json JSON NOT NULL,
    idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    requested_by_user_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(24) NOT NULL,
    correlation_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'requested',
    next_attempt_at DATETIME(6) NULL,
    current_attempt_id BIGINT UNSIGNED NULL,
    lock_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    attempt_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    execution_count INT UNSIGNED NOT NULL DEFAULT 0,
    recovery_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    max_execution_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    max_automatic_recoveries INT UNSIGNED NOT NULL DEFAULT 2,
    automatic_recovery_count INT UNSIGNED NOT NULL DEFAULT 0,
    recovery_status VARCHAR(16) NOT NULL DEFAULT 'none',
    next_recovery_at DATETIME(6) NULL,
    worker_policy_version VARCHAR(64) NOT NULL,
    worker_policy_json JSON NOT NULL,
    failure_category VARCHAR(40) NULL,
    failure_code VARCHAR(64) NULL,
    safe_summary VARCHAR(500) NULL,
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_sbj_job_key (job_key),
    UNIQUE KEY uq_sbj_release_key (release_key),
    UNIQUE KEY uq_sbj_identity (idempotency_key),
    UNIQUE KEY uq_sbj_id_site (id, site_id),
    UNIQUE KEY uq_sbj_id_revision_site (id, revision_id, site_id),
    INDEX idx_sbj_due (status, next_attempt_at, id),
    INDEX idx_sbj_history (site_id, created_at, id),
    INDEX idx_sbj_revision (revision_id, site_id),
    INDEX idx_sbj_correlation (correlation_id),
    CONSTRAINT fk_sbj_revision FOREIGN KEY (revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sbj_association FOREIGN KEY (association_id, site_id, business_id) REFERENCES site_business_associations (id, site_id, business_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sbj_business FOREIGN KEY (business_id) REFERENCES businesses (id) ON DELETE RESTRICT,
    CONSTRAINT fk_sbj_requester FOREIGN KEY (requested_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_sbj_business CHECK ((business_id IS NULL AND association_id IS NULL) OR (business_id IS NOT NULL AND association_id IS NOT NULL)),
    CONSTRAINT chk_sbj_status CHECK (status IN ('requested','running','retry_wait','succeeded','failed','reconciliation_required','cancelled')),
    INDEX idx_sbj_recovery_due (recovery_status, next_recovery_at, id),
    CONSTRAINT chk_sbj_counters CHECK (attempt_count = execution_count + recovery_count
        AND max_execution_attempts BETWEEN 1 AND 3 AND execution_count <= max_execution_attempts
        AND max_automatic_recoveries BETWEEN 0 AND 2
        AND automatic_recovery_count <= max_automatic_recoveries
        AND automatic_recovery_count <= recovery_count),
    CONSTRAINT chk_sbj_recovery CHECK (recovery_status IN ('none','required','running','blocked','resolved')),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_build_jobs_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_build_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    build_job_id BIGINT UNSIGNED NOT NULL,
    attempt_number BIGINT UNSIGNED NOT NULL,
    attempt_kind VARCHAR(16) NOT NULL,
    execution_number INT UNSIGNED NULL,
    recovery_number BIGINT UNSIGNED NULL,
    worker_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    lease_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    recovery_of_attempt_id BIGINT UNSIGNED NULL,
    recovery_trigger VARCHAR(16) NULL,
    operator_request_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    recovery_authorized_by_user_id BIGINT UNSIGNED NULL,
    recovery_actor_type VARCHAR(24) NULL,
    recovery_reason_code VARCHAR(64) NULL,
    status VARCHAR(24) NOT NULL,
    leased_at DATETIME(6) NOT NULL,
    deadline_at DATETIME(6) NOT NULL,
    lease_expires_at DATETIME(6) NOT NULL,
    heartbeat_at DATETIME(6) NOT NULL,
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    external_reference VARCHAR(191) NULL,
    failure_category VARCHAR(40) NULL,
    failure_code VARCHAR(64) NULL,
    safe_summary VARCHAR(500) NULL,
    correlation_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    candidate_storage_key VARCHAR(500) NULL,
    candidate_artifact_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    UNIQUE KEY uq_sba_number (build_job_id, attempt_number),
    UNIQUE KEY uq_sba_execution (build_job_id, execution_number),
    UNIQUE KEY uq_sba_recovery (build_job_id, recovery_number),
    UNIQUE KEY uq_sba_operator (build_job_id, operator_request_key),
    UNIQUE KEY uq_sba_id_site (id, site_id),
    UNIQUE KEY uq_sba_id_parent_site (id, build_job_id, site_id),
    INDEX idx_sba_lease (status, lease_expires_at, id),
    INDEX idx_sba_correlation (correlation_id),
    CONSTRAINT fk_sba_parent FOREIGN KEY (build_job_id, site_id) REFERENCES site_build_jobs (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sba_original FOREIGN KEY (recovery_of_attempt_id, build_job_id, site_id) REFERENCES site_build_attempts (id, build_job_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sba_actor FOREIGN KEY (recovery_authorized_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_sba_number CHECK (attempt_number > 0),
    CONSTRAINT chk_sba_lease CHECK (leased_at < lease_expires_at AND lease_expires_at <= deadline_at),
    CONSTRAINT chk_sba_status CHECK (status IN ('leased','running','succeeded','failed','expired','abandoned')),
    -- Historical actor deletion must remain possible. The actor FK is deliberately
    -- excluded from ALL CHECKs; authenticated insertion shape belongs to the service.
    CONSTRAINT chk_sba_shape CHECK (
        (attempt_kind = 'execution' AND execution_number IS NOT NULL AND execution_number BETWEEN 1 AND 3
         AND recovery_number IS NULL AND recovery_of_attempt_id IS NULL AND recovery_trigger IS NULL
         AND operator_request_key IS NULL AND recovery_actor_type IS NULL AND recovery_reason_code IS NULL)
        OR
        (attempt_kind = 'recovery' AND execution_number IS NULL AND recovery_number IS NOT NULL AND recovery_number > 0
         AND recovery_of_attempt_id IS NOT NULL AND recovery_trigger IS NOT NULL
         AND recovery_actor_type IS NOT NULL AND recovery_reason_code IS NOT NULL
         AND ((recovery_trigger = 'automatic' AND operator_request_key IS NULL AND recovery_actor_type = 'system')
           OR (recovery_trigger = 'operator' AND operator_request_key IS NOT NULL
               AND recovery_actor_type IN ('internal_admin','super_admin'))))
    ),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_build_attempts_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE site_build_jobs
    ADD CONSTRAINT fk_sbj_current_attempt FOREIGN KEY (current_attempt_id, id, site_id)
        REFERENCES site_build_attempts (id, build_job_id, site_id) ON DELETE RESTRICT;

CREATE TABLE site_releases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    release_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    build_job_id BIGINT UNSIGNED NOT NULL,
    source_revision_id BIGINT UNSIGNED NOT NULL,
    source_snapshot_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    build_input_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    artifact_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    manifest_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    builder_version VARCHAR(64) NOT NULL,
    builder_code_sha CHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    build_profile VARCHAR(40) NOT NULL,
    storage_backend VARCHAR(32) NOT NULL,
    storage_key VARCHAR(500) NOT NULL,
    manifest_json JSON NOT NULL,
    validation_summary_json JSON NOT NULL,
    file_count INT UNSIGNED NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    built_at DATETIME(6) NOT NULL,
    correlation_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    UNIQUE KEY uq_sr_release_key (release_key),
    UNIQUE KEY uq_sr_build_job (build_job_id),
    UNIQUE KEY uq_sr_id_site (id, site_id),
    UNIQUE KEY uq_sr_id_revision_site (id, source_revision_id, site_id),
    INDEX idx_sr_history (site_id, created_at, id),
    INDEX idx_sr_artifact (artifact_hash),
    INDEX idx_sr_correlation (correlation_id),
    CONSTRAINT fk_sr_job_revision FOREIGN KEY (build_job_id, source_revision_id, site_id) REFERENCES site_build_jobs (id, revision_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sr_revision FOREIGN KEY (source_revision_id, site_id) REFERENCES site_revisions (id, site_id) ON DELETE RESTRICT,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_releases_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_release_validations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NULL,
    build_attempt_id BIGINT UNSIGNED NULL,
    validation_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    validation_phase VARCHAR(32) NOT NULL,
    validator_version VARCHAR(64) NOT NULL,
    artifact_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    result VARCHAR(16) NOT NULL,
    summary_json JSON NOT NULL,
    checked_at DATETIME(6) NOT NULL,
    correlation_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    UNIQUE KEY uq_srv_validation_key (validation_key),
    UNIQUE KEY uq_srv_id_site (id, site_id),
    INDEX idx_srv_release (release_id, checked_at, id),
    INDEX idx_srv_attempt (build_attempt_id, checked_at, id),
    INDEX idx_srv_correlation (correlation_id),
    CONSTRAINT fk_srv_release FOREIGN KEY (release_id, site_id) REFERENCES site_releases (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_srv_attempt FOREIGN KEY (build_attempt_id, site_id) REFERENCES site_build_attempts (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT chk_srv_owner CHECK (release_id IS NOT NULL OR build_attempt_id IS NOT NULL),
    CONSTRAINT chk_srv_phase CHECK (validation_phase IN ('build','seal','stage','restore','reconcile')),
    CONSTRAINT chk_srv_result CHECK (result IN ('pass','fail')),
    CONSTRAINT chk_srv_summary CHECK (OCTET_LENGTH(summary_json) <= 16384),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_release_validations_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_deployment_targets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    environment VARCHAR(16) NOT NULL,
    publisher_key VARCHAR(64) NOT NULL,
    binding_key VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    binding_version INT UNSIGNED NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    current_deployment_id BIGINT UNSIGNED NULL,
    active_deployment_id BIGINT UNSIGNED NULL,
    pointer_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    fence_epoch BIGINT UNSIGNED NOT NULL DEFAULT 0,
    reconciliation_status VARCHAR(24) NOT NULL DEFAULT 'clean',
    last_observed_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_sdt_site_environment (site_id, environment),
    UNIQUE KEY uq_sdt_binding (environment, binding_key),
    UNIQUE KEY uq_sdt_id_site (id, site_id),
    INDEX idx_sdt_enabled_reconciliation (enabled, reconciliation_status, id),
    CONSTRAINT chk_sdt_environment CHECK (environment IN ('staging','production')),
    CONSTRAINT chk_sdt_enabled CHECK (enabled IN (0,1)),
    CONSTRAINT chk_sdt_reconciliation CHECK (reconciliation_status IN ('clean','required','blocked')),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_deployment_targets_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_deployments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    deployment_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NOT NULL,
    source_revision_id BIGINT UNSIGNED NOT NULL,
    operation VARCHAR(16) NOT NULL,
    request_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_payload_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    request_payload_json JSON NOT NULL,
    reason_code VARCHAR(64) NOT NULL,
    expected_current_deployment_id BIGINT UNSIGNED NULL,
    expected_pointer_version BIGINT UNSIGNED NOT NULL,
    restored_from_deployment_id BIGINT UNSIGNED NULL,
    deployment_approval_id BIGINT UNSIGNED NULL,
    requested_by_user_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(24) NOT NULL,
    correlation_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    binding_version INT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'requested',
    next_attempt_at DATETIME(6) NULL,
    current_attempt_id BIGINT UNSIGNED NULL,
    attempt_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    execution_count INT UNSIGNED NOT NULL DEFAULT 0,
    recovery_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    max_execution_attempts INT UNSIGNED NOT NULL DEFAULT 3,
    max_automatic_recoveries INT UNSIGNED NOT NULL DEFAULT 2,
    automatic_recovery_count INT UNSIGNED NOT NULL DEFAULT 0,
    recovery_status VARCHAR(16) NOT NULL DEFAULT 'none',
    next_recovery_at DATETIME(6) NULL,
    worker_policy_version VARCHAR(64) NOT NULL,
    worker_policy_json JSON NOT NULL,
    previous_deployment_id BIGINT UNSIGNED NULL,
    activation_fence BIGINT UNSIGNED NULL,
    external_reference VARCHAR(191) NULL,
    failure_category VARCHAR(40) NULL,
    failure_code VARCHAR(64) NULL,
    safe_summary VARCHAR(500) NULL,
    started_at DATETIME(6) NULL,
    staged_at DATETIME(6) NULL,
    activation_attempted_at DATETIME(6) NULL,
    health_verified_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    updated_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_sd_deployment_key (deployment_key),
    UNIQUE KEY uq_sd_request (site_id, target_id, request_key),
    UNIQUE KEY uq_sd_id_site (id, site_id),
    UNIQUE KEY uq_sd_id_target_site (id, target_id, site_id),
    INDEX idx_sd_due (status, next_attempt_at, id),
    INDEX idx_sd_history (target_id, created_at, id),
    INDEX idx_sd_release (release_id, site_id),
    INDEX idx_sd_correlation (correlation_id),
    CONSTRAINT fk_sd_target FOREIGN KEY (target_id, site_id) REFERENCES site_deployment_targets (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sd_release FOREIGN KEY (release_id, source_revision_id, site_id) REFERENCES site_releases (id, source_revision_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sd_previous FOREIGN KEY (previous_deployment_id, target_id, site_id) REFERENCES site_deployments (id, target_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sd_expected FOREIGN KEY (expected_current_deployment_id, target_id, site_id) REFERENCES site_deployments (id, target_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sd_restore FOREIGN KEY (restored_from_deployment_id, target_id, site_id) REFERENCES site_deployments (id, target_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sd_requester FOREIGN KEY (requested_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_sd_operation CHECK ((operation = 'publish' AND restored_from_deployment_id IS NULL) OR (operation = 'restore' AND restored_from_deployment_id IS NOT NULL)),
    CONSTRAINT chk_sd_status CHECK (status IN ('requested','running','artifact_staged','activation_attempted','health_check_pending','succeeded','retry_wait','failed','reconciliation_required','cancelled')),
    INDEX idx_sd_recovery_due (recovery_status, next_recovery_at, id),
    CONSTRAINT chk_sd_counters CHECK (attempt_count = execution_count + recovery_count
        AND max_execution_attempts BETWEEN 1 AND 3 AND execution_count <= max_execution_attempts
        AND max_automatic_recoveries BETWEEN 0 AND 2
        AND automatic_recovery_count <= max_automatic_recoveries
        AND automatic_recovery_count <= recovery_count),
    CONSTRAINT chk_sd_recovery CHECK (recovery_status IN ('none','required','running','blocked','resolved')),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_deployments_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_deployment_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    deployment_id BIGINT UNSIGNED NOT NULL,
    attempt_number BIGINT UNSIGNED NOT NULL,
    attempt_kind VARCHAR(16) NOT NULL,
    execution_number INT UNSIGNED NULL,
    recovery_number BIGINT UNSIGNED NULL,
    worker_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    lease_token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    recovery_of_attempt_id BIGINT UNSIGNED NULL,
    recovery_trigger VARCHAR(16) NULL,
    operator_request_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    recovery_authorized_by_user_id BIGINT UNSIGNED NULL,
    recovery_actor_type VARCHAR(24) NULL,
    recovery_reason_code VARCHAR(64) NULL,
    status VARCHAR(24) NOT NULL,
    leased_at DATETIME(6) NOT NULL,
    deadline_at DATETIME(6) NOT NULL,
    lease_expires_at DATETIME(6) NOT NULL,
    heartbeat_at DATETIME(6) NOT NULL,
    started_at DATETIME(6) NULL,
    completed_at DATETIME(6) NULL,
    external_reference VARCHAR(191) NULL,
    failure_category VARCHAR(40) NULL,
    failure_code VARCHAR(64) NULL,
    safe_summary VARCHAR(500) NULL,
    correlation_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    fence_epoch BIGINT UNSIGNED NOT NULL,
    receipt_json JSON NULL,
    CONSTRAINT chk_sda_receipt CHECK (receipt_json IS NULL OR OCTET_LENGTH(receipt_json) <= 16384),
    UNIQUE KEY uq_sda_number (deployment_id, attempt_number),
    UNIQUE KEY uq_sda_execution (deployment_id, execution_number),
    UNIQUE KEY uq_sda_recovery (deployment_id, recovery_number),
    UNIQUE KEY uq_sda_operator (deployment_id, operator_request_key),
    UNIQUE KEY uq_sda_id_site (id, site_id),
    UNIQUE KEY uq_sda_id_parent_site (id, deployment_id, site_id),
    INDEX idx_sda_lease (status, lease_expires_at, id),
    INDEX idx_sda_correlation (correlation_id),
    CONSTRAINT fk_sda_parent FOREIGN KEY (deployment_id, site_id) REFERENCES site_deployments (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sda_original FOREIGN KEY (recovery_of_attempt_id, deployment_id, site_id) REFERENCES site_deployment_attempts (id, deployment_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sda_actor FOREIGN KEY (recovery_authorized_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_sda_number CHECK (attempt_number > 0),
    CONSTRAINT chk_sda_lease CHECK (leased_at < lease_expires_at AND lease_expires_at <= deadline_at),
    CONSTRAINT chk_sda_status CHECK (status IN ('leased','running','succeeded','failed','expired','abandoned')),
    -- Historical actor deletion must remain possible. The actor FK is deliberately
    -- excluded from ALL CHECKs; authenticated insertion shape belongs to the service.
    CONSTRAINT chk_sda_shape CHECK (
        (attempt_kind = 'execution' AND execution_number IS NOT NULL AND execution_number BETWEEN 1 AND 3
         AND recovery_number IS NULL AND recovery_of_attempt_id IS NULL AND recovery_trigger IS NULL
         AND operator_request_key IS NULL AND recovery_actor_type IS NULL AND recovery_reason_code IS NULL)
        OR
        (attempt_kind = 'recovery' AND execution_number IS NULL AND recovery_number IS NOT NULL AND recovery_number > 0
         AND recovery_of_attempt_id IS NOT NULL AND recovery_trigger IS NOT NULL
         AND recovery_actor_type IS NOT NULL AND recovery_reason_code IS NOT NULL
         AND ((recovery_trigger = 'automatic' AND operator_request_key IS NULL AND recovery_actor_type = 'system')
           OR (recovery_trigger = 'operator' AND operator_request_key IS NOT NULL
               AND recovery_actor_type IN ('internal_admin','super_admin'))))
    ),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_deployment_attempts_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_deployment_health_checks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    deployment_id BIGINT UNSIGNED NOT NULL,
    deployment_attempt_id BIGINT UNSIGNED NOT NULL,
    check_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    phase VARCHAR(24) NOT NULL,
    probe_profile VARCHAR(64) NOT NULL,
    expected_release_id BIGINT UNSIGNED NULL,
    expected_absent TINYINT(1) NOT NULL DEFAULT 0,
    observed_release_key CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    observed_artifact_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    http_status SMALLINT UNSIGNED NULL,
    duration_ms INT UNSIGNED NOT NULL,
    result VARCHAR(16) NOT NULL,
    failure_code VARCHAR(64) NULL,
    summary_json JSON NOT NULL,
    checked_at DATETIME(6) NOT NULL,
    correlation_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    UNIQUE KEY uq_sdhc_check_key (check_key),
    UNIQUE KEY uq_sdhc_id_site (id, site_id),
    INDEX idx_sdhc_deployment (deployment_id, checked_at, id),
    INDEX idx_sdhc_attempt (deployment_attempt_id, checked_at, id),
    INDEX idx_sdhc_correlation (correlation_id),
    CONSTRAINT fk_sdhc_deployment FOREIGN KEY (deployment_id, site_id) REFERENCES site_deployments (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sdhc_attempt FOREIGN KEY (deployment_attempt_id, deployment_id, site_id) REFERENCES site_deployment_attempts (id, deployment_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sdhc_release FOREIGN KEY (expected_release_id, site_id) REFERENCES site_releases (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT chk_sdhc_phase CHECK (phase IN ('candidate','active','rollback','reconcile')),
    CONSTRAINT chk_sdhc_result CHECK (result IN ('pass','fail')),
    CONSTRAINT chk_sdhc_expected CHECK ((expected_absent = 0 AND expected_release_id IS NOT NULL) OR (expected_absent = 1 AND expected_release_id IS NULL AND phase IN ('rollback','reconcile'))),
    CONSTRAINT chk_sdhc_summary CHECK (OCTET_LENGTH(summary_json) <= 4096),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_deployment_health_checks_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_deployment_approvals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id BIGINT UNSIGNED NOT NULL,
    approval_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NOT NULL,
    source_revision_id BIGINT UNSIGNED NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    operation VARCHAR(16) NOT NULL,
    artifact_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expected_current_deployment_id BIGINT UNSIGNED NULL,
    expected_pointer_version BIGINT UNSIGNED NOT NULL,
    binding_version INT UNSIGNED NOT NULL,
    expires_at DATETIME(6) NOT NULL,
    consumed_by_deployment_id BIGINT UNSIGNED NULL,
    consumed_at DATETIME(6) NULL,
    correlation_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    UNIQUE KEY uq_sdap_approval (approval_id),
    UNIQUE KEY uq_sdap_id_site (id, site_id),
    UNIQUE KEY uq_sdap_binding (id, release_id, target_id, site_id),
    INDEX idx_sdap_expiry (target_id, expires_at, id),
    INDEX idx_sdap_release (release_id, site_id),
    INDEX idx_sdap_correlation (correlation_id),
    CONSTRAINT fk_sdap_approval FOREIGN KEY (approval_id, source_revision_id, site_id) REFERENCES site_approvals (id, revision_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sdap_release FOREIGN KEY (release_id, source_revision_id, site_id) REFERENCES site_releases (id, source_revision_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sdap_target FOREIGN KEY (target_id, site_id) REFERENCES site_deployment_targets (id, site_id) ON DELETE RESTRICT,
    CONSTRAINT fk_sdap_expected FOREIGN KEY (expected_current_deployment_id, target_id, site_id) REFERENCES site_deployments (id, target_id, site_id) ON DELETE RESTRICT,
    CONSTRAINT chk_sdap_operation CHECK (operation IN ('publish','restore')),
    CONSTRAINT chk_sdap_consumed CHECK ((consumed_by_deployment_id IS NULL AND consumed_at IS NULL) OR (consumed_by_deployment_id IS NOT NULL AND consumed_at IS NOT NULL)),
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_site_deployment_approvals_site FOREIGN KEY (site_id) REFERENCES sites (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deferred nullable ownership relationships: every participating table now exists.
ALTER TABLE site_deployment_targets
    ADD CONSTRAINT fk_sdt_current FOREIGN KEY (current_deployment_id, id, site_id) REFERENCES site_deployments (id, target_id, site_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_sdt_active FOREIGN KEY (active_deployment_id, id, site_id) REFERENCES site_deployments (id, target_id, site_id) ON DELETE RESTRICT;
ALTER TABLE site_deployments
    ADD CONSTRAINT fk_sd_current_attempt FOREIGN KEY (current_attempt_id, id, site_id) REFERENCES site_deployment_attempts (id, deployment_id, site_id) ON DELETE RESTRICT,
    ADD CONSTRAINT fk_sd_approval FOREIGN KEY (deployment_approval_id, release_id, target_id, site_id) REFERENCES site_deployment_approvals (id, release_id, target_id, site_id) ON DELETE RESTRICT;
ALTER TABLE site_deployment_approvals
    ADD CONSTRAINT fk_sdap_consumed FOREIGN KEY (consumed_by_deployment_id, target_id, site_id) REFERENCES site_deployments (id, target_id, site_id) ON DELETE RESTRICT;
