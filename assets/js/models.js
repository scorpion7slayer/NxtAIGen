/**
 * Gestionnaire d'autodétection des modèles IA
 * Charge dynamiquement les modèles disponibles depuis l'API
 */

const ModelManager = {
    // Configuration des providers
    providers: {
        openai: {
            name: "OpenAI",
            icon: "assets/images/providers/openai.svg",
            color: "emerald",
        },
        anthropic: {
            name: "Anthropic",
            icon: "assets/images/providers/anthropic.svg",
            color: "orange",
        },
        gemini: {
            name: "Google",
            icon: "assets/images/providers/gemini.svg",
            color: "blue",
        },
        deepseek: {
            name: "DeepSeek",
            icon: "assets/images/providers/deepseek.svg",
            color: "indigo",
        },
        mistral: {
            name: "Mistral AI",
            icon: "assets/images/providers/mistral.svg",
            color: "orange",
        },
        xai: {
            name: "xAI",
            icon: "assets/images/providers/xai.svg",
            color: "gray",
        },
        perplexity: {
            name: "Perplexity",
            icon: "assets/images/providers/perplexity.svg",
            color: "teal",
        },
        openrouter: {
            name: "OpenRouter",
            icon: "assets/images/providers/openrouter.svg",
            color: "purple",
        },
        huggingface: {
            name: "Hugging Face",
            icon: "assets/images/providers/huggingface.svg",
            color: "yellow",
        },
        moonshot: {
            name: "Moonshot",
            icon: "assets/images/providers/moonshot.svg",
            color: "slate",
        },
        github: {
            name: "GitHub",
            icon: "assets/images/providers/githubcopilot.svg",
            color: "green",
        },
        ollama: {
            name: "Ollama",
            icon: "assets/images/providers/ollama.svg",
            color: "gray",
        },
    },

    // Cache des modèles
    cache: {},
    cacheExpiry: 5 * 60 * 1000, // 5 minutes

    /**
     * Charge les modèles pour un provider spécifique
     */
    async loadModels(provider) {
        // Vérifier le cache
        const cached = this.cache[provider];
        if (cached && Date.now() - cached.timestamp < this.cacheExpiry) {
            return cached.data;
        }

        try {
            const response = await fetch(`api/models.php?provider=${provider}`);
            const data = await response.json();

            // Mettre en cache
            this.cache[provider] = {
                data: data,
                timestamp: Date.now(),
            };

            return data;
        } catch (error) {
            console.error(`Erreur chargement modèles ${provider}:`, error);
            return { error: "Erreur de connexion" };
        }
    },

    /**
     * Charge tous les modèles disponibles
     */
    async loadAllModels() {
        try {
            const response = await fetch("api/models.php?provider=all");
            const data = await response.json();

            // Mettre en cache chaque provider
            for (const [provider, models] of Object.entries(data)) {
                if (provider !== "provider" && provider !== "timestamp") {
                    this.cache[provider] = {
                        data: models,
                        timestamp: Date.now(),
                    };
                }
            }

            return data;
        } catch (error) {
            console.error("Erreur chargement modèles:", error);
            return { error: "Erreur de connexion" };
        }
    },

    /**
     * Génère le HTML pour un modèle
     */
    renderModelOption(provider, model) {
        const providerInfo = this.providers[provider];
        const colorClass = `bg-${providerInfo.color}-500/20`;

        return `
            <button
                class="model-option w-full flex items-center gap-3 px-3 py-2 rounded-lg text-gray-300 hover:bg-gray-700/50 transition-colors text-left"
                data-provider="${provider}"
                data-model="${model.id}"
                data-icon="${providerInfo.icon}"
                data-display="${model.name || model.id}">
                <div class="w-8 h-8 rounded-lg ${colorClass} flex items-center justify-center">
                    <img src="${providerInfo.icon}" class="w-5 h-5" alt="${providerInfo.name}">
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">${model.name || model.id}</p>
                    <p class="text-xs text-gray-500 truncate">${model.description || ""}</p>
                </div>
            </button>
        `;
    },

    /**
     * Génère le HTML pour une section de provider
     * Avec déduplication des modèles
     */
    renderProviderSection(provider, models, globalSeenIds = new Set()) {
        const providerInfo = this.providers[provider];
        if (!providerInfo || !models || models.length === 0)
            return { html: "", seenIds: globalSeenIds };

        // Filtrer les modèles déjà affichés par un autre provider
        const uniqueModels = models.filter((model) => {
            const id = model.id.toLowerCase();
            if (globalSeenIds.has(id)) return false;
            globalSeenIds.add(id);
            return true;
        });

        if (uniqueModels.length === 0)
            return { html: "", seenIds: globalSeenIds };

        let html = `
            <div class="mb-2 provider-section" data-provider="${provider}">
                <p class="px-3 py-1 text-xs text-gray-500 flex items-center gap-2">
                    <img src="${providerInfo.icon}" alt="${providerInfo.name}" class="w-3 h-3">
                    ${providerInfo.name}
                    <span class="ml-auto text-gray-600">${uniqueModels.length}</span>
                </p>
        `;

        for (const model of uniqueModels) {
            html += this.renderModelOption(provider, model);
        }

        html += "</div>";
        return { html, seenIds: globalSeenIds };
    },

    /**
     * Rafraîchit le menu des modèles
     */
    async refreshModelMenu() {
        const menuContent = document.querySelector("#modelMenu .p-2");
        if (!menuContent) return;

        // Afficher le loader
        menuContent.innerHTML = `
            <p class="px-3 py-2 text-xs font-medium text-gray-500 uppercase tracking-wider flex items-center gap-2">
                <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Chargement des modèles...
            </p>
        `;

        // Charger tous les modèles
        const allModels = await this.loadAllModels();

        // Construire le HTML
        let html = `
            <div class="flex items-center justify-between px-3 py-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Modèles disponibles</p>
                <button id="refreshModelsBtn" class="text-gray-500 hover:text-gray-300 transition-colors" title="Rafraîchir">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
            <div class="border-b border-gray-700/50 mb-2"></div>
        `;

        // Ordre des providers
        const providerOrder = [
            "openai",
            "anthropic",
            "gemini",
            "deepseek",
            "mistral",
            "xai",
            "perplexity",
            "openrouter",
            "huggingface",
            "moonshot",
            "github",
            "ollama",
        ];

        let hasModels = false;
        let globalSeenIds = new Set(); // Pour dédupliquer entre providers

        for (const provider of providerOrder) {
            const data = allModels[provider];
            if (data && data.models && data.models.length > 0) {
                const result = this.renderProviderSection(
                    provider,
                    data.models,
                    globalSeenIds,
                );
                if (result.html) {
                    html += result.html;
                    globalSeenIds = result.seenIds;
                    hasModels = true;
                }
            } else if (data && data.error) {
                // Afficher les providers avec erreur différemment
                const providerInfo = this.providers[provider];
                if (providerInfo) {
                    html += `
                        <div class="mb-2 opacity-50">
                            <p class="px-3 py-1 text-xs text-gray-600 flex items-center gap-2">
                                <img src="${providerInfo.icon}" alt="${providerInfo.name}" class="w-3 h-3 opacity-50">
                                ${providerInfo.name}
                                <span class="ml-auto text-red-400/50 text-[10px]">${data.error}</span>
                            </p>
                        </div>
                    `;
                }
            }
        }

        if (!hasModels) {
            html += `
                <p class="px-3 py-4 text-sm text-gray-500 text-center">
                    Aucun modèle disponible.<br>
                    <span class="text-xs">Configurez vos clés API dans config.php</span>
                </p>
            `;
        }

        menuContent.innerHTML = html;

        // Réattacher les événements
        this.attachModelEvents();

        // Bouton refresh
        document
            .getElementById("refreshModelsBtn")
            ?.addEventListener("click", async (e) => {
                e.stopPropagation();
                this.cache = {}; // Vider le cache
                await this.refreshModelMenu();
            });
    },

    /**
     * Attache les événements aux options de modèle
     */
    attachModelEvents() {
        document.querySelectorAll(".model-option").forEach((option) => {
            option.addEventListener("click", function (e) {
                e.stopPropagation();

                selectedModel = {
                    provider: this.dataset.provider,
                    model: this.dataset.model,
                    display: this.dataset.display,
                };

                // Mettre à jour l'affichage
                document.getElementById("modelIcon").src = this.dataset.icon;
                document.getElementById("modelName").textContent =
                    this.dataset.display;

                // Fermer le menu
                const modelMenu = document.getElementById("modelMenu");
                const modelChevron = document.getElementById("modelChevron");
                modelMenu.classList.remove(
                    "opacity-100",
                    "visible",
                    "translate-y-0",
                );
                modelMenu.classList.add(
                    "opacity-0",
                    "invisible",
                    "translate-y-2",
                );
                modelChevron.classList.remove("rotate-180");
                isModelMenuOpen = false;
            });
        });
    },

    /**
     * Initialise le gestionnaire de modèles
     */
    init() {
        // Charger les modèles au premier clic sur le menu
        const modelSelectorBtn = document.getElementById("modelSelectorBtn");
        let firstOpen = true;

        modelSelectorBtn?.addEventListener("click", async () => {
            if (firstOpen) {
                firstOpen = false;
                await this.refreshModelMenu();
            }
        });

        // Ou charger immédiatement si l'utilisateur est connecté
        if (document.querySelector("#profileButton")) {
            this.refreshModelMenu();
        }
    },
};

// Initialiser au chargement de la page
document.addEventListener("DOMContentLoaded", () => {
    ModelManager.init();
});
