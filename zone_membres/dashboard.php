<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';

$isAdmin = false;
try {
    $checkAdmin = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $checkAdmin->execute([$_SESSION['user_id']]);
    $userData = $checkAdmin->fetch();
    $isAdmin = ($userData && $userData['is_admin'] == 1);
} catch (PDOException $ex) {
    $isAdmin = false;
}

include 'header.php';
?>
<main class="pt-20 pb-10 min-h-screen px-4">
    <div class="max-w-lg mx-auto">
        <?php if (isset($_GET['oauth_success'])): ?>
            <div class="mb-6 px-4 py-3 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center gap-3">
                <i class="fa-solid fa-check-circle text-green-400"></i>
                <span class="text-sm text-green-400"><?php echo htmlspecialchars($_SESSION['oauth_success'] ?? 'Connexion OAuth réussie!', ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <?php unset($_SESSION['oauth_success']); ?>
        <?php endif; ?>

        <?php if (isset($_GET['oauth_error'])): ?>
            <div class="mb-6 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center gap-3">
                <i class="fa-solid fa-exclamation-circle text-red-400"></i>
                <span class="text-sm text-red-400"><?php echo htmlspecialchars($_GET['oauth_error'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        <?php endif; ?>

        <div class="text-center mb-10">
            <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-br from-green-500/20 to-green-600/10 border border-green-500/30 rounded-2xl flex items-center justify-center">
                <span class="text-2xl font-bold text-green-400"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
            </div>
            <h1 class="text-xl font-semibold text-gray-800 dark:text-neutral-200"><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="text-sm text-gray-500 dark:text-neutral-500 mt-1">ID: <?php echo $_SESSION['user_id']; ?></p>
            <?php if ($isAdmin): ?>
                <span class="inline-flex items-center gap-1.5 mt-3 px-3 py-1.5 bg-purple-500/10 border border-purple-500/30 rounded-xl text-xs text-purple-400">
                    <i class="fa-solid fa-crown"></i>
                    <span data-i18n="dashboard.administrator">Administrateur</span>
                </span>
            <?php endif; ?>
        </div>

        <div class="bg-white dark:bg-neutral-800/50 border border-gray-200 dark:border-neutral-700/50 rounded-2xl p-4 space-y-2 shadow-sm dark:shadow-none">
            <a href="../index.php" class="flex items-center gap-3 w-full bg-gray-100 dark:bg-neutral-700/30 hover:bg-gray-200 dark:hover:bg-neutral-700/50 border border-gray-200 dark:border-neutral-600/30 rounded-xl px-4 py-3 text-sm text-gray-700 dark:text-neutral-200 transition-colors">
                <div class="w-9 h-9 rounded-lg bg-green-500/10 flex items-center justify-center">
                    <i class="fa-solid fa-comments text-green-400"></i>
                </div>
                <span data-i18n="dashboard.go_chatbot">Aller au chatbot</span>
                <i class="fa-solid fa-chevron-right ml-auto text-gray-400 dark:text-neutral-600"></i>
            </a>

            <?php if ($isAdmin): ?>
                <a href="../admin/settings.php" class="flex items-center gap-3 w-full bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 rounded-xl px-4 py-3 text-sm text-purple-600 dark:text-purple-300 transition-colors">
                    <div class="w-9 h-9 rounded-lg bg-purple-500/10 flex items-center justify-center">
                        <i class="fa-solid fa-user-shield text-purple-400"></i>
                    </div>
                    <span data-i18n="dashboard.admin_settings">Paramètres administrateur</span>
                    <i class="fa-solid fa-chevron-right ml-auto text-purple-400 dark:text-purple-600"></i>
                </a>
            <?php endif; ?>

            <a href="subscription.php" class="flex items-center gap-3 w-full bg-gray-100 dark:bg-neutral-700/30 hover:bg-gray-200 dark:hover:bg-neutral-700/50 border border-gray-200 dark:border-neutral-600/30 rounded-xl px-4 py-3 text-sm text-gray-700 dark:text-neutral-200 transition-colors">
                <div class="w-9 h-9 rounded-lg bg-green-500/10 flex items-center justify-center">
                    <i class="fa-solid fa-credit-card text-green-400"></i>
                </div>
                <span data-i18n="dashboard.my_subscription">Mon abonnement</span>
                <i class="fa-solid fa-chevron-right ml-auto text-gray-400 dark:text-neutral-600"></i>
            </a>

            <a href="settings.php" class="flex items-center gap-3 w-full bg-gray-100 dark:bg-neutral-700/30 hover:bg-gray-200 dark:hover:bg-neutral-700/50 border border-gray-200 dark:border-neutral-600/30 rounded-xl px-4 py-3 text-sm text-gray-700 dark:text-neutral-200 transition-colors">
                <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center">
                    <i class="fa-solid fa-user-gear text-amber-400"></i>
                </div>
                <span data-i18n="dashboard.my_settings">Mes paramètres</span>
                <i class="fa-solid fa-chevron-right ml-auto text-gray-400 dark:text-neutral-600"></i>
            </a>

            <a href="logout.php" class="flex items-center gap-3 w-full bg-gray-100 dark:bg-neutral-700/30 hover:bg-gray-200 dark:hover:bg-neutral-700/50 border border-gray-200 dark:border-neutral-600/30 rounded-xl px-4 py-3 text-sm text-gray-500 dark:text-neutral-400 transition-colors">
                <div class="w-9 h-9 rounded-lg bg-gray-200 dark:bg-neutral-700/50 flex items-center justify-center">
                    <i class="fa-solid fa-sign-out-alt text-gray-500 dark:text-neutral-500"></i>
                </div>
                <span data-i18n="dashboard.logout">Se déconnecter</span>
                <i class="fa-solid fa-chevron-right ml-auto text-gray-400 dark:text-neutral-600"></i>
            </a>
        </div>

        <div class="mt-4">
            <a href="deleted_user.php" class="flex items-center justify-center gap-2 w-full bg-red-500/5 hover:bg-red-500/10 border border-red-500/10 hover:border-red-500/20 rounded-xl px-4 py-3 text-sm text-gray-500 dark:text-neutral-500 hover:text-red-500 dark:hover:text-red-400 transition-colors">
                <i class="fa-solid fa-trash"></i>
                <span data-i18n="dashboard.delete_account">Supprimer le compte</span>
            </a>
        </div>
    </div>
</main>
</body>

</html>