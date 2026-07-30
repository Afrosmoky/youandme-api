<?php

namespace App\Modules\Rewards\Exceptions;

use RuntimeException;

/**
 * Google's verifier keys could not be fetched, so a callback can be neither
 * confirmed nor denied.
 *
 * This is OUR outage, not a bad callback, and the difference matters: a verdict
 * of "invalid" is final and answers 200, which tells Google never to send this
 * one again — a genuine ad view would be paid for with nothing. Signalling the
 * failure instead lets the webhook answer 5xx and have the callback redelivered
 * once the keys are reachable.
 *
 * Deliberately without render(): the app-layer webhook decides the status code,
 * because the retry semantics belong to that endpoint, not to Rewards.
 */
final class AdMobKeysUnavailableException extends RuntimeException {}
