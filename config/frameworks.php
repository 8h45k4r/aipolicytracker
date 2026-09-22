<?php

// Editorial metadata for the standards and frameworks that obligations are crosswalked to.
// Keys match App\Models\FrameworkMapping::FRAMEWORKS. Descriptions are original summaries of
// what each document is and how it is structured; no clause text is reproduced from any
// copyrighted standard. Crosswalks cite clause numbers only.
return [
    'iso_42001' => [
        'slug' => 'iso-42001',
        'name' => 'ISO/IEC 42001:2023',
        'short' => 'ISO/IEC 42001',
        'publisher' => 'ISO and IEC',
        'published' => '2023',
        'certifiable' => true,
        'url' => 'https://www.iso.org/standard/81230.html',
        'summary' => 'A certifiable management system standard for artificial intelligence. It sets out what an organisation must put in place to govern the AI systems it develops or uses: scope, leadership, objectives, risk and impact assessment, operational controls, monitoring and improvement.',
        'structure' => 'Requirements sit in clauses 4 to 10, following the harmonised structure shared by other ISO management system standards. Annex A lists reference controls that an organisation selects from and justifies.',
        'unit' => 'Clause',
        // The written comparison that accompanies this crosswalk. The two pages target the
        // same question, so each links the other rather than competing unlinked.
        'guide' => ['slug' => 'iso-42001-vs-eu-ai-act', 'jurisdiction' => 'eu'],
        'why' => 'Because it is certifiable, ISO/IEC 42001 is what most organisations are audited against. Knowing which legal duties a clause already covers tells you how much of a statute your existing certification evidence reaches.',
    ],
    'nist_ai_rmf' => [
        'slug' => 'nist-ai-rmf',
        'name' => 'NIST AI Risk Management Framework 1.0',
        'short' => 'NIST AI RMF',
        'publisher' => 'National Institute of Standards and Technology',
        'published' => '2023',
        'certifiable' => false,
        'url' => 'https://www.nist.gov/itl/ai-risk-management-framework',
        'summary' => 'A voluntary framework for managing risk across the AI lifecycle. There is no certification against it; organisations adopt it as a common vocabulary for identifying, measuring and treating AI risk.',
        'structure' => 'Four functions - GOVERN, MAP, MEASURE and MANAGE - each broken into categories and subcategories. GOVERN runs throughout; the other three describe a cycle.',
        'unit' => 'Function',
        'guide' => ['slug' => 'nist-ai-rmf-vs-eu-ai-act', 'jurisdiction' => 'eu'],
        'why' => 'It is the reference point for US federal AI policy and for a growing number of procurement questionnaires, so a duty that maps cleanly to a function is one you can evidence in terms a US counterparty already uses.',
    ],
    'iso_27001' => [
        'slug' => 'iso-27001',
        'name' => 'ISO/IEC 27001:2022',
        'short' => 'ISO/IEC 27001',
        'publisher' => 'ISO and IEC',
        'published' => '2022',
        'certifiable' => true,
        'url' => 'https://www.iso.org/standard/27001',
        'summary' => 'A certifiable management system standard for information security. It predates the AI standards and is the control set most organisations already hold, which is why some AI duties - security, incident handling, access - land on it rather than on an AI-specific standard.',
        'structure' => 'Requirements in clauses 4 to 10, with Annex A reference controls grouped into organisational, people, physical and technological themes.',
        'unit' => 'Clause',
        'why' => 'Where an AI duty is really a security duty, existing ISO/IEC 27001 evidence may already satisfy it. This is the smallest crosswalk on the platform and is recorded for completeness rather than coverage.',
    ],
    'oecd_ai_principles' => [
        'slug' => 'oecd-ai-principles',
        'name' => 'OECD AI Principles',
        'short' => 'OECD AI Principles',
        'publisher' => 'Organisation for Economic Co-operation and Development',
        'published' => '2019, updated 2024',
        'certifiable' => false,
        'url' => 'https://oecd.ai/en/ai-principles',
        'summary' => 'An intergovernmental statement of values-based principles for trustworthy AI, plus recommendations addressed to governments. It is not an organisational control set, and it is the source much national AI policy language is drawn from.',
        'structure' => 'Values-based principles addressed to AI actors, and recommendations addressed to policymakers.',
        'unit' => 'Principle',
        'why' => 'Useful for tracing where a national strategy took its wording from, rather than for evidencing compliance.',
    ],
];
