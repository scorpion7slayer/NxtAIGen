<?php

/**
 * Test OAuth GitHub - Diagnostic
 * Accédez à cette page pour tester la configuration OAuth
 */

session_start();

$config = require __DIR__ . '/config.php';

echo "<h1>Test OAuth GitHub</h1>";
echo "<pre>";

// 1. Vérifier la configuration
echo "=== CONFIGURATION ===\n";
echo "Client ID: " . (strlen($config['GITHUB_CLIENT_ID']) > 10 ? substr($config['GITHUB_CLIENT_ID'], 0, 10) . '...' : 'MANQUANT') . "\n";
echo "Client Secret: " . (strlen($config['GITHUB_CLIENT_SECRET']) > 10 ? 'Configuré (' . strlen($config['GITHUB_CLIENT_SECRET']) . ' chars)' : 'MANQUANT') . "\n";
echo "Callback URL: " . $config['GITHUB_CALLBACK_URL'] . "\n\n";

// 2. Vérifier cURL
echo "=== CURL ===\n";
echo "cURL activé: " . (function_exists('curl_init') ? 'OUI' : 'NON') . "\n";
echo "Version: " . (function_exists('curl_version') ? curl_version()['version'] : 'N/A') . "\n";
echo "SSL: " . (function_exists('curl_version') ? curl_version()['ssl_version'] : 'N/A') . "\n\n";

// 3. Tester la connexion à GitHub
echo "=== TEST CONNEXION GITHUB ===\n";
$ch = curl_init('https://github.com');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_NOBODY => true,
  CURLOPT_TIMEOUT => 10,
  CURLOPT_SSL_VERIFYPEER => true,
]);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
// curl_close() supprimé - deprecated depuis PHP 8.0
echo "Connexion github.com: " . ($httpCode > 0 ? "OK (HTTP $httpCode)" : "ÉCHEC - $error") . "\n\n";

// 4. Tester l'endpoint OAuth
echo "=== TEST ENDPOINT TOKEN ===\n";
$ch = curl_init($config['GITHUB_TOKEN_URL']);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_NOBODY => true,
  CURLOPT_TIMEOUT => 10,
  CURLOPT_SSL_VERIFYPEER => true,
]);
$result = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
// curl_close() supprimé - deprecated depuis PHP 8.0
echo "Connexion " . $config['GITHUB_TOKEN_URL'] . ": " . ($httpCode > 0 ? "OK (HTTP $httpCode)" : "ÉCHEC - $error") . "\n\n";

// 5. Test avec un faux code (pour vérifier que l'endpoint répond)
echo "=== TEST ÉCHANGE TOKEN (avec faux code) ===\n";
$testData = [
  'client_id' => $config['GITHUB_CLIENT_ID'],
  'client_secret' => $config['GITHUB_CLIENT_SECRET'],
  'code' => 'test_fake_code_12345',
  'redirect_uri' => $config['GITHUB_CALLBACK_URL'],
];

$ch = curl_init($config['GITHUB_TOKEN_URL']);
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => http_build_query($testData),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Accept: application/json',
    'Content-Type: application/x-www-form-urlencoded',
    'User-Agent: NxtGenAI'
  ],
  CURLOPT_SSL_VERIFYPEER => true,
  CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
// curl_close() supprimé - deprecated depuis PHP 8.0

echo "HTTP Code: $httpCode\n";
if ($curlError) {
  echo "Erreur cURL: $curlError\n";
} else {
  $decoded = json_decode($response, true);
  echo "Réponse GitHub: " . json_encode($decoded, JSON_PRETTY_PRINT) . "\n";

  if (isset($decoded['error']) && $decoded['error'] === 'bad_verification_code') {
    echo "\n[OK] L'endpoint fonctionne correctement ! L'erreur 'bad_verification_code' est normale car le code est faux.\n";
  }
}

echo "\n=== SESSION ===\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Non connecté') . "\n";
echo "OAuth State: " . ($_SESSION['github_oauth_state'] ?? 'Non défini') . "\n";

echo "</pre>";

echo "<h2>Liens de test</h2>";
echo "<ul>";
echo "<li><a href='connect.php'>Démarrer OAuth GitHub</a></li>";
echo "<li><a href='../../zone_membres/dashboard.php'>Dashboard</a></li>";
echo "</ul>";
