<?php

namespace App\Modules\Premium\Support;

/**
 * What redeeming a code grants. Backed by the string in promo_codes.kind.
 *
 * One case in the MVP: a code opens the whole closed deck. Themed packs are also
 * premium but deferred with their content (canon §1 row 7), and they will arrive
 * as another case here — which is why this is an enum from day one and not a
 * boolean.
 */
enum PromoCodeKind: string
{
    case FullDeck = 'full_deck';
}
