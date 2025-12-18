<?php

/**
 * Fonctions d'aide pour le traitement des fichiers dans les APIs
 */

/**
 * Prépare le contenu du message au format OpenAI (utilisé par OpenAI, Mistral, DeepSeek, xAI, etc.)
 * @param string $userMessage Le message texte de l'utilisateur
 * @param array $files Les fichiers attachés
 * @return array|string Le contenu formaté pour l'API
 */
function prepareOpenAIMessageContent($userMessage, $files = [])
{
  // Si pas de fichiers, retourner le message simple
  if (empty($files)) {
    return $userMessage ?: 'Bonjour';
  }

  $messageContent = [];

  // Ajouter le texte si présent
  if (!empty($userMessage)) {
    $messageContent[] = [
      'type' => 'text',
      'text' => $userMessage
    ];
  }

  // Ajouter les images si présentes
  $supportedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  foreach ($files as $file) {
    if (in_array($file['type'], $supportedImageTypes)) {
      $messageContent[] = [
        'type' => 'image_url',
        'image_url' => [
          'url' => $file['data'], // data:image/xxx;base64,... format
          'detail' => 'auto'
        ]
      ];
    } else {
      // Pour les fichiers non-image, ajouter le contenu comme texte
      if (
        strpos($file['type'], 'text/') === 0 ||
        $file['type'] === 'application/json' ||
        $file['type'] === 'application/xml'
      ) {
        $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
        $fileContent = base64_decode($base64Data);
        $messageContent[] = [
          'type' => 'text',
          'text' => "Contenu du fichier {$file['name']}:\n```\n{$fileContent}\n```"
        ];
      }
    }
  }

  // Si aucun contenu, ajouter un message par défaut
  if (empty($messageContent)) {
    return $userMessage ?: 'Analyse ce fichier';
  }

  return $messageContent;
}

/**
 * Prépare le contenu du message au format Anthropic
 * @param string $userMessage Le message texte de l'utilisateur
 * @param array $files Les fichiers attachés
 * @return array Le contenu formaté pour l'API Anthropic
 */
function prepareAnthropicMessageContent($userMessage, $files = [])
{
  $messageContent = [];
  $supportedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  // Ajouter les images d'abord (Anthropic préfère les images avant le texte)
  foreach ($files as $file) {
    if (in_array($file['type'], $supportedImageTypes)) {
      $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
      $messageContent[] = [
        'type' => 'image',
        'source' => [
          'type' => 'base64',
          'media_type' => $file['type'],
          'data' => $base64Data
        ]
      ];
    } else {
      // Pour les fichiers non-image, ajouter le contenu comme texte
      if (
        strpos($file['type'], 'text/') === 0 ||
        $file['type'] === 'application/json' ||
        $file['type'] === 'application/xml'
      ) {
        $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
        $fileContent = base64_decode($base64Data);
        $messageContent[] = [
          'type' => 'text',
          'text' => "Contenu du fichier {$file['name']}:\n```\n{$fileContent}\n```"
        ];
      }
    }
  }

  // Ajouter le texte à la fin
  if (!empty($userMessage)) {
    $messageContent[] = [
      'type' => 'text',
      'text' => $userMessage
    ];
  } else if (empty($messageContent)) {
    $messageContent[] = [
      'type' => 'text',
      'text' => 'Analyse ce fichier'
    ];
  }

  return $messageContent;
}

/**
 * Prépare le contenu du message au format Gemini
 * @param string $userMessage Le message texte de l'utilisateur
 * @param array $files Les fichiers attachés
 * @return array Les parts formatées pour l'API Gemini
 */
function prepareGeminiParts($userMessage, $files = [])
{
  $parts = [];
  $supportedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  // Ajouter les images si présentes
  foreach ($files as $file) {
    if (in_array($file['type'], $supportedImageTypes)) {
      $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
      $parts[] = [
        'inlineData' => [
          'mimeType' => $file['type'],
          'data' => $base64Data
        ]
      ];
    } else {
      // Pour les fichiers non-image, ajouter le contenu comme texte
      if (
        strpos($file['type'], 'text/') === 0 ||
        $file['type'] === 'application/json' ||
        $file['type'] === 'application/xml'
      ) {
        $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
        $fileContent = base64_decode($base64Data);
        $parts[] = [
          'text' => "Contenu du fichier {$file['name']}:\n```\n{$fileContent}\n```"
        ];
      }
    }
  }

  // Ajouter le texte
  if (!empty($userMessage)) {
    $parts[] = ['text' => $userMessage];
  } else if (empty($parts)) {
    $parts[] = ['text' => 'Analyse ce fichier'];
  }

  return $parts;
}

/**
 * Prépare le contenu du message pour Ollama (images séparées)
 * @param string $userMessage Le message texte de l'utilisateur
 * @param array $files Les fichiers attachés
 * @return array ['content' => string, 'images' => array]
 */
function prepareOllamaMessage($userMessage, $files = [])
{
  $messageContent = !empty($userMessage) ? $userMessage : 'Analyse cette image';
  $images = [];
  $supportedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  foreach ($files as $file) {
    if (in_array($file['type'], $supportedImageTypes)) {
      // Ollama attend les images en base64 sans le préfixe data:
      $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
      $images[] = $base64Data;
    } else {
      // Pour les fichiers non-image, ajouter le contenu au message
      if (
        strpos($file['type'], 'text/') === 0 ||
        $file['type'] === 'application/json' ||
        $file['type'] === 'application/xml'
      ) {
        $base64Data = preg_replace('/^data:[^;]+;base64,/', '', $file['data']);
        $fileContent = base64_decode($base64Data);
        $messageContent .= "\n\nContenu du fichier {$file['name']}:\n```\n{$fileContent}\n```";
      }
    }
  }

  return [
    'content' => $messageContent,
    'images' => $images
  ];
}

/**
 * Valide et extrait les données de la requête
 * @return array ['message' => string, 'files' => array, 'model' => string|null]
 */
function validateAndExtractInput()
{
  $input = json_decode(file_get_contents('php://input'), true);

  $message = isset($input['message']) ? trim($input['message']) : '';
  $files = isset($input['files']) ? $input['files'] : [];
  $model = isset($input['model']) && !empty($input['model']) ? $input['model'] : null;

  // Valider qu'on a au moins un message ou des fichiers
  if (empty($message) && empty($files)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message ou fichier requis']);
    exit();
  }

  return [
    'message' => $message,
    'files' => $files,
    'model' => $model,
    'raw' => $input
  ];
}
