<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

// Générer un token CSRF si non existant
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
  // Vérification CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    header('Location: deleted_user.php?error=Token de sécurité invalide');
    exit();
  }

  include 'db.php';

  try {
    // Supprimer d'abord les clés API de l'utilisateur
    $pdo->prepare("DELETE FROM api_keys_user WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    $pdo->prepare("DELETE FROM provider_settings WHERE user_id = ?")->execute([$_SESSION['user_id']]);

    // Supprimer l'utilisateur
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);

    session_destroy();
    header('Location: login.php?success=Compte supprimé avec succès');
    exit();
  } catch (PDOException $e) {
    // En cas d'erreur, essayer simplement de supprimer l'utilisateur
    try {
      $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
      $stmt->execute([$_SESSION['user_id']]);
      session_destroy();
      header('Location: login.php?success=Compte supprimé');
      exit();
    } catch (PDOException $e2) {
      header('Location: deleted_user.php?error=Erreur lors de la suppression');
      exit();
    }
  }
}

include 'header.php';
?>
<main class="pt-20 pb-10 min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-sm text-center">
    <div class="w-16 h-16 mx-auto mb-4 bg-red-500/10 border border-red-500/20 rounded-2xl flex items-center justify-center">
      <i class="fa-solid fa-triangle-exclamation text-2xl text-red-400"></i>
    </div>
    <h1 class="text-xl font-semibold text-neutral-200 mb-2">Supprimer le compte</h1>
    <p class="text-sm text-neutral-500 mb-6">Cette action est irréversible. Toutes vos données seront supprimées.</p>

    <div class="bg-neutral-800/50 border border-neutral-700/50 rounded-2xl p-6">
      <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-4 mb-4">
        <p class="text-sm text-red-400">
          <i class="fa-solid fa-warning mr-2"></i>
          Attention : cette action ne peut pas être annulée !
        </p>
      </div>

      <form method="POST" class="space-y-3">
        <input type="hidden" name="confirm_delete" value="1">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" class="w-full bg-red-600 hover:bg-red-500 text-white text-sm font-medium py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
          <i class="fa-solid fa-trash"></i>
          <span>Confirmer la suppression</span>
        </button>
        <a href="dashboard.php" class="flex items-center justify-center gap-2 w-full bg-neutral-700/50 hover:bg-neutral-700 border border-neutral-600/50 text-neutral-300 text-sm py-3 rounded-xl transition-colors">
          <i class="fa-solid fa-arrow-left"></i>
          <span>Annuler</span>
        </a>
      </form>
    </div>
  </div>
</main>
</body>

</html>