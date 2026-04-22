<?php

/**
 * Configuration OAuth GitHub — valeurs chargées depuis .env (racine du projet)
 */

require_once __DIR__ . '/../env_loader.php';

return [
  'GITHUB_CLIENT_ID'     => env('GITHUB_CLIENT_ID', ''),
  'GITHUB_CLIENT_SECRET' => env('GITHUB_CLIENT_SECRET', ''),

  'GITHUB_CALLBACK_URL' => env('GITHUB_CALLBACK_URL', 'http://localhost/NxtGenAI/api/github/callback.php'),

  'GITHUB_SCOPES' => [
    'read:user',
    'user:email',
    'copilot',
  ],

  'GITHUB_AUTHORIZE_URL' => 'https://github.com/login/oauth/authorize',
  'GITHUB_TOKEN_URL'     => 'https://github.com/login/oauth/access_token',
  'GITHUB_API_URL'       => 'https://api.github.com',
];
