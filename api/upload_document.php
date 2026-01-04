<?php

/**
 * API pour upload et parsing de fichiers PDF/DOCX
 */

session_start();
require_once __DIR__ . '/document_parser.php';

header('Content-Type: application/json');

// Vérifier la méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Méthode non autorisée']);
  exit();
}

// Vérifier qu'un fichier a été uploadé
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  $errorMsg = 'Aucun fichier reçu';
  if (isset($_FILES['document']['error'])) {
    switch ($_FILES['document']['error']) {
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        $errorMsg = 'Fichier trop volumineux';
        break;
      case UPLOAD_ERR_PARTIAL:
        $errorMsg = 'Upload incomplet';
        break;
      case UPLOAD_ERR_NO_FILE:
        $errorMsg = 'Aucun fichier sélectionné';
        break;
      default:
        $errorMsg = 'Erreur d\'upload';
    }
  }
  echo json_encode(['error' => $errorMsg]);
  exit();
}

try {
  $parser = new DocumentParser();
  $result = $parser->parse($_FILES['document']);

  if ($result['success']) {
    echo json_encode([
      'success' => true,
      'text' => $result['text'],
      'filename' => $_FILES['document']['name'],
      'size' => $_FILES['document']['size'],
      'char_count' => mb_strlen($result['text'])
    ]);
  } else {
    http_response_code(400);
    echo json_encode([
      'success' => false,
      'error' => $result['error']
    ]);
  }
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Erreur serveur: ' . $e->getMessage()
  ]);
}
