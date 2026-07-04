<?php

namespace Youandme\Auth\Support;

/**
 * Public API interface for verifying Apple ID tokens. Bound to the concrete
 * AppleTokenVerifier in AuthServiceProvider; injected into SignInWithAppleAction
 * and mocked in tests.
 */
interface AppleTokenVerifierInterface extends SocialTokenVerifier {}
