<?php

declare(strict_types=1);

/*
 | Push copy. Working texts — Wiktoria signs them off, and this is the one file
 | she has to touch (P9). Read with an explicit locale, so a missing APP_LOCALE
 | (phpunit.xml does not set one) can never turn a push into a translation key.
 |
 | The yearly lines are plural forms for trans_choice; the monthly ones have a
 | single form and ignore the count.
 */

return [
    'memory_anniversary' => [
        'year' => [
            'title' => '{1} Rok temu|[2,4] :count lata temu|[5,*] :count lat temu',
            'body' => '{1} Rok temu zapisaliście wspomnienie. Zajrzyjcie, co wtedy napisaliście.'
                .'|[2,4] :count lata temu zapisaliście wspomnienie. Zajrzyjcie, co wtedy napisaliście.'
                .'|[5,*] :count lat temu zapisaliście wspomnienie. Zajrzyjcie, co wtedy napisaliście.',
        ],
        'month' => [
            'title' => 'Miesiąc temu',
            'body' => 'Miesiąc temu zapisaliście wspomnienie. Zajrzyjcie, co wtedy napisaliście.',
        ],
    ],
];
