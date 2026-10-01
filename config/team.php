<?php

/*
|--------------------------------------------------------------------------
| People behind AIPolicyTracker
|--------------------------------------------------------------------------
|
| Rendered at /team. Every entry is a person who agreed to be listed; the
| bio is their own wording or wording they approved, and the links are the
| ones they chose. Affiliations identify, they do not endorse: the page says
| so in its own words, and nothing here should be read as a claim that an
| employer or institution stands behind the project.
|
| `photo` is a file under public/images/team, 400×400, named after the slug.
| `reviewer` links the card to the person's entry on the reviewer roster
| when they have one, so the two pages describe one person, not two.
|
*/

return [

    'core' => [
        [
            'slug' => 'bhaskar-bhatt',
            'name' => 'Bhaskar Bhatt',
            'role' => 'Maintainer',
            'bio' => 'Bhaskar leads platform direction, methodology, data-quality standards and the development of AIPolicyTracker. He is an AI governance and compliance practitioner and an ISO/IEC 42001 Lead Auditor.',
            'photo' => 'bhaskar-bhatt.jpg',
            'reviewer' => 'bhaskar-bhatt',
            'links' => [
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/8h45k4r/'],
                ['label' => 'Website', 'url' => 'https://bhaskar.com.np/'],
            ],
        ],
    ],

    'contributors' => [
        [
            'slug' => 'kailash-bohara',
            'name' => 'Kailash Bohara',
            'role' => 'AI Governance Research Contributor',
            'bio' => 'Kailash Bohara is a cybersecurity professional with 9+ years of experience spanning application security, penetration testing, cloud security, security architecture, GRC and DevSecOps. His interests and work also extend to the emerging fields of AI security and AI safety. He is the Chapter Leader of OWASP Kathmandu, where he actively contributes to the cybersecurity community through knowledge sharing, events and community initiatives.',
            'photo' => 'kailash-bohara.jpg',
            'links' => [
                ['label' => 'Website', 'url' => 'https://kailashbohara.com.np/'],
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/kailash0x01/'],
                ['label' => 'X (Twitter)', 'url' => 'https://x.com/corrupted_brain'],
            ],
        ],
        [
            'slug' => 'joyce-loksee-ho',
            'name' => 'Joyce Loksee Ho',
            'role' => 'AI Governance Research Contributor',
            'bio' => 'Joyce is a seasoned security expert focusing on cyber compliance and legal risks. She works as a Trust lead in a global software company and is a Master of Research candidate focusing on the intersection of business continuity, data privacy, and the integration of law and information technology to enhance Australian regulatory frameworks. She holds a Bachelor of Business and brings extensive professional experience in cybersecurity, including a role as a consulting lead for business continuity, where she advised clients in industries such as banking, energy and conglomerates on operational resilience. Her research interests blend her cybersecurity expertise with a passion for security and privacy frameworks, including AI legal regulations. Driven by the challenges of cyber threats and emerging technologies in the digital era, Joyce is committed to proposing regulatory enhancements and promoting cyber resilience across Australia.',
            'photo' => 'joyce-loksee-ho.jpg',
            'links' => [
                ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/joyce-loksee-ho-23526099'],
            ],
        ],
    ],

    // Advisors act in an independent capacity. Each entry: slug, name,
    // specialism, scope (one factual sentence), optional photo and links.
    'advisors' => [],

];
