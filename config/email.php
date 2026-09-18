<?php

/*
|--------------------------------------------------------------------------
| Address quality for anything that creates an account or a contact record
|--------------------------------------------------------------------------
|
| A throwaway address costs the project twice: the reader never receives the
| change alert or the template they asked for, and the download and subscriber
| figures quoted on the site stop describing real people. Work and personal
| mailboxes are both welcome; an inbox that expires in ten minutes is not.
|
| Enforcement is off under `testing`, set explicitly in phpunit.xml rather than
| derived from APP_ENV here. Every other suite is about its own subject and uses
| addresses at RFC 2606 reserved domains, which have no MX record by design and
| would be refused. The suite that is about this policy switches it on and
| supplies its own resolver.
|
| Deriving the default from APP_ENV was tried and was wrong: `.env.example` sets
| this key, CI copies that file to `.env`, and an explicit value in the
| environment beats any default a config file can express. The test environment
| must state what it wants, not infer it.
|
*/

return [

    // Master switch. Off means the rule passes everything, so a site can be
    // brought up or an incident contained without editing validation rules.
    'enforce' => (bool) env('EMAIL_DOMAIN_ENFORCEMENT', true),

    // Look the domain up in DNS and refuse one that cannot receive mail at all.
    // This catches more throwaway traffic than any blocklist: roughly a third of
    // known throwaway domains have no mail route at the moment they are offered.
    'check_deliverability' => (bool) env('EMAIL_CHECK_DELIVERABILITY', true),

    // Resolved once per domain and kept this long. A sign-up form is not the
    // place to pay for a DNS round trip on every keystroke of retried input.
    'cache_ttl' => (int) env('EMAIL_DOMAIN_CACHE_TTL', 86400),

    // Proof that the resolver itself is working. When a domain looks unreachable
    // this name is resolved as a control; if it is unreachable too, the resolver
    // is down and every address is allowed through rather than refusing the
    // whole world's sign-ups because of a local outage.
    'canary_domain' => env('EMAIL_RESOLVER_CANARY', 'gmail.com'),

    // Curated lists, checked in this order: trusted wins outright.
    'lists' => [
        'trusted' => base_path('data/email/trusted-domains.txt'),
        'disposable' => base_path('data/email/disposable-domains.txt'),
        'mail_hosts' => base_path('data/email/disposable-mail-hosts.txt'),
    ],

    // Written by `php artisan email:domains-refresh`, merged on top of the
    // curated list. Kept outside the repository so an operator can react to a
    // new throwaway service without a deploy, and so the project never
    // redistributes a third-party list under an unexamined licence.
    'overlay' => storage_path('app/email/disposable-domains.txt'),

    // Reserved by RFC 2606 and RFC 6761 for documentation and testing. No real
    // person is reachable at one, so they are refused wherever the policy runs.
    'reserved' => [
        'example.com', 'example.net', 'example.org', 'example.edu',
        'test', 'example', 'invalid', 'localhost', 'local', 'localdomain',
    ],
];
