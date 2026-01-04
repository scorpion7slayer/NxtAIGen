/**
 * Affichage des limites de rate limiting pour les utilisateurs connectés
 * Ajout au DOM principal de index.php
 */

// Widget d'affichage des limites restantes (pour utilisateurs connectés)
if (!isGuest && typeof conversationManager !== "undefined") {
    // Créer le conteneur des limites
    const rateLimitWidget = document.createElement("div");
    rateLimitWidget.id = "rateLimitWidget";
    rateLimitWidget.className =
        "fixed bottom-4 right-4 bg-neutral-800/90 backdrop-blur-md border border-neutral-700/50 rounded-xl p-4 text-xs text-neutral-400 shadow-lg";
    rateLimitWidget.style.maxWidth = "280px";
    rateLimitWidget.style.display = "none"; // Masqué par défaut

    rateLimitWidget.innerHTML = `
    <div class="flex items-center justify-between mb-2">
      <span class="font-medium text-neutral-300"><i class="fa-solid fa-chart-simple mr-1"></i>Utilisation</span>
      <button id="closeRateLimitWidget" class="text-neutral-500 hover:text-neutral-300 transition-colors">
        <i class="fa-solid fa-times"></i>
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
      <a href="/zone_membres/settings.php" class="text-blue-400 hover:text-blue-300 text-xs flex items-center gap-1">
        <i class="fa-solid fa-arrow-up-right-from-square"></i>
        <span>Améliorer mon plan</span>
      </a>
    </div>
  `;

    document.body.appendChild(rateLimitWidget);

    // Fermer le widget
    document
        .getElementById("closeRateLimitWidget")
        .addEventListener("click", () => {
            rateLimitWidget.style.display = "none";
            localStorage.setItem("rateLimitWidgetClosed", "true");
        });

    // Fonction pour mettre à jour les limites affichées
    window.updateRateLimits = function (limits) {
        if (!limits) return;

        const widget = document.getElementById("rateLimitWidget");

        // Afficher le widget si des limites sont retournées
        if (
            limits.hourly !== undefined &&
            localStorage.getItem("rateLimitWidgetClosed") !== "true"
        ) {
            widget.style.display = "block";
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
