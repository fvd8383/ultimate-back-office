# Sprint 8.7 Migration 021 Validation

Use this checklist before applying `database/migrations/021_shared_business_profile.sql` to staging. Do not run these checks against production unless a separate deployment plan explicitly says to do so.

## Pre-Migration Checks

Confirm migrations `018`, `019`, and `020` are represented in the current schema:

```sql
SELECT table_name, column_name, column_type
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (table_name = '247sp_website_configurations' AND column_name IN ('service_area_radius_miles', 'service_area_radius_is_custom'))
    OR (table_name = 'domain_requests' AND column_name IN ('request_type', 'dns_status', 'ssl_status', 'next_action', 'last_error'))
    OR (table_name = 'domain_dns_records' AND column_name IN ('business_id', 'record_hash', 'domain_name', 'record_type', 'host', 'value'))
  )
ORDER BY table_name, column_name;
```

Confirm domain tables exist:

```sql
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('domain_requests', 'domain_assignments', 'website_domains', 'domain_dns_records', 'domain_events')
ORDER BY table_name;
```

Confirm existing service-area columns exist:

```sql
SELECT table_name, column_name, column_type
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND (
    (table_name = 'businesses' AND column_name = 'is_public_physical_location')
    OR (table_name = '247sp_website_configurations' AND column_name IN (
      'service_area_address',
      'service_area_city',
      'service_area_state',
      'service_area_postal_code',
      'service_area_business',
      'service_area_radius_miles',
      'service_area_radius_is_custom'
    ))
  )
ORDER BY table_name, column_name;
```

Record pre-migration business, domain, and LeadHub counts:

```sql
SELECT 'businesses' AS table_name, COUNT(*) AS row_count FROM businesses
UNION ALL SELECT 'contacts', COUNT(*) FROM contacts
UNION ALL SELECT 'notes', COUNT(*) FROM notes
UNION ALL SELECT 'tasks', COUNT(*) FROM tasks
UNION ALL SELECT 'activity_logs', COUNT(*) FROM activity_logs
UNION ALL SELECT 'domain_requests', COUNT(*) FROM domain_requests
UNION ALL SELECT 'domain_assignments', COUNT(*) FROM domain_assignments
UNION ALL SELECT 'website_domains', COUNT(*) FROM website_domains
UNION ALL SELECT 'domain_dns_records', COUNT(*) FROM domain_dns_records
UNION ALL SELECT 'domain_events', COUNT(*) FROM domain_events;
```

Check source-table uniqueness before backfill:

```sql
SELECT business_id, COUNT(*) AS row_count
FROM `247sp_business_content`
GROUP BY business_id
HAVING COUNT(*) > 1;

SELECT business_id, COUNT(*) AS row_count
FROM `247sp_website_configurations`
GROUP BY business_id
HAVING COUNT(*) > 1;
```

Expected state: no rows. Migration 021 uses the lowest `247sp_business_content.id` if duplicate content rows unexpectedly exist, but duplicate rows still require staging data review.

## Migration Command

Run only after the pre-migration checks are acceptable:

```bash
mysql -h DB_HOST -P DB_PORT -u DB_USER -p DB_NAME < database/migrations/021_shared_business_profile.sql
```

## Post-Migration Checks

Confirm all migration 021 tables were created:

```sql
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN (
    'business_profiles',
    'business_profile_hours',
    'business_profile_hour_exceptions',
    'business_profile_faqs',
    'business_profile_pricing_guidance',
    'business_appointment_rules',
    'business_transfer_rules',
    'business_escalation_rules',
    'business_notification_preferences'
  )
ORDER BY table_name;
```

Confirm the removed service-area table was not created:

```sql
SELECT table_name
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'business_profile_service_areas';
```

Expected state: no rows.

Confirm exactly one `business_profiles` row exists per business:

```sql
SELECT
  (SELECT COUNT(*) FROM businesses) AS business_count,
  (SELECT COUNT(*) FROM business_profiles) AS profile_count,
  (SELECT COUNT(DISTINCT business_id) FROM business_profiles) AS distinct_profile_business_count;

SELECT business_id, COUNT(*) AS profile_count
FROM business_profiles
GROUP BY business_id
HAVING COUNT(*) <> 1;
```

Expected state: counts match; duplicate query returns no rows.

Confirm no orphaned profile child records:

```sql
SELECT 'business_profile_hours' AS table_name, COUNT(*) AS orphan_count
FROM business_profile_hours c
LEFT JOIN business_profiles bp ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_profile_hour_exceptions', COUNT(*)
FROM business_profile_hour_exceptions c
LEFT JOIN business_profiles bp ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_profile_faqs', COUNT(*)
FROM business_profile_faqs c
LEFT JOIN business_profiles bp ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_profile_pricing_guidance', COUNT(*)
FROM business_profile_pricing_guidance c
LEFT JOIN business_profiles bp ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_appointment_rules', COUNT(*)
FROM business_appointment_rules c
LEFT JOIN business_profiles bp ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_transfer_rules', COUNT(*)
FROM business_transfer_rules c
LEFT JOIN business_profiles bp ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_escalation_rules', COUNT(*)
FROM business_escalation_rules c
LEFT JOIN business_profiles bp ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL
UNION ALL
SELECT 'business_notification_preferences', COUNT(*)
FROM business_notification_preferences c
LEFT JOIN business_profiles bp ON bp.id = c.business_profile_id AND bp.business_id = c.business_id
WHERE bp.id IS NULL;
```

Expected state: every `orphan_count` is `0`.

Confirm no conflicting service references:

```sql
SELECT 'business_appointment_rules' AS table_name, id, business_id, sub_service_id, business_custom_service_id
FROM business_appointment_rules
WHERE sub_service_id IS NOT NULL AND business_custom_service_id IS NOT NULL
UNION ALL
SELECT 'business_transfer_rules', id, business_id, sub_service_id, business_custom_service_id
FROM business_transfer_rules
WHERE sub_service_id IS NOT NULL AND business_custom_service_id IS NOT NULL
UNION ALL
SELECT 'business_escalation_rules', id, business_id, sub_service_id, business_custom_service_id
FROM business_escalation_rules
WHERE sub_service_id IS NOT NULL AND business_custom_service_id IS NOT NULL;
```

Confirm custom service references belong to the same business:

```sql
SELECT 'business_appointment_rules' AS table_name, r.id, r.business_id, r.business_custom_service_id, bcs.business_id AS custom_service_business_id
FROM business_appointment_rules r
INNER JOIN business_custom_services bcs ON bcs.id = r.business_custom_service_id
WHERE r.business_id <> bcs.business_id
UNION ALL
SELECT 'business_transfer_rules', r.id, r.business_id, r.business_custom_service_id, bcs.business_id
FROM business_transfer_rules r
INNER JOIN business_custom_services bcs ON bcs.id = r.business_custom_service_id
WHERE r.business_id <> bcs.business_id
UNION ALL
SELECT 'business_escalation_rules', r.id, r.business_id, r.business_custom_service_id, bcs.business_id
FROM business_escalation_rules r
INNER JOIN business_custom_services bcs ON bcs.id = r.business_custom_service_id
WHERE r.business_id <> bcs.business_id;
```

Expected state: both queries return no rows.

Confirm no duplicate notification types per business:

```sql
SELECT business_id, notification_type, COUNT(*) AS row_count
FROM business_notification_preferences
GROUP BY business_id, notification_type
HAVING COUNT(*) > 1;
```

Confirm no contradictory hours rows:

```sql
SELECT 'business_profile_hours' AS table_name, id, business_id, business_profile_id, day_of_week, NULL AS exception_date
FROM business_profile_hours
WHERE (is_closed = 1 AND (opens_at IS NOT NULL OR closes_at IS NOT NULL OR is_24_hours = 1))
   OR (is_24_hours = 1 AND (opens_at IS NOT NULL OR closes_at IS NOT NULL))
   OR (is_closed = 0 AND is_24_hours = 0 AND (opens_at IS NULL OR closes_at IS NULL))
UNION ALL
SELECT 'business_profile_hour_exceptions', id, business_id, business_profile_id, NULL, exception_date
FROM business_profile_hour_exceptions
WHERE (is_closed = 1 AND (opens_at IS NOT NULL OR closes_at IS NOT NULL OR is_24_hours = 1))
   OR (is_24_hours = 1 AND (opens_at IS NOT NULL OR closes_at IS NOT NULL))
   OR (is_closed = 0 AND is_24_hours = 0 AND (opens_at IS NULL OR closes_at IS NULL));
```

Check lifecycle distribution and invalid lifecycle values:

```sql
SELECT lifecycle_status, COUNT(*) AS row_count
FROM business_profiles
GROUP BY lifecycle_status
ORDER BY lifecycle_status;

SELECT id, business_id, lifecycle_status
FROM business_profiles
WHERE lifecycle_status NOT IN ('draft', 'in_review', 'ready', 'active', 'incomplete');
```

Check timezone null count:

```sql
SELECT
  COUNT(*) AS profile_count,
  SUM(CASE WHEN timezone IS NULL THEN 1 ELSE 0 END) AS timezone_null_count
FROM business_profiles;
```

Confirm foreign keys exist:

```sql
SELECT table_name, constraint_name, referenced_table_name, delete_rule
FROM information_schema.referential_constraints
WHERE constraint_schema = DATABASE()
  AND table_name IN (
    'business_profiles',
    'business_profile_hours',
    'business_profile_hour_exceptions',
    'business_profile_faqs',
    'business_profile_pricing_guidance',
    'business_appointment_rules',
    'business_transfer_rules',
    'business_escalation_rules',
    'business_notification_preferences'
  )
ORDER BY table_name, constraint_name;
```

Confirm expected indexes exist:

```sql
SELECT table_name, index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) AS columns_in_index, non_unique
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name IN (
    'business_profiles',
    'business_profile_hours',
    'business_profile_hour_exceptions',
    'business_profile_faqs',
    'business_profile_pricing_guidance',
    'business_appointment_rules',
    'business_transfer_rules',
    'business_escalation_rules',
    'business_notification_preferences'
  )
GROUP BY table_name, index_name, non_unique
ORDER BY table_name, index_name;
```

Confirm existing domain and LeadHub counts remain unchanged by comparing this output to the pre-migration count output:

```sql
SELECT 'businesses' AS table_name, COUNT(*) AS row_count FROM businesses
UNION ALL SELECT 'contacts', COUNT(*) FROM contacts
UNION ALL SELECT 'notes', COUNT(*) FROM notes
UNION ALL SELECT 'tasks', COUNT(*) FROM tasks
UNION ALL SELECT 'activity_logs', COUNT(*) FROM activity_logs
UNION ALL SELECT 'domain_requests', COUNT(*) FROM domain_requests
UNION ALL SELECT 'domain_assignments', COUNT(*) FROM domain_assignments
UNION ALL SELECT 'website_domains', COUNT(*) FROM website_domains
UNION ALL SELECT 'domain_dns_records', COUNT(*) FROM domain_dns_records
UNION ALL SELECT 'domain_events', COUNT(*) FROM domain_events;
```

## Tenant-Isolation Checks

Normal application reads and writes should always filter by `business_id` from the authorized business context. For profile child tables, use both keys when possible:

```sql
SELECT bp.*
FROM business_profiles bp
WHERE bp.business_id = ?;

SELECT h.*
FROM business_profile_hours h
INNER JOIN business_profiles bp ON bp.id = h.business_profile_id AND bp.business_id = h.business_id
WHERE h.business_id = ?;
```

## Roll-Forward Repair Guidance

If staging validation finds missing rows or bad backfill after `021` is applied, add a later repair migration. Do not edit migration `021` after staging applies it. Safe repair candidates are additive updates that create missing `business_profiles` rows, correct nullable draft fields, or add missing indexes/constraints. Do not delete existing LeadHub, domain, business, or 247SP records as part of a rollback.
