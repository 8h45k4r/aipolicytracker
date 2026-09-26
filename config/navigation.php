<?php

// The primary navigation, as data so the header, the mobile menu and the tests all read one
// source. Every entry is a route name plus optional parameters — no closures, because
// `config:cache` must be able to serialise this file (see ConfigIsCacheableTest).
//
// Groups open as a <details> panel, which works with JavaScript disabled; the script only
// adds Escape-to-close and close-on-outside-click. Sections give a panel sub-headings so a
// long group stays readable instead of becoming a wall of links.
return [
    'primary' => [
        'policies' => [
            'label' => 'Policies',
            'summary' => 'Laws, duties, dates and what changed.',
            'sections' => [
                [
                    'heading' => 'Explore',
                    'items' => [
                        ['route' => 'updates.index', 'label' => 'AI policy updates', 'note' => 'What is new, by day, month and country'],
                        ['route' => 'policies.index', 'label' => 'Policy explorer', 'note' => 'Every recorded instrument, filterable'],
                        ['route' => 'obligations.index', 'label' => 'Obligations', 'note' => 'What the rules actually require'],
                        ['route' => 'controls.index', 'label' => 'Controls', 'note' => 'What you operate to meet them, and the evidence'],
                        ['route' => 'compare.index', 'label' => 'Compare jurisdictions', 'note' => 'Two to four side by side'],
                        ['route' => 'calendar', 'label' => 'Deadline calendar', 'note' => 'Dated milestones, with a feed'],
                        ['route' => 'deadlines.engine', 'label' => 'Which date applies to you?', 'note' => 'Five questions, a personal timeline'],
                        ['route' => 'changes.index', 'label' => 'Change log', 'note' => 'What moved, and what it means'],
                        ['route' => 'transition.index', 'label' => 'AI economic transition', 'note' => 'Dividends, basic income, AI taxes, layoff disclosure'],
                    ],
                ],
                [
                    'heading' => 'By jurisdiction',
                    'items' => [
                        ['route' => 'jurisdictions.index', 'label' => 'All jurisdictions', 'note' => 'Countries, regions and states'],
                        ['route' => 'hubs.show', 'params' => ['ai-regulation-asia'], 'label' => 'Asia', 'note' => 'Regional hub, country by country'],
                        ['route' => 'hubs.show', 'params' => ['ai-regulation-africa'], 'label' => 'Africa', 'note' => 'Regional hub'],
                        ['route' => 'hubs.show', 'params' => ['ai-regulation-americas'], 'label' => 'Americas', 'note' => 'Regional hub'],
                        ['route' => 'landing', 'params' => ['eu-ai-act'], 'label' => 'EU AI Act'],
                        ['route' => 'landing', 'params' => ['ai-regulation-uk'], 'label' => 'United Kingdom'],
                        ['route' => 'landing', 'params' => ['ai-regulation-usa'], 'label' => 'United States'],
                        ['route' => 'landing', 'params' => ['ai-regulation-india'], 'label' => 'India'],
                        ['route' => 'landing', 'params' => ['ai-governance-singapore'], 'label' => 'Singapore'],
                        ['route' => 'landing', 'params' => ['ai-regulation-australia'], 'label' => 'Australia'],
                        ['route' => 'landing', 'params' => ['ai-governance-uae'], 'label' => 'United Arab Emirates'],
                        ['route' => 'landing', 'params' => ['ai-policy-nepal'], 'label' => 'Nepal'],
                        ['route' => 'landing', 'params' => ['ai-regulation-south-asia'], 'label' => 'South Asia'],
                    ],
                ],
            ],
        ],

        'frameworks' => [
            'label' => 'Frameworks',
            'summary' => 'Which legal duties map to which standard.',
            'sections' => [
                [
                    'heading' => 'Standards',
                    'items' => [
                        ['route' => 'frameworks.index', 'label' => 'All crosswalks', 'note' => 'Coverage, framework by framework'],
                        ['route' => 'frameworks.compare', 'label' => 'Compare frameworks', 'note' => 'What can be reused, computed'],
                        ['route' => 'frameworks.show', 'params' => ['iso-42001'], 'label' => 'ISO/IEC 42001', 'note' => 'Duties grouped by clause'],
                        ['route' => 'frameworks.show', 'params' => ['nist-ai-rmf'], 'label' => 'NIST AI RMF', 'note' => 'Duties grouped by function'],
                    ],
                ],
                [
                    'heading' => 'Law to standard',
                    'items' => [
                        ['route' => 'frameworks.crosswalk', 'params' => ['iso-42001', 'eu'], 'label' => 'EU AI Act to ISO/IEC 42001', 'note' => 'Clause by clause'],
                        ['route' => 'frameworks.crosswalk', 'params' => ['nist-ai-rmf', 'eu'], 'label' => 'EU AI Act to NIST AI RMF', 'note' => 'Function by function'],
                        ['route' => 'guides.show', 'params' => ['iso-42001-vs-eu-ai-act'], 'label' => 'ISO 42001 vs the EU AI Act', 'note' => 'The written comparison'],
                        ['route' => 'guides.show', 'params' => ['nist-ai-rmf-vs-eu-ai-act'], 'label' => 'NIST AI RMF vs the EU AI Act', 'note' => 'The written comparison'],
                    ],
                ],
            ],
        ],

        'risk' => [
            'label' => 'AI risk',
            'summary' => 'Recorded harms and the taxonomies behind them.',
            'sections' => [
                [
                    'heading' => 'Explore',
                    'items' => [
                        ['route' => 'risk.index', 'label' => 'Risk domains', 'note' => 'Seven domains, 24 subdomains'],
                        ['route' => 'risk.incidents', 'label' => 'Incidents overview', 'note' => 'How recorded harms arise'],
                        ['route' => 'risk.incidents.browse', 'label' => 'Browse incidents', 'note' => 'Filter and export'],
                        ['route' => 'risk.risks', 'label' => 'Browse risk entries', 'note' => 'Filter and export'],
                        ['route' => 'risk.frameworks', 'label' => 'Source frameworks', 'note' => 'The documents behind the taxonomy'],
                    ],
                ],
            ],
        ],

        'guides' => [
            'label' => 'Guides',
            'summary' => 'Written walkthroughs and free templates.',
            'sections' => [
                [
                    'heading' => 'Guides',
                    'items' => [
                        ['route' => 'templates.index', 'label' => 'Templates library', 'note' => 'XLSX and DOCX generated from the records'],
                        ['route' => 'guides.index', 'label' => 'All guides'],
                        ['route' => 'guides.show', 'params' => ['ai-startup-eu-ai-act-readiness'], 'label' => 'EU AI Act readiness for startups'],
                        ['route' => 'guides.show', 'params' => ['ai-governance-for-startups'], 'label' => 'AI governance for startups'],
                    ],
                ],
                [
                    'heading' => 'By role and sector',
                    'items' => [
                        ['route' => 'audiences.index', 'label' => 'Start from who you are', 'note' => 'Duties, controls and evidence per audience'],
                        ['route' => 'audiences.show', 'params' => ['deployers'], 'label' => 'Deployers'],
                        ['route' => 'audiences.show', 'params' => ['providers'], 'label' => 'Providers and developers'],
                        ['route' => 'audiences.show', 'params' => ['hr-and-recruitment'], 'label' => 'Hiring and HR'],
                        ['route' => 'audiences.show', 'params' => ['generative-ai'], 'label' => 'Generative AI'],
                        ['route' => 'tools.applicability', 'label' => 'Applicability check', 'note' => 'Which duties may reach you'],
                    ],
                ],
            ],
        ],

        'data' => [
            'label' => 'Data & API',
            'summary' => 'Every record, machine readable and openly licensed.',
            'sections' => [
                [
                    'heading' => 'For people',
                    'items' => [
                        ['route' => 'open-data', 'label' => 'Open data', 'note' => 'Exports, licence and citation'],
                    ],
                ],
                [
                    'heading' => 'For machines',
                    'items' => [
                        ['route' => 'api.v1.root', 'label' => 'REST API (v1)', 'note' => 'Read-only, no key required'],
                        ['route' => 'openapi', 'label' => 'OpenAPI document'],
                        ['route' => 'llms', 'label' => 'llms.txt', 'note' => 'What an assistant should read first'],
                        ['route' => 'open-data.health', 'label' => 'Data health', 'note' => 'How far the records can be trusted'],
                    ],
                ],
            ],
        ],

        'trust' => [
            'label' => 'How we work',
            'summary' => 'Sources, review state and what is still missing.',
            'sections' => [
                [
                    'heading' => 'Method',
                    'items' => [
                        ['route' => 'methodology', 'label' => 'Methodology', 'note' => 'How a record is built'],
                        ['route' => 'verification', 'label' => 'Verification policy', 'note' => 'How old a fact may be'],
                        ['route' => 'reviewers', 'label' => 'Reviewers', 'note' => 'Who checks what, and their interests'],
                    ],
                ],
                [
                    'heading' => 'What is missing',
                    'items' => [
                        ['route' => 'coverage', 'label' => 'Coverage', 'note' => 'What a record must carry'],
                        ['route' => 'gaps', 'label' => 'Open gaps', 'note' => 'The queue, in public'],
                        ['route' => 'corrections', 'label' => 'Corrections log', 'note' => 'What readers reported'],
                        ['route' => 'contribute', 'label' => 'Contribute a correction'],
                        ['route' => 'about', 'label' => 'About'],
                    ],
                ],
            ],
        ],
    ],
];
