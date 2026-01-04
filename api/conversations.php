<?php
session_start();
require_once __DIR__ . '/../zone_membres/db.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Non connecté']);
  exit();
}

$userId = $_SESSION['user_id'];

header('Content-Type: application/json');

// Récupérer l'action depuis GET ou depuis le body JSON pour les requêtes POST
$action = $_GET['action'] ?? '';
$input = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = json_decode(file_get_contents('php://input'), true);
  if (empty($action) && isset($input['action'])) {
    $action = $input['action'];
  }
}

try {
  switch ($action) {
    case 'list':
      // Lister toutes les conversations de l'utilisateur
      $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.created_at, c.updated_at,
               COUNT(m.id) as message_count,
               MAX(m.created_at) as last_message_at
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE c.user_id = ?
        GROUP BY c.id
        ORDER BY c.updated_at DESC
        LIMIT 100
      ");
      $stmt->execute([$userId]);
      $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
      echo json_encode(['success' => true, 'conversations' => $conversations]);
      break;

    case 'get':
      // Récupérer une conversation spécifique avec tous ses messages
      $conversationId = intval($_GET['id'] ?? 0);

      // Vérifier que la conversation appartient à l'utilisateur
      $stmt = $pdo->prepare("SELECT id, title, created_at, updated_at FROM conversations WHERE id = ? AND user_id = ?");
      $stmt->execute([$conversationId, $userId]);
      $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$conversation) {
        http_response_code(404);
        echo json_encode(['error' => 'Conversation non trouvée']);
        exit();
      }

      // Récupérer tous les messages (colonnes nécessaires uniquement)
      $stmt = $pdo->prepare("
        SELECT id, role, content, model, tokens_used, created_at 
        FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at ASC
      ");
      $stmt->execute([$conversationId]);
      $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo json_encode([
        'success' => true,
        'conversation' => $conversation,
        'messages' => $messages
      ]);
      break;

    case 'create':
      // Créer une nouvelle conversation
      $title = $input['title'] ?? 'Nouvelle conversation';

      $stmt = $pdo->prepare("INSERT INTO conversations (user_id, title) VALUES (?, ?)");
      $stmt->execute([$userId, $title]);
      $conversationId = $pdo->lastInsertId();

      echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'message' => 'Conversation créée'
      ]);
      break;

    case 'save_message':
      // Sauvegarder un message dans une conversation
      $conversationId = intval($input['conversation_id'] ?? 0);
      $role = $input['role'] ?? 'user';
      $content = $input['content'] ?? '';
      $model = $input['model'] ?? null;
      $provider = $input['provider'] ?? null;
      $tokensUsed = intval($input['tokens_used'] ?? 0);

      // Vérifier que la conversation appartient à l'utilisateur
      $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
      $stmt->execute([$conversationId, $userId]);
      if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit();
      }

      // Insérer le message
      $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, role, content, model, tokens_used)
        VALUES (?, ?, ?, ?, ?)
      ");
      $stmt->execute([$conversationId, $role, $content, $model, $tokensUsed > 0 ? $tokensUsed : null]);
      $messageId = $pdo->lastInsertId();

      // Mettre à jour le timestamp de la conversation
      $stmt = $pdo->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
      $stmt->execute([$conversationId]);

      echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'message' => 'Message sauvegardé'
      ]);
      break;

    case 'update_title':
      // Modifier le titre d'une conversation
      $conversationId = intval($input['conversation_id'] ?? 0);
      $title = $input['title'] ?? 'Nouvelle conversation';

      $stmt = $pdo->prepare("UPDATE conversations SET title = ? WHERE id = ? AND user_id = ?");
      $stmt->execute([$title, $conversationId, $userId]);

      if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Titre mis à jour']);
      } else {
        http_response_code(404);
        echo json_encode(['error' => 'Conversation non trouvée']);
      }
      break;

    case 'delete':
      // Supprimer une conversation (CASCADE supprimera les messages)
      $conversationId = intval($input['conversation_id'] ?? $_GET['id'] ?? 0);

      $stmt = $pdo->prepare("DELETE FROM conversations WHERE id = ? AND user_id = ?");
      $stmt->execute([$conversationId, $userId]);

      if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Conversation supprimée']);
      } else {
        http_response_code(404);
        echo json_encode(['error' => 'Conversation non trouvée']);
      }
      break;

    case 'generate_title':
      // Générer un titre automatiquement basé sur le premier échange
      $conversationId = intval($input['conversation_id'] ?? $_GET['id'] ?? 0);
      $userMessage = $input['user_message'] ?? '';
      $aiResponse = $input['ai_response'] ?? '';

      // Vérifier l'accès
      $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
      $stmt->execute([$conversationId, $userId]);
      if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès refusé']);
        exit();
      }

      // Générer un titre basé sur le message utilisateur
      if (!empty($userMessage)) {
        // Tronquer à 50 caractères et ajouter "..."
        $title = mb_substr($userMessage, 0, 50);
        if (mb_strlen($userMessage) > 50) {
          $title .= '...';
        }
      } else {
        // Récupérer le premier message utilisateur depuis la DB
        $stmt = $pdo->prepare("
          SELECT content FROM messages 
          WHERE conversation_id = ? AND role = 'user' 
          ORDER BY created_at ASC 
          LIMIT 1
        ");
        $stmt->execute([$conversationId]);
        $firstMessage = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($firstMessage) {
          $title = mb_substr($firstMessage['content'], 0, 50);
          if (mb_strlen($firstMessage['content']) > 50) {
            $title .= '...';
          }
        } else {
          $title = 'Nouvelle conversation';
        }
      }

      $stmt = $pdo->prepare("UPDATE conversations SET title = ? WHERE id = ?");
      $stmt->execute([$title, $conversationId]);

      echo json_encode(['success' => true, 'title' => $title]);
      break;

    default:
      http_response_code(400);
      echo json_encode(['error' => 'Action inconnue: ' . $action]);
  }
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
}
