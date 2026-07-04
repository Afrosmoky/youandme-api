<?php

namespace Youandme\Auth\Support;

/**
 * Public API interface for verifying Google ID tokens. Bound to the concrete
 * GoogleTokenVerifier in AuthServiceProvider; injected into SignInWithGoogleAction
 * and mocked in tests.
 */
interface GoogleTokenVerifierInterface extends SocialTokenVerifier {}
