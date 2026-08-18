<?php

return [
    'brand' => [
        'name' => '24/7 Sales Partner',
        'short_name' => '247SP',
        'canonical_url' => 'https://247salespartner.com/',
        'description' => 'A connected lead generation and lead capture system for local service businesses.',
        'logo' => '/assets/brand/247sp-logo.svg',
        'favicon' => '/assets/brand/favicon.svg',
        'social_image' => '/assets/img/og-247sp.png',
    ],
    'signup' => [
        'url' => 'https://accounts.ultimatebackoffice.com/signup.php?product=247sp',
        'label' => 'Get Started with 24/7 Sales Partner',
        'microcopy' => 'Secure signup powered by Ultimate Back Office.',
        'ecosystem_copy' => '24/7 Sales Partner is part of the Ultimate Back Office ecosystem.',
    ],
    'homepage' => [
        'eyebrow' => 'Lead capture for local service businesses',
        'headline' => 'Never Miss Another Lead.',
        'supporting_copy' => '24/7 Sales Partner gives your service business a professional website, business phone, AI receptionist, text and website chat, business email, and lead management—all working together to help capture opportunities 24/7.',
        'problem_headline' => "Your next customer isn't waiting for you to finish the job you're on.",
        'solution_headline' => "Your business can respond even when you can't.",
        'final_headline' => 'Your next customer could be looking for you right now.',
    ],
    'promoted_pricing' => [
        // Marketing presentation only. Cohort assignment remains server-side at completed business signup.
        'cohort_key' => 'alpha',
        'name' => 'Alpha',
        'audience' => 'Customers #1–5',
        'setup_price' => '$0',
        'setup_label' => 'setup',
        'intro_price' => 'First 6 calendar months free',
        'monthly_price' => '$79/month',
        'monthly_label' => 'afterward',
        'disclaimer' => 'Early-customer pricing is subject to availability and is assigned when your 24/7 Sales Partner business signup is completed.',
    ],
    'vsl' => [
        // Replace these values when approved product footage is ready.
        'video_url' => null,
        'poster_image' => null,
        'placeholder' => true,
        'placeholder_label' => 'Product demo video coming soon',
    ],
    'support' => [
        // Final public support details are pending approval.
        'email' => null,
        'account_url' => 'https://accounts.ultimatebackoffice.com/',
    ],
    'legal' => [
        'privacy_url' => '/privacy.php',
        'terms_url' => '/terms.php',
        'contact_url' => '/contact.php',
    ],
    'analytics_events' => [
        'page_view' => 'marketing_page_view',
        'vsl_play' => 'marketing_vsl_play',
        'vsl_complete' => 'marketing_vsl_complete',
        'primary_cta' => 'marketing_primary_cta_click',
        'pricing_cta' => 'marketing_pricing_cta_click',
        'faq_engagement' => 'marketing_faq_engagement',
        'signup_initiation' => 'marketing_signup_initiation',
    ],
    'features' => [
        ['key' => 'website', 'label' => 'Professionally Built Website', 'description' => 'You supply the business information. We build a professional, conversion-focused website designed to create trust and generate inquiries.', 'featured' => true],
        ['key' => 'phone', 'label' => 'Business Phone', 'description' => 'A professional business number connected to your 247SP communication system, with transfer options available where supported.'],
        ['key' => 'receptionist', 'label' => 'AI Receptionist', 'description' => 'Coverage when you are unavailable. It can answer incoming calls, understand what a prospect needs, and gather information for your follow-up.'],
        ['key' => 'text', 'label' => 'Business Text Messaging', 'description' => 'Give prospects a convenient way to contact your business and keep lead-related messages connected to the opportunity.'],
        ['key' => 'chat', 'label' => 'Website Chat', 'description' => 'Let website visitors begin a conversation without having to pick up the phone.'],
        ['key' => 'email', 'label' => 'Business Email', 'description' => 'Build a consistent, professional customer-facing presence with business email included in the system.'],
        ['key' => 'leadhub', 'label' => 'LeadHub', 'description' => "One place to see who's interested, what they need, how they contacted you, and what happens next.", 'featured' => true],
    ],
    'industries' => ['Plumbers', 'HVAC contractors', 'Electricians', 'Landscapers', 'Cleaners', 'Roofers', 'Painters', 'Handymen', 'Detailers', 'Other local service businesses'],
    'faq' => [
        ['question' => 'Is 24/7 Sales Partner just a website?', 'answer' => 'No. The website is one part of a connected system that helps prospects find, contact, and interact with your business while keeping those opportunities organized in LeadHub.'],
        ['question' => 'Do I have to build the website myself?', 'answer' => 'No. You supply information about your business and services. 24/7 Sales Partner is intended to provide a professionally built website around that information.'],
        ['question' => "What happens when somebody calls and I can't answer?", 'answer' => 'The AI receptionist is designed to provide coverage when you are unavailable by answering the incoming call, understanding what the prospect needs, and gathering information for your follow-up.'],
        ['question' => 'Can I still answer my own calls?', 'answer' => 'Yes. The AI receptionist is intended to provide coverage rather than prevent you from handling calls personally. Exact routing depends on the supported phone setup for your business.'],
        ['question' => 'Can customers text my business?', 'answer' => 'Yes. Business text messaging gives prospects another convenient way to contact you while keeping lead-related communication connected to the 247SP system.'],
        ['question' => 'What is LeadHub?', 'answer' => "LeadHub is the lead-management center of 247SP—one place to see who's interested, what they need, how they contacted you, and what happens next."],
        ['question' => 'Can I keep my current business phone number?', 'answer' => 'Number transfer or porting may be available where supported. Eligibility and timing depend on the current number and carrier, so those details are confirmed for each business.'],
        ['question' => 'Why does signup take me to Ultimate Back Office?', 'answer' => '24/7 Sales Partner is part of the Ultimate Back Office ecosystem. Ultimate Back Office securely manages your account, subscription, and access to the tools that power 24/7 Sales Partner.'],
        ['question' => 'Do I need any technical experience?', 'answer' => 'No. 247SP is designed for service-business owners, not developers or marketers. You provide the business information; the connected system and team handle the technical setup.'],
        ['question' => 'Can I cancel?', 'answer' => 'Cancellation requests are currently reviewed with support. Final public cancellation terms are pending legal and commercial review and will be published before launch.', 'placeholder' => true],
    ],
];
