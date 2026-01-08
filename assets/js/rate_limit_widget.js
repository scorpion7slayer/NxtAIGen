/**
 * Affichage des limites de rate limiting pour les utilisateurs connectés
 * Ajout au DOM principal de index.php
 */

// Fonction d'initialisation du widget
function initRateLimitWidget() {
    // Widget d'affichage des limites restantes (pour utilisateurs connectés)
    // S'affiche uniquement si l'utilisateur n'est pas un invité
    if (typeof isGuest !== "undefined" && !isGuest) {
        // Créer le bouton réduit (affiché quand widget fermé)
        const rateLimitToggle = document.createElement("button");
        rateLimitToggle.id = "rateLimitToggle";
        rateLimitToggle.className =
            "fixed bottom-4 right-4 bg-neutral-800/90 backdrop-blur-md border border-neutral-700/50 rounded-full w-10 h-10 flex items-center justify-center text-neutral-400 hover:text-neutral-200 hover:bg-neutral-700/90 shadow-lg transition-all duration-200 z-40";
        rateLimitToggle.innerHTML = '<i class="fa-solid fa-chart-simple"></i>';
        rateLimitToggle.title = "Afficher l'utilisation";
        rateLimitToggle.style.display = "none"; // Masqué par défaut

        document.body.appendChild(rateLimitToggle);

        // Créer le conteneur des limites
        const rateLimitWidget = document.createElement("div");
        rateLimitWidget.id = "rateLimitWidget";
        rateLimitWidget.className =
            "fixed bottom-4 right-4 bg-neutral-800/90 backdrop-blur-md border border-neutral-700/50 rounded-xl p-4 text-xs text-neutral-400 shadow-lg z-40";
        rateLimitWidget.style.maxWidth = "280px";
        rateLimitWidget.style.display = "none"; // Masqué par défaut

        // Ajouter des styles responsive pour mobile
        const styleSheet = document.createElement("style");
        styleSheet.textContent = `
            @media (max-width: 640px) {
                #rateLimitWidget, #rateLimitToggle {
                    bottom: 5rem !important;
                    right: 0.75rem !important;
                }
                #rateLimitWidget {
                    max-width: calc(100vw - 1.5rem) !important;
                    left: 0.75rem !important;
                }
            }
            @media (min-width: 641px) and (max-width: 800px) {
                #rateLimitWidget, #rateLimitToggle {
                    bottom: 1rem !important;
                    right: 1rem !important;
                }
            }
        `;
        document.head.appendChild(styleSheet);

        rateLimitWidget.innerHTML = `
    <div class="flex items-center justify-between mb-2">
      <span class="font-medium text-neutral-300"><i class="fa-solid fa-chart-simple mr-1"></i>Utilisation</span>
      <button id="closeRateLimitWidget" class="text-neutral-500 hover:text-neutral-300 transition-colors" title="Réduire">
        <i class="fa-solid fa-minus"></i>
      </button>
    </div>
    <div class="space-y-2">
      <div>
        <div class="flex justify-between mb-1">
          <span>Cette heure</span>
          <span id="hourlyUsage" class="font-mono">-/-</span>
        </div>
        <div class="h-1 bg-neutral-700 rounded-full overflow-hidden">
          <div id="hourlyBar" class="h-full bg-blue-500 transition-all duration-300" style="width: 0%"></div>
        </div>
      </div>
      <div>
        <div class="flex justify-between mb-1">
          <span>Aujourd'hui</span>
          <span id="dailyUsage" class="font-mono">-/-</span>
        </div>
        <div class="h-1 bg-neutral-700 rounded-full overflow-hidden">
          <div id="dailyBar" class="h-full bg-green-500 transition-all duration-300" style="width: 0%"></div>
        </div>
      </div>
      <div>
        <div class="flex justify-between mb-1">
          <span>Ce mois</span>
          <span id="monthlyUsage" class="font-mono">-/-</span>
        </div>
        <div class="h-1 bg-neutral-700 rounded-full overflow-hidden">
          <div id="monthlyBar" class="h-full bg-purple-500 transition-all duration-300" style="width: 0%"></div>
        </div>
      </div>
    </div>
    <div class="mt-3 pt-3 border-t border-neutral-700/50">
      <a href="/NxtAIGen/shop/abonnement-achat-index-stripe.php" class="text-blue-400 hover:text-blue-300 text-xs flex items-center gap-1">
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        <span>Améliorer mon plan</span>
      </a>
    </div>
  `;

        document.body.appendChild(rateLimitWidget);

        // Fonction pour réduire le widget (montrer le toggle)
        function minimizeWidget() {
            rateLimitWidget.style.display = "none";
            rateLimitToggle.style.display = "flex";
            localStorage.setItem("rateLimitWidgetMinimized", "true");
        }

        // Fonction pour agrandir le widget (cacher le toggle)
        function expandWidget() {
            rateLimitToggle.style.display = "none";
            rateLimitWidget.style.display = "block";
            localStorage.setItem("rateLimitWidgetMinimized", "false");
        }

        // Fermer/réduire le widget
        document
            .getElementById("closeRateLimitWidget")
            .addEventListener("click", minimizeWidget);

        // Ouvrir le widget depuis le toggle
        rateLimitToggle.addEventListener("click", expandWidget);

        // Fonction pour mettre à jour les limites affichées
        window.updateRateLimits = function (limits) {
            if (!limits) return;

            const widget = document.getElementById("rateLimitWidget");
            const toggle = document.getElementById("rateLimitToggle");
            const isMinimized =
                localStorage.getItem("rateLimitWidgetMinimized") === "true";

            // Afficher le widget ou le toggle selon l'état précédent
            if (limits.hourly !== undefined) {
                if (isMinimized) {
                    toggle.style.display = "flex";
                    widget.style.display = "none";
                } else {
                    widget.style.display = "block";
                    toggle.style.display = "none";
                }
            }

            // Hourly
            if (limits.hourly !== undefined && limits.hourly !== -1) {
                const hourlyElement = document.getElementById("hourlyUsage");
                const hourlyBar = document.getElementById("hourlyBar");
                const hourlyLimit = limits.hourly_limit || 20;
                const hourlyUsed = hourlyLimit - limits.hourly;
                const hourlyPercent = (hourlyUsed / hourlyLimit) * 100;

                hourlyElement.textContent = `${hourlyUsed}/${hourlyLimit}`;
                hourlyBar.style.width = `${hourlyPercent}%`;
                hourlyBar.className = `h-full transition-all duration-300 ${
                    hourlyPercent >= 90
                        ? "bg-red-500"
                        : hourlyPercent >= 70
                          ? "bg-amber-500"
                          : "bg-blue-500"
                }`;
            } else if (limits.hourly === -1) {
                document.getElementById("hourlyUsage").textContent = "∞";
                document.getElementById("hourlyBar").style.width = "100%";
            }

            // Daily
            if (limits.daily !== undefined && limits.daily !== -1) {
                const dailyElement = document.getElementById("dailyUsage");
                const dailyBar = document.getElementById("dailyBar");
                const dailyLimit = limits.daily_limit || 100;
                const dailyUsed = dailyLimit - limits.daily;
                const dailyPercent = (dailyUsed / dailyLimit) * 100;

                dailyElement.textContent = `${dailyUsed}/${dailyLimit}`;
                dailyBar.style.width = `${dailyPercent}%`;
                dailyBar.className = `h-full transition-all duration-300 ${
                    dailyPercent >= 90
                        ? "bg-red-500"
                        : dailyPercent >= 70
                          ? "bg-amber-500"
                          : "bg-green-500"
                }`;
            } else if (limits.daily === -1) {
                document.getElementById("dailyUsage").textContent = "∞";
                document.getElementById("dailyBar").style.width = "100%";
            }

            // Monthly
            if (limits.monthly !== undefined && limits.monthly !== -1) {
                const monthlyElement = document.getElementById("monthlyUsage");
                const monthlyBar = document.getElementById("monthlyBar");
                const monthlyLimit = limits.monthly_limit || 2000;
                const monthlyUsed = monthlyLimit - limits.monthly;
                const monthlyPercent = (monthlyUsed / monthlyLimit) * 100;

                monthlyElement.textContent = `${monthlyUsed}/${monthlyLimit}`;
                monthlyBar.style.width = `${monthlyPercent}%`;
                monthlyBar.className = `h-full transition-all duration-300 ${
                    monthlyPercent >= 90
                        ? "bg-red-500"
                        : monthlyPercent >= 70
                          ? "bg-amber-500"
                          : "bg-purple-500"
                }`;
            } else if (limits.monthly === -1) {
                document.getElementById("monthlyUsage").textContent = "∞";
                document.getElementById("monthlyBar").style.width = "100%";
            }
        };

        // Charger les limites initiales
        if (!isGuest) {
            fetch("/NxtAIGen/api/rate_limiter_info.php")
                .then((r) => r.json())
                .then((data) => {
                    if (data.success && data.limits) {
                        window.updateRateLimits(data.limits);
                    }
                })
                .catch((err) =>
                    console.error("Erreur chargement rate limits:", err),
                );
        }
    }
}

// Exécuter quand le DOM est prêt
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initRateLimitWidget);
} else {
    // DOM déjà chargé, exécuter immédiatement
    initRateLimitWidget();
}
