<?php

/**
 * Configuration OAuth GitHub
 * 
 * ÉTAPES POUR CONFIGURER :
 * 1. Allez sur https://github.com/settings/developers
 * 2. Cliquez sur "New OAuth App"
 * 3. Remplissez :
 *    - Application name: NxtGenAI
 *    - Homepage URL: http://localhost/NxtGenAI
 *    - Authorization callback URL: http://localhost/NxtGenAI/api/github/callback.php
 * 4. Copiez le Client ID et générez un Client Secret
 * 5. Collez-les ci-dessous
 */

return [
  // === CONFIGURATION OAUTH GITHUB ===
  'GITHUB_CLIENT_ID' => 'Ov23lix9T37DO12FaqdR',      // Votre Client ID GitHub
  'GITHUB_CLIENT_SECRET' => '6b835ec5dc80d5d1a0baf50c2ee1ea330f582a82',  // Votre Client Secret GitHub

  // URL de callback (doit correspondre à celle configurée sur GitHub)
  'GITHUB_CALLBACK_URL' => 'http://localhost/NxtGenAI/api/github/callback.php',

  // Scopes demandés (permissions)
  // https://docs.github.com/en/developers/apps/building-oauth-apps/scopes-for-oauth-apps
  'GITHUB_SCOPES' => [
    'read:user',           // Lire le profil utilisateur
    'user:email',          // Lire l'email
    // Ajoutez d'autres scopes si nécessaire pour GitHub Models API
  ],

  // URLs OAuth GitHub
  'GITHUB_AUTHORIZE_URL' => 'https://github.com/login/oauth/authorize',
  'GITHUB_TOKEN_URL' => 'https://github.com/login/oauth/access_token',
  'GITHUB_API_URL' => 'https://api.github.com',
];
