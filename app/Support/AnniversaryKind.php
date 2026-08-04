<?php

namespace App\Support;

/**
 * Which anniversary of a memory came round today. Also the suffix of the push copy
 * key (lang/pl/push.php) and the value the client sees in the data payload, so the
 * three never drift apart.
 */
enum AnniversaryKind: string
{
    case Year = 'year';
    case Month = 'month';
}
