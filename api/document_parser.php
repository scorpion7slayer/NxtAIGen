<?php

/**
 * Parser de fichiers PDF et DOCX côté serveur
 * Permet d'extraire le texte de documents pour le contexte IA
 */

class DocumentParser
{
  private $maxFileSize = 10485760; // 10 MB
  private $allowedMimeTypes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // DOCX
    'application/msword', // DOC
  ];

  /**
   * Parser un fichier uploadé
   * @param array $file Fichier depuis $_FILES
   * @return array ['success' => bool, 'text' => string, 'error' => string]
   */
  public function parse($file)
  {
    // Vérifier la taille
    if ($file['size'] > $this->maxFileSize) {
      return [
        'success' => false,
        'error' => 'Fichier trop volumineux (max 10 MB)'
      ];
    }

    // Vérifier le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $this->allowedMimeTypes)) {
      return [
        'success' => false,
        'error' => 'Type de fichier non supporté'
      ];
    }

    // Parser selon le type
    if ($mimeType === 'application/pdf') {
      return $this->parsePDF($file['tmp_name']);
    } elseif (strpos($mimeType, 'wordprocessing') !== false || $mimeType === 'application/msword') {
      return $this->parseDOCX($file['tmp_name']);
    }

    return ['success' => false, 'error' => 'Format non reconnu'];
  }

  /**
   * Parser un PDF
   * @param string $filePath Chemin temporaire du fichier
   * @return array
   */
  private function parsePDF($filePath)
  {
    // Utiliser pdftotext si disponible
    $pdftotextPath = $this->findPdfToText();
    if ($pdftotextPath) {
      $outputFile = tempnam(sys_get_temp_dir(), 'pdf_');
      $command = escapeshellcmd($pdftotextPath) . ' ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outputFile);
      exec($command, $output, $returnCode);

      if ($returnCode === 0 && file_exists($outputFile)) {
        $text = file_get_contents($outputFile);
        unlink($outputFile);

        if (!empty(trim($text))) {
          return ['success' => true, 'text' => $this->cleanText($text)];
        }
      }
    };
  }

  /**
   * Parser un DOCX avec ZipArchive (extraction XML)
   * @param string $filePath Chemin temporaire du fichier
   * @return array
   */
  private function parseDOCX($filePath)
  {
    if (!class_exists('ZipArchive')) {
      return [
        'success' => false,
        'error' => 'Extension PHP zip non disponible'
      ];
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
      return ['success' => false, 'error' => 'Impossible d\'ouvrir le fichier DOCX'];
    }

    // Le contenu textuel est dans word/document.xml
    $xmlContent = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xmlContent === false) {
      return ['success' => false, 'error' => 'Structure DOCX invalide'];
    }

    // Parser le XML et extraire le texte
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) {
      return ['success' => false, 'error' => 'XML DOCX invalide'];
    }

    // Enregistrer les namespaces
    $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    // Extraire tous les textes (w:t)
    $textNodes = $xml->xpath('//w:t');
    $text = '';
    foreach ($textNodes as $node) {
      $text .= (string)$node . ' ';
    }

    $text = $this->cleanText($text);

    if (empty(trim($text))) {
      return ['success' => false, 'error' => 'Document vide'];
    }

    return ['success' => true, 'text' => $text];
  }

  /**
   * Nettoyer et normaliser le texte extrait
   */
  private function cleanText($text)
  {
    // Remplacer les multiples espaces par un seul
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    // Limiter à 50 000 caractères
    if (mb_strlen($text) > 50000) {
      $text = mb_substr($text, 0, 50000) . '... [tronqué]';
    }
    return $text;
  }

  /**
   * Trouver l'exécutable pdftotext
   */
  private function findPdfToText()
  {
    $paths = [
      '/usr/bin/pdftotext',
      '/usr/local/bin/pdftotext',
      'C:\\Program Files\\poppler\\bin\\pdftotext.exe',
      'C:\\poppler\\bin\\pdftotext.exe',
    ];

    foreach ($paths as $path) {
      if (file_exists($path) && is_executable($path)) {
        return $path;
      }
    }

    $command = PHP_OS_FAMILY === 'Windows' ? 'where pdftotext' : 'which pdftotext';
    $output = @shell_exec($command);
    if ($output && trim($output)) {
      $path = trim(explode("\n", $output)[0]);
      if (file_exists($path)) {
        return $path;
      }
    }

    return null;
  }


  public function getAllowedMimeTypes()
  {
    return $this->allowedMimeTypes;
  }

  /**
   * Obtenir les extensions autorisées
   */
  public function getAllowedExtensions()
  {
    return ['pdf', 'docx', 'doc'];
  }
}
