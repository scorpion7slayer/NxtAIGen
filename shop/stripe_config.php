<?php

/**
 * Configuration Stripe — valeurs chargées depuis .env (racine du projet)
 */

require_once __DIR__ . '/../api/env_loader.php';

define('STRIPE_SECRET_KEY',      env('STRIPE_SECRET_KEY', ''));
define('STRIPE_PUBLISHABLE_KEY', env('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_WEBHOOK_SECRET',  env('STRIPE_WEBHOOK_SECRET', ''));

define('STRIPE_PRICE_BASIC',   env('STRIPE_PRICE_BASIC', ''));
define('STRIPE_PRICE_PREMIUM', env('STRIPE_PRICE_PREMIUM', ''));
define('STRIPE_PRICE_ULTRA',   env('STRIPE_PRICE_ULTRA', ''));
