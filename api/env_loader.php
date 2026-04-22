<?php

/**
 * Chargeur de variables d'environnement (.env)
 *
 * Parse le fichier .env à la racine du projet et expose les valeurs
 * via $_ENV, getenv() et la fonction env(). Aucune dépendance externe.
 */

if (!function_exists('loadEnvFile')) {
  function loadEnvFile($path)
  {
    if (!is_file($path) || !is_readable($path)) {
      return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '' || $line[0] === '#') {
        continue;
      }

      $pos = strpos($line, '=');
      if ($pos === false) {
        continue;
      }

      $name = trim(substr($line, 0, $pos));
      $value = trim(substr($line, $pos + 1));

      // Retirer les guillemets englobants éventuels
      if (strlen($value) >= 2) {
        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
          $value = substr($value, 1, -1);
        }
      }

      // Ne pas écraser une variable déjà définie dans l'environnement réel
      if (getenv($name) === false) {
        putenv("{$name}={$value}");
      }
      if (!isset($_ENV[$name])) {
        $_ENV[$name] = $value;
      }
    }
    return true;
  }
}

if (!function_exists('env')) {
  function env($key, $default = null)
  {
    if (array_key_exists($key, $_ENV)) {
      $value = $_ENV[$key];
    } else {
      $value = getenv($key);
    }

    if ($value === false || $value === null) {
      return $default;
    }

    // Conversions de commodité pour les booléens stockés sous forme de chaîne
    $lower = strtolower((string)$value);
    if ($lower === 'true') return true;
    if ($lower === 'false') return false;
    if ($lower === 'null') return null;

    return $value;
  }
}

// Auto-chargement au premier require
loadEnvFile(__DIR__ . '/../.env');
