<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <!-- Preconnect CDN -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <!-- Preload CSS critique -->
    <link rel="preload" href="../src/output.css" as="style">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
    <!-- Font Awesome non-bloquant -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" media="print" onload="this.media='all'" />
    <title>NxtGenAI</title>
    <link href="../src/output.css" rel="stylesheet">
</head>

<body class="min-h-screen text-neutral-400">
    <header class="fixed top-0 left-0 right-0 z-50 bg-[oklch(21%_0.006_285.885)]/90 backdrop-blur-md border-b border-neutral-700/50">
        <div class="max-w-3xl mx-auto px-3 sm:px-4 py-3 flex items-center justify-between gap-2">
            <a href="../index.php" class="flex items-center gap-2 sm:gap-2.5 text-sm font-medium text-neutral-200 hover:text-white transition-colors shrink-0">
                <img src="../assets/images/logo.svg" alt="NxtGenAI" class="w-6 h-6 sm:w-7 sm:h-7" />
                <span class="hidden xs:inline">NxtGenAI</span>
            </a>
            <nav class="flex items-center gap-2 sm:gap-4">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span class="text-xs sm:text-sm text-neutral-400 hidden sm:inline max-w-25 truncate"><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <div class="w-px h-4 bg-neutral-700 hidden sm:block"></div>
                    <a href="dashboard.php" class="text-xs sm:text-sm text-neutral-400 hover:text-green-400 transition-colors flex items-center gap-1">
                        <i class="fa-solid fa-user"></i>
                        <span class="hidden sm:inline">Compte</span>
                    </a>
                    <a href="logout.php" class="text-xs sm:text-sm text-neutral-500 hover:text-red-400 transition-colors" title="Déconnexion">
                        <i class="fa-solid fa-sign-out-alt"></i>
                    </a>
                <?php else: ?>
                    <a href="login.php" class="text-xs sm:text-sm text-neutral-400 hover:text-green-400 transition-colors">Connexion</a>
                    <a href="register.php" class="px-2 sm:px-3 py-1.5 text-xs sm:text-sm bg-green-600 hover:bg-green-500 text-white rounded-lg transition-colors">Inscription</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>