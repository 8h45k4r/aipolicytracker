<?php

/*
| AI policy news from official sources only (docs/modules/news.md). Each item is a headline,
| a date, the publisher and a link to the publisher's own page; no article text is copied or
| shown. A source is listed only after its feed was fetched and parsed (11-12 November 2026).
| Broad feeds keep only items whose title or summary is about AI (`relevance`).
*/

return [
    'enabled' => (bool) env('NEWS_SYNC', true),
    'timeout' => 20,
    // Items older than this are not shown and are pruned.
    'keep_days' => 365,

    'sources' => [
        'nist' => ['name' => 'NIST news', 'publisher' => 'US National Institute of Standards and Technology', 'jurisdiction' => 'us', 'format' => 'rss', 'ai_only' => true,
            'url' => 'https://www.nist.gov/news-events/news/rss.xml'],
        'uk-aisi' => ['name' => 'UK AI Security Institute', 'publisher' => 'AI Security Institute (GOV.UK)', 'jurisdiction' => 'uk', 'format' => 'atom', 'ai_only' => false,
            'url' => 'https://www.gov.uk/government/organisations/ai-security-institute.atom'],
        'uk-dsit' => ['name' => 'UK Department for Science, Innovation and Technology', 'publisher' => 'DSIT (GOV.UK)', 'jurisdiction' => 'uk', 'format' => 'atom', 'ai_only' => true,
            'url' => 'https://www.gov.uk/government/organisations/department-for-science-innovation-and-technology.atom'],
        'uk-govuk-ai' => ['name' => 'GOV.UK news on artificial intelligence', 'publisher' => 'GOV.UK', 'jurisdiction' => 'uk', 'format' => 'atom', 'ai_only' => true,
            'url' => 'https://www.gov.uk/search/news-and-communications.atom?keywords=artificial+intelligence'],
        'eu-digital-strategy' => ['name' => 'Shaping Europe\'s digital future', 'publisher' => 'European Commission', 'jurisdiction' => 'eu', 'format' => 'rss', 'ai_only' => true,
            'url' => 'https://digital-strategy.ec.europa.eu/en/rss.xml'],
        'federal-register' => ['name' => 'Federal Register', 'publisher' => 'US Office of the Federal Register', 'jurisdiction' => 'us', 'format' => 'federal_register', 'ai_only' => true,
            'url' => 'https://www.federalregister.gov/api/v1/documents.json'],
    ],

    // An item belongs on the page when its title is about AI, or its summary uses one of the
    // specific phrases in `relevance_summary` (a passing "AI" in a long summary is not enough).
    'relevance_summary' => '/artificial intelligence|machine learning|generative AI|\bAI (Act|system|model|safety|security|governance|regulation|strategy|policy)/i',
    'relevance' => '/artificial intelligence|\bA\.?I\.?\b|machine learning|automated decision|algorithmic|generative model|large language model|frontier model|chatbot|deepfake/i',

    // Topic tags, matched against title and summary. An item may carry several.
    'topics' => [
        'policy' => ['label' => 'Policy and law', 'pattern' => '/\b(act|bill|law|legislation|regulat\w*|rule|directive|code of practice|guidance|policy)\b/i'],
        'safety' => ['label' => 'Safety and security', 'pattern' => '/\b(safety|safe|security|secure|evaluation|red[- ]team\w*|misuse|cyber\w*)\b/i'],
        'governance' => ['label' => 'Governance', 'pattern' => '/\b(governance|oversight|accountab\w*|audit\w*|standard\w*|assurance)\b/i'],
        'responsible_ai' => ['label' => 'Responsible AI', 'pattern' => '/\b(responsible|ethic\w*|trustworth\w*|fairness|bias|human rights|fundamental rights|transparen\w*)\b/i'],
        'strategy' => ['label' => 'Strategy and investment', 'pattern' => '/\b(strateg\w*|plan|roadmap|invest\w*|funding|compute|skills|adoption)\b/i'],
        'risk' => ['label' => 'Risk', 'pattern' => '/\b(risks?|harms?|threats?)\b/i'],
        'incidents' => ['label' => 'Incidents', 'pattern' => '/\b(incidents?|breach\w*|failure|enforcement|fine[sd]?|penalt\w*)\b/i'],
    ],
];
