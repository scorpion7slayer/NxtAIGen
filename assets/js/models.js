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
    localStorageKey: "nxtgenai_models_cache",

    // Liste plate de tous les modèles (pour le bottom sheet mobile)
    models: [],

    /**
     * Initialise le cache depuis localStorage
     */
    initCache() {
        try {
            const stored = localStorage.getItem(this.localStorageKey);
            if (stored) {
                const parsed = JSON.parse(stored);
                // Valider que le cache n'est pas trop vieux (max 30 min)
                const maxAge = 30 * 60 * 1000;
                Object.keys(parsed).forEach((provider) => {
                    if (Date.now() - parsed[provider].timestamp < maxAge) {
                        this.cache[provider] = parsed[provider];
                    }
                });
            }
        } catch (e) {
            console.warn("Erreur lecture cache modèles:", e);
        }
    },

    /**
     * Sauvegarde le cache dans localStorage
     */
    saveCache() {
        try {
            localStorage.setItem(
                this.localStorageKey,
                JSON.stringify(this.cache),
            );
        } catch (e) {
            console.warn("Erreur sauvegarde cache modèles:", e);
        }
    },

    /**
     * Charge les modèles pour un provider spécifique
     */
    async loadModels(provider) {
        // Vérifier le cache (mémoire d'abord)
        const cached = this.cache[provider];
        if (cached && Date.now() - cached.timestamp < this.cacheExpiry) {
            return cached.data;
        }

        try {
            const response = await fetch(`api/models.php?provider=${provider}`);
            const data = await response.json();

            // Mettre en cache (mémoire + localStorage)
            this.cache[provider] = {
                data: data,
                timestamp: Date.now(),
            };
            this.saveCache();

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
                    <i class="fa-solid fa-arrows-rotate text-sm"></i>
                </button>
            </div>
            <div class="relative px-3 pb-2">
                <i class="fa-solid fa-magnifying-glass absolute left-6 top-1/2 -translate-y-1/2 text-gray-500 text-xs pointer-events-none"></i>
                <input
                    type="text"
                    id="desktopModelSearch"
                    placeholder="Rechercher..."
                    autocomplete="off"
                    class="w-full pl-8 pr-8 py-1.5 bg-gray-700/50 border border-gray-700/50 rounded-lg text-sm text-gray-200 placeholder-gray-500 focus:border-green-500/50 focus:ring-2 focus:ring-green-500/20 outline-none transition-colors"
                />
                <button
                    id="desktopModelSearchClear"
                    type="button"
                    class="absolute right-6 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors hidden"
                    title="Effacer">
                    <i class="fa-solid fa-xmark text-xs"></i>
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

        // Construire la liste plate des modèles pour le bottom sheet mobile
        this.buildFlatModelsList(allModels);

        // Réattacher les événements
        this.attachModelEvents();

        // Émettre un événement pour signaler que les modèles sont chargés
        document.dispatchEvent(
            new CustomEvent("modelsLoaded", {
                detail: { models: this.models },
            }),
        );

        // Bouton refresh
        document
            .getElementById("refreshModelsBtn")
            ?.addEventListener("click", async (e) => {
                e.stopPropagation();
                this.cache = {}; // Vider le cache mémoire
                localStorage.removeItem(this.localStorageKey); // Vider le cache localStorage
                await this.refreshModelMenu();
            });

        // Recherche desktop
        const searchInput = document.getElementById("desktopModelSearch");
        const clearBtn = document.getElementById("desktopModelSearchClear");

        if (searchInput) {
            searchInput.addEventListener("input", (e) => {
                const value = e.target.value;
                this.filterDesktopModels(value);
                // Afficher/masquer le bouton clear
                if (clearBtn) {
                    clearBtn.classList.toggle("hidden", !value);
                }
            });
            // Empêcher la fermeture du menu lors du clic sur la recherche
            searchInput.addEventListener("click", (e) => {
                e.stopPropagation();
            });
        }

        // Bouton clear de la recherche
        if (clearBtn) {
            clearBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                if (searchInput) {
                    searchInput.value = "";
                    searchInput.focus();
                }
                clearBtn.classList.add("hidden");
                this.filterDesktopModels("");
            });
        }
    },

    /**
     * Filtre les modèles desktop selon la recherche
     */
    filterDesktopModels(query) {
        const q = query.toLowerCase().trim();
        const sections = document.querySelectorAll("#modelMenu .provider-section");

        sections.forEach((section) => {
            const providerName = section.dataset.provider.toLowerCase();
            const providerInfo = this.providers[section.dataset.provider];
            const displayName = providerInfo?.name?.toLowerCase() || "";
            const models = section.querySelectorAll(".model-option");
            let visibleCount = 0;

            if (!q) {
                // Pas de filtre, tout afficher
                section.style.display = "";
                models.forEach((m) => (m.style.display = ""));
                return;
            }

            // Vérifier si le nom du provider correspond
            const providerMatches = providerName.includes(q) || displayName.includes(q);

            models.forEach((model) => {
                const modelId = model.dataset.model?.toLowerCase() || "";
                const modelDisplay = model.dataset.display?.toLowerCase() || "";
                const modelMatches = modelId.includes(q) || modelDisplay.includes(q);

                if (providerMatches || modelMatches) {
                    model.style.display = "";
                    visibleCount++;
                } else {
                    model.style.display = "none";
                }
            });

            // Cacher la section si aucun modèle visible
            section.style.display = visibleCount === 0 ? "none" : "";
        });
    },

    /**
     * Construit une liste plate de tous les modèles (pour le bottom sheet mobile)
     */
    buildFlatModelsList(allModels) {
        this.models = [];
        const seenIds = new Set();

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

        for (const provider of providerOrder) {
            const data = allModels[provider];
            if (data && data.models && data.models.length > 0) {
                for (const model of data.models) {
                    const id = model.id.toLowerCase();
                    if (!seenIds.has(id)) {
                        seenIds.add(id);
                        this.models.push({
                            provider: provider,
                            model: model.id,
                            display: model.name || model.id,
                            description: model.description || "",
                        });
                    }
                }
            }
        }
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

                // Mettre à jour l'affichage desktop
                document.getElementById("modelIcon").src = this.dataset.icon;
                document.getElementById("modelName").textContent =
                    this.dataset.display;

                // Mettre à jour l'affichage mobile
                const mobileModelIcon =
                    document.getElementById("mobileModelIcon");
                const mobileModelName =
                    document.getElementById("mobileModelName");
                if (mobileModelIcon) mobileModelIcon.src = this.dataset.icon;
                if (mobileModelName)
                    mobileModelName.textContent = this.dataset.display;

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
        // Restaurer le cache depuis localStorage
        this.initCache();

        // Charger les modèles au premier clic sur le menu
        const modelSelectorBtn = document.getElementById("modelSelectorBtn");
        let firstOpen = true;

        modelSelectorBtn?.addEventListener("click", async () => {
            if (firstOpen) {
                firstOpen = false;
                await this.refreshModelMenu();
            }
        });

        // Charger les modèles immédiatement pour tous les utilisateurs (connectés ou non)
        this.refreshModelMenu();
    },
};

// Initialiser au chargement de la page
document.addEventListener("DOMContentLoaded", () => {
    ModelManager.init();
});

// Exposer ModelManager globalement pour le bottom sheet mobile
window.modelManager = ModelManager;
