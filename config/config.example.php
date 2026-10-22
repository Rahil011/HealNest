<?php

declare(strict_types=1);

/**
 * Copy to config.php and fill in Google OAuth values.
 * Redirect URI: {base_url}/admin/oauth-callback.php
 */
return [
    'admin_username' => 'admin',
    'admin_password' => 'change-this-password',

    'google_client_id' => 'YOUR_CLIENT_ID.apps.googleusercontent.com',
    'google_client_secret' => 'YOUR_CLIENT_SECRET',
    /**
     * Must match the URL you use in the browser (localhost vs 127.0.0.1 matters).
     * Add every variant under Google Cloud → Credentials → Authorized redirect URIs.
     */
    'google_redirect_uri' => 'http://localhost:8080/admin/oauth-callback.php',

    /** Google account allowed during one-time mail connect */
    'google_allowed_email' => 'your@gmail.com',

    'session_name' => 'healnest_admin_session',
    'app_base_path' => dirname(__DIR__),
];
