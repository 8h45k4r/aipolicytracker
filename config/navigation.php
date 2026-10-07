<?php

// The primary navigation, as data so the header, the mobile menu, the section hubs and the
// tests all read one source. Every entry is a route name plus optional parameters — no
// closures, because `config:cache` must be able to serialise this file (see
// ConfigIsCacheableTest).
//
// Each group's menu shows only the entries marked 'menu' => true (five to seven), then a
// link to the group's hub page (/explore/{group}), which lists every entry under its
// section heading. So the menu stays short and nothing becomes unreachable.
//
// Groups open as a <details> panel, which works with JavaScript disabled; the script only
// adds Escape-to-close and close-on-outside-click.
return [
    'primary' => [
        'policies' => [
            'label' => 'Policies',
            'summary' => 'Laws, duties, dates and what changed.',
            'sections' => [
                [
                    'heading' => 'Laws and duties',
                    'items' => [
                        ['route' => 'updates.index', 'label' => 'AI policy updates', 'note' => 'What is new, by day, month and country', 'menu' => true],
                        ['route' => 'policies.index', 'label' => 'Policy explorer', 'note' => 'Every recorded instrument, filterable', 'menu' => true],
                        ['route' => 'obligations.index', 'label' => 'Obligations', 'note' => 'What the rules actually require', 'menu' => true],
                        ['route' => 'controls.index', 'label' => 'Controls', 'note' => 'What you operate to meet them, and the evidence', 'menu' => true],
                        ['route' => 'compare.index', 'label' => 'Compare jurisdictions', 'note' => 'Two to four side by side', 'menu' => true],
                    ],
                ],
                [
                    'heading' => 'Dates and changes',
                    'items' => [
                        ['route' => 'calendar', 'label' => 'Deadline calendar', 'note' => 'Dated milestones, with a feed', 'menu' => true],
                        ['route' => 'deadlines.engine', 'label' => 'Which date applies to you?', 'note' => 'Five questions, a personal timeline', 'menu' => true],
                        ['route' => 'changes.index', 'label' => 'Change log', 'note' => 'What moved, and what it means'],
                        ['route' => 'state-of.show', 'label' => 'State of AI regulation', 'note' => 'The quarterly report, computed'],
                        ['route' => 'ai-policy-examples', 'label' => 'AI policy examples', 'note' => 'National AI policies, by region'],
                        ['route' => 'transition.index', 'label' => 'AI economic transition', 'note' => 'Dividends, basic income, AI taxes, layoff disclosure'],
                        ['route' => 'enforcement.index', 'label' => 'Enforcement tracker', 'note' => 'Fines, orders and court decisions on AI'],
                        ['route' => 'policies.implementation', 'params' => ['eu-ai-act'], 'label' => 'EU AI Act implementation', 'note' => 'Guidelines, codes and acts, with due dates'],
                        ['route' => 'standards.index', 'label' => 'AI standards', 'note' => 'CEN-CENELEC and ISO/IEC work, metadata only'],
                    ],
                ],
            ],
        ],

        'jurisdictions' => [
            'label' => 'Jurisdictions',
            'summary' => 'Country, regional and state pages.',
            'sections' => [
                [
                    'heading' => 'Regions',
                    'items' => [
                        ['route' => 'jurisdictions.index', 'label' => 'All jurisdictions', 'note' => 'Countries, regions and states', 'menu' => true],
                        ['route' => 'hubs.show', 'params' => ['ai-regulation-asia'], 'label' => 'Asia', 'note' => 'Regional hub, country by country', 'menu' => true],
                        ['route' => 'hubs.show', 'params' => ['ai-regulation-africa'], 'label' => 'Africa', 'note' => 'Regional hub', 'menu' => true],
                        ['route' => 'hubs.show', 'params' => ['ai-regulation-americas'], 'label' => 'Americas', 'note' => 'Regional hub', 'menu' => true],
                        ['route' => 'landing', 'params' => ['ai-regulation-south-asia'], 'label' => 'South Asia', 'note' => 'Regional overview'],
                    ],
                ],
                [
                    'heading' => 'Countries',
                    'items' => [
                        ['route' => 'policies.show', 'params' => ['eu-ai-act'], 'label' => 'EU AI Act', 'note' => 'The European Union', 'menu' => true],
                        ['route' => 'jurisdictions.show', 'params' => ['us'], 'label' => 'United States', 'note' => 'Federal and state', 'menu' => true],
                        ['route' => 'jurisdictions.show', 'params' => ['uk'], 'label' => 'United Kingdom', 'menu' => true],
                        ['route' => 'jurisdictions.show', 'params' => ['india'], 'label' => 'India'],
                        ['route' => 'jurisdictions.show', 'params' => ['singapore'], 'label' => 'Singapore'],
                        ['route' => 'jurisdictions.show', 'params' => ['australia'], 'label' => 'Australia'],
                        ['route' => 'landing', 'params' => ['ai-governance-uae'], 'label' => 'United Arab Emirates'],
                        ['route' => 'hubs.show', 'params' => ['ai-regulation-nepal'], 'label' => 'Nepal'],
                    ],
                ],
            ],
        ],

        'guides' => [
            'label' => 'Guides',
            'summary' => 'Walkthroughs, free templates and standards crosswalks.',
            'sections' => [
                [
                    'heading' => 'Guides and templates',
                    'items' => [
                        ['route' => 'templates.index', 'label' => 'Templates library', 'note' => 'XLSX and DOCX generated from the records', 'menu' => true],
                        ['route' => 'guides.index', 'label' => 'All guides', 'note' => 'Written walkthroughs, law by law', 'menu' => true],
                        ['route' => 'guides.show', 'params' => ['ai-startup-eu-ai-act-readiness'], 'label' => 'EU AI Act readiness for startups'],
                        ['route' => 'guides.show', 'params' => ['ai-governance-for-startups'], 'label' => 'AI governance for startups'],
                        ['route' => 'tools.applicability', 'label' => 'Applicability check', 'note' => 'Which duties may reach you', 'menu' => true],
                        ['route' => 'assessments.index', 'label' => 'Self-assessments', 'note' => 'Score yourself against a law or framework', 'menu' => true],
                    ],
                ],
                [
                    'heading' => 'By role and sector',
                    'items' => [
                        ['route' => 'audiences.index', 'label' => 'Start from who you are', 'note' => 'Duties, controls and evidence per audience', 'menu' => true],
                        ['route' => 'audiences.show', 'params' => ['deployers'], 'label' => 'Deployers'],
                        ['route' => 'audiences.show', 'params' => ['providers'], 'label' => 'Providers and developers'],
                        ['route' => 'audiences.show', 'params' => ['hr-and-recruitment'], 'label' => 'Hiring and HR'],
                        ['route' => 'audiences.show', 'params' => ['generative-ai'], 'label' => 'Generative AI'],
                    ],
                ],
                [
                    'heading' => 'Standards',
                    'items' => [
                        ['route' => 'frameworks.index', 'label' => 'Framework crosswalks', 'note' => 'Which legal duties map to which standard', 'menu' => true],
                        ['route' => 'frameworks.compare', 'label' => 'Compare frameworks', 'note' => 'What can be reused, computed', 'menu' => true],
                        ['route' => 'frameworks.show', 'params' => ['iso-42001'], 'label' => 'ISO/IEC 42001', 'note' => 'Duties grouped by clause'],
                        ['route' => 'frameworks.show', 'params' => ['nist-ai-rmf'], 'label' => 'NIST AI RMF', 'note' => 'Duties grouped by function'],
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
                        ['route' => 'risk.index', 'label' => 'Risk domains', 'note' => 'Seven domains, 24 subdomains', 'menu' => true],
                        ['route' => 'risk.incidents', 'label' => 'Incidents overview', 'note' => 'How recorded harms arise', 'menu' => true],
                        ['route' => 'risk.incidents.browse', 'label' => 'Browse incidents', 'note' => 'Filter and export', 'menu' => true],
                        ['route' => 'risk.risks', 'label' => 'Browse risk entries', 'note' => 'Filter and export', 'menu' => true],
                        ['route' => 'risk.frameworks', 'label' => 'Source frameworks', 'note' => 'The documents behind the taxonomy', 'menu' => true],
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
                        ['route' => 'open-data', 'label' => 'Open data', 'note' => 'Exports, licence and citation', 'menu' => true],
                        ['route' => 'open-data.health', 'label' => 'Data health', 'note' => 'How far the records can be trusted', 'menu' => true],
                    ],
                ],
                [
                    'heading' => 'For machines',
                    'items' => [
                        ['route' => 'api.v1.root', 'label' => 'REST API (v1)', 'note' => 'Read-only, no key required', 'menu' => true],
                        ['route' => 'openapi', 'label' => 'OpenAPI document', 'note' => 'The API, described', 'menu' => true],
                        ['route' => 'llms', 'label' => 'llms.txt', 'note' => 'What an assistant should read first', 'menu' => true],
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
                        ['route' => 'methodology', 'label' => 'Methodology', 'note' => 'How a record is built', 'menu' => true],
                        ['route' => 'verification', 'label' => 'Verification policy', 'note' => 'How old a fact may be', 'menu' => true],
                        ['route' => 'reviewers', 'label' => 'Reviewers', 'note' => 'Who checks what, and their interests', 'menu' => true],
                        ['route' => 'team', 'label' => 'People', 'note' => 'Maintainer, contributors and advisors'],
                        ['route' => 'about', 'label' => 'About', 'note' => 'Who runs the site and why', 'menu' => true],
                    ],
                ],
                [
                    'heading' => 'What is missing',
                    'items' => [
                        ['route' => 'coverage', 'label' => 'Coverage', 'note' => 'What a record must carry', 'menu' => true],
                        ['route' => 'gaps', 'label' => 'Open gaps', 'note' => 'The queue, in public'],
                        ['route' => 'corrections', 'label' => 'Corrections log', 'note' => 'What readers reported'],
                        ['route' => 'contribute', 'label' => 'Contribute a correction', 'note' => 'Report an error or a missing rule', 'menu' => true],
                    ],
                ],
            ],
        ],
    ],
];
