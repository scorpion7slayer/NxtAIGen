<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
include 'header.php';
?>
<main class="pt-20 pb-10 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="w-16 h-16 mx-auto mb-4 bg-neutral-800/50 border border-neutral-700/50 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-user text-2xl text-green-500"></i>
            </div>
            <h1 class="text-xl font-semibold text-neutral-200 mb-1">Connexion</h1>
            <p class="text-sm text-neutral-500">Accédez à votre compte</p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="mb-4 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl flex items-center gap-3">
                <i class="fa-solid fa-exclamation-circle text-red-400"></i>
                <p class="text-sm text-red-400"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="mb-4 px-4 py-3 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center gap-3">
                <i class="fa-solid fa-check-circle text-green-400"></i>
                <p class="text-sm text-green-400"><?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-neutral-800/50 border border-neutral-700/50 rounded-2xl p-6">
            <form action="handle_login.php" method="POST" class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-neutral-400 mb-2">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required
                        class="w-full bg-neutral-700/50 border border-neutral-600/50 rounded-xl px-4 py-3 text-sm text-neutral-200 placeholder:text-neutral-500 outline-none focus:border-green-500/50 transition-colors"
                        placeholder="Votre nom d'utilisateur">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-neutral-400 mb-2">Mot de passe</label>
                    <input type="password" id="password" name="password" required
                        class="w-full bg-neutral-700/50 border border-neutral-600/50 rounded-xl px-4 py-3 text-sm text-neutral-200 placeholder:text-neutral-500 outline-none focus:border-green-500/50 transition-colors"
                        placeholder="••••••••">
                </div>
                <button type="submit" class="w-full bg-green-600 hover:bg-green-500 text-white text-sm font-medium py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
                    <i class="fa-solid fa-sign-in-alt"></i>
                    <span>Se connecter</span>
                </button>
            </form>
        </div>

        <p class="text-center text-sm text-neutral-500 mt-6">
            Pas de compte ? <a href="register.php" class="text-green-400 hover:text-green-300 transition-colors">Créer un compte</a>
        </p>
    </div>
</main>
</body>

</html>