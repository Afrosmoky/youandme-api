<?php

namespace Youandme\Auth\Support;

use RuntimeException;

/**
 * A call to Apple's token endpoints failed. The message is safe to log by
 * construction: an HTTP status and Apple's `error` code at most, never a response
 * body, a token or the authorization code.
 */
final class AppleAuthException extends RuntimeException {}
