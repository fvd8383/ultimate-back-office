ALTER TABLE component_definitions
    DROP INDEX uq_component_definitions_key,
    ADD UNIQUE KEY uq_component_definitions_key_version (component_key, implementation_version);

INSERT INTO component_definitions (
    component_key, label, category, implementation_version,
    configuration_schema_version, status, metadata_json, created_at, updated_at
)
SELECT seeded.component_key, seeded.label, seeded.category, '1.0.0',
       1, 'active',
       JSON_OBJECT('scope', seeded.scope, 'authorable', TRUE, 'manifest_version', 1),
       NOW(), NOW()
FROM (
    SELECT 'hero' AS component_key, 'Hero' AS label, 'content' AS category, 'section' AS scope
    UNION ALL SELECT 'statistics', 'Statistics', 'content', 'section'
    UNION ALL SELECT 'service_grid', 'Service Grid', 'services', 'section'
    UNION ALL SELECT 'service_detail', 'Service Detail', 'services', 'section'
    UNION ALL SELECT 'trust_cards', 'Trust Cards', 'content', 'section'
    UNION ALL SELECT 'about_content', 'About Content', 'content', 'section'
    UNION ALL SELECT 'contact_content', 'Contact Content', 'content', 'section'
    UNION ALL SELECT 'cta', 'Call To Action', 'conversion', 'section'
    UNION ALL SELECT 'lead_form', 'Lead Form', 'conversion', 'section'
    UNION ALL SELECT 'pricing_list', 'Pricing List', 'content', 'section'
    UNION ALL SELECT 'faq', 'Frequently Asked Questions', 'content', 'section'
    UNION ALL SELECT 'text_block', 'Text Block', 'content', 'section'
    UNION ALL SELECT 'site_header', 'Site Header', 'layout', 'layout'
    UNION ALL SELECT 'site_footer', 'Site Footer', 'layout', 'layout'
    UNION ALL SELECT 'mobile_cta', 'Mobile CTA', 'layout', 'layout'
) seeded
LEFT JOIN component_definitions existing
  ON existing.component_key = seeded.component_key
 AND existing.implementation_version = '1.0.0'
WHERE existing.id IS NULL;

INSERT INTO component_variants (
    component_definition_id, variant_key, label, configuration_schema_version,
    status, metadata_json, created_at, updated_at
)
SELECT definition.id, seeded.variant_key, seeded.label, 1, 'active',
       JSON_OBJECT('manifest_version', 1), NOW(), NOW()
FROM (
    SELECT 'hero' AS component_key, 'default' AS variant_key, 'Default' AS label
    UNION ALL SELECT 'hero', 'split_media', 'Split Media'
    UNION ALL SELECT 'statistics', 'default', 'Default'
    UNION ALL SELECT 'service_grid', 'cards', 'Cards'
    UNION ALL SELECT 'service_detail', 'default', 'Default'
    UNION ALL SELECT 'trust_cards', 'default', 'Default'
    UNION ALL SELECT 'about_content', 'default', 'Default'
    UNION ALL SELECT 'contact_content', 'default', 'Default'
    UNION ALL SELECT 'cta', 'banner', 'Banner'
    UNION ALL SELECT 'cta', 'inline', 'Inline'
    UNION ALL SELECT 'lead_form', 'default', 'Default'
    UNION ALL SELECT 'pricing_list', 'link', 'Link'
    UNION ALL SELECT 'faq', 'accordion', 'Accordion'
    UNION ALL SELECT 'text_block', 'default', 'Default'
    UNION ALL SELECT 'site_header', 'standard', 'Standard'
    UNION ALL SELECT 'site_header', 'centered', 'Centered'
    UNION ALL SELECT 'site_footer', 'default', 'Default'
    UNION ALL SELECT 'mobile_cta', 'default', 'Default'
) seeded
INNER JOIN component_definitions definition
  ON definition.component_key = seeded.component_key
 AND definition.implementation_version = '1.0.0'
LEFT JOIN component_variants existing
  ON existing.component_definition_id = definition.id
 AND existing.variant_key = seeded.variant_key
WHERE existing.id IS NULL;
