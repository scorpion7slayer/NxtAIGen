<?php

/**
 * Fonctions d'aide pour le traitement des fichiers dans les APIs
 */

// =============================================================================
// CONSTANTS & SHARED HELPERS
// =============================================================================

/** Types MIME d'images supportés par les providers AI */
const SUPPORTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

/** Types MIME de fichiers texte supportés */
const SUPPORTED_TEXT_TYPES = ['text/', 'application/json', 'application/xml'];

/**
 * Vérifie si un fichier est une image supportée
 * @param string $mimeType Le type MIME du fichier
 * @return bool
 */
function isImageFile(string $mimeType): bool
{
  return in_array($mimeType, SUPPORTED_IMAGE_TYPES);
}

/**
 * Vérifie si un fichier est un fichier texte supporté
 * @param string $mimeType Le type MIME du fichier
 * @return bool
 */
function isTextFile(string $mimeType): bool
{
  return strpos($mimeType, 'text/') === 0 ||
    $mimeType === 'application/json' ||
    $mimeType === 'application/xml';
}

/**
 * Extrait les données base64 d'une data URL
 * @param string $dataUrl La data URL (data:type;base64,...)
 * @return string Les données base64 brutes
 */
function extractBase64Data(string $dataUrl): string
{
  return preg_replace('/^data:[^;]+;base64,/', '', $dataUrl);
}

/**
 * Formate le contenu d'un fichier texte pour l'affichage
 * @param string $fileName Le nom du fichier
 * @param string $base64Data Les données base64 du fichier
 * @return string Le contenu formaté
 */
function formatTextFileContent(string $fileName, string $base64Data): string
{
  $fileContent = base64_decode($base64Data);
  return "Contenu du fichier {$fileName}:\n```\n{$fileContent}\n```";
}

// =============================================================================
// PROVIDER-SPECIFIC MESSAGE FORMATTERS
// =============================================================================

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
    $messageContent[] = ['type' => 'text', 'text' => $userMessage];
  }

  // Traiter les fichiers
  foreach ($files as $file) {
    if (isImageFile($file['type'])) {
      // OpenAI accepte les data URLs directement
      $messageContent[] = [
        'type' => 'image_url',
        'image_url' => ['url' => $file['data'], 'detail' => 'auto']
      ];
    } else if (isTextFile($file['type'])) {
      $base64Data = extractBase64Data($file['data']);
      $messageContent[] = [
        'type' => 'text',
        'text' => formatTextFileContent($file['name'], $base64Data)
      ];
    }
  }

  return empty($messageContent) ? ($userMessage ?: 'Analyse ce fichier') : $messageContent;
}

/**
 * Prépare le contenu du message au format Anthropic
 * Note: Anthropic préfère les images AVANT le texte
 * @param string $userMessage Le message texte de l'utilisateur
 * @param array $files Les fichiers attachés
 * @return array Le contenu formaté pour l'API Anthropic
 */
function prepareAnthropicMessageContent($userMessage, $files = [])
{
  $messageContent = [];

  // Ajouter les fichiers d'abord (images avant texte pour Anthropic)
  foreach ($files as $file) {
    $base64Data = extractBase64Data($file['data']);

    if (isImageFile($file['type'])) {
      $messageContent[] = [
        'type' => 'image',
        'source' => ['type' => 'base64', 'media_type' => $file['type'], 'data' => $base64Data]
      ];
    } else if (isTextFile($file['type'])) {
      $messageContent[] = [
        'type' => 'text',
        'text' => formatTextFileContent($file['name'], $base64Data)
      ];
    }
  }

  // Ajouter le texte à la fin (ou un message par défaut)
  $messageContent[] = [
    'type' => 'text',
    'text' => !empty($userMessage) ? $userMessage : (empty($messageContent) ? 'Analyse ce fichier' : '')
  ];

  // Supprimer le dernier élément s'il est vide (cas: fichiers présents mais pas de message)
  if (end($messageContent)['text'] === '') {
    array_pop($messageContent);
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

  // Traiter les fichiers
  foreach ($files as $file) {
    $base64Data = extractBase64Data($file['data']);

    if (isImageFile($file['type'])) {
      $parts[] = ['inlineData' => ['mimeType' => $file['type'], 'data' => $base64Data]];
    } else if (isTextFile($file['type'])) {
      $parts[] = ['text' => formatTextFileContent($file['name'], $base64Data)];
    }
  }

  // Ajouter le texte utilisateur ou un message par défaut
  if (!empty($userMessage)) {
    $parts[] = ['text' => $userMessage];
  } else if (empty($parts)) {
    $parts[] = ['text' => 'Analyse ce fichier'];
  }

  return $parts;
}

/**
 * Prépare le contenu du message pour Ollama (images séparées du texte)
 * @param string $userMessage Le message texte de l'utilisateur
 * @param array $files Les fichiers attachés
 * @return array ['content' => string, 'images' => array]
 */
function prepareOllamaMessage($userMessage, $files = [])
{
  $messageContent = !empty($userMessage) ? $userMessage : 'Analyse cette image';
  $images = [];

  foreach ($files as $file) {
    $base64Data = extractBase64Data($file['data']);

    if (isImageFile($file['type'])) {
      // Ollama attend les images en base64 sans le préfixe data:
      $images[] = $base64Data;
    } else if (isTextFile($file['type'])) {
      // Fichiers texte: ajouter au contenu du message
      $messageContent .= "\n\n" . formatTextFileContent($file['name'], $base64Data);
    }
  }

  return ['content' => $messageContent, 'images' => $images];
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
