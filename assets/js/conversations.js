/**
 * Gestionnaire d'historique des conversations
 * Permet de sauvegarder, charger et gérer les conversations
 */

class ConversationManager {
    constructor() {
        this.currentConversationId = null;
        this.autoSaveEnabled = true;
        this.pendingMessages = [];
    }

    /**
     * Créer une nouvelle conversation
     */
    async createConversation(title = "Nouvelle conversation") {
        try {
            const response = await fetch(
                "/NxtAIGen/api/conversations.php?action=create",
                {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ title }),
                },
            );

            const data = await response.json();
            if (data.success) {
                this.currentConversationId = data.conversation_id;
                return data.conversation_id;
            }
            throw new Error(data.error || "Erreur lors de la création");
        } catch (error) {
            console.error("Erreur création conversation:", error);
            return null;
        }
    }

    /**
     * Sauvegarder un message dans la conversation courante
     */
    async saveMessage(role, content, model = null, tokensUsed = 0) {
        if (!this.autoSaveEnabled || !this.currentConversationId) {
            return;
        }

        try {
            const response = await fetch(
                "/NxtAIGen/api/conversations.php?action=save_message",
                {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        conversation_id: this.currentConversationId,
                        role,
                        content,
                        model,
                        tokens_used: tokensUsed,
                    }),
                },
            );

            const data = await response.json();
            if (!data.success) {
                console.warn("Message non sauvegardé:", data.error);
            }
        } catch (error) {
            console.error("Erreur sauvegarde message:", error);
        }
    }

    /**
     * Charger toutes les conversations de l'utilisateur
     */
    async loadConversations() {
        try {
            const response = await fetch(
                "/NxtAIGen/api/conversations.php?action=list",
            );
            const data = await response.json();
            if (data.success) {
                return data.conversations;
            }
            return [];
        } catch (error) {
            console.error("Erreur chargement conversations:", error);
            return [];
        }
    }

    /**
     * Charger une conversation spécifique
     */
    async loadConversation(conversationId) {
        try {
            const response = await fetch(
                `/NxtAIGen/api/conversations.php?action=get&id=${conversationId}`,
            );
            const data = await response.json();
            if (data.success) {
                this.currentConversationId = conversationId;
                return data;
            }
            throw new Error(data.error || "Conversation non trouvée");
        } catch (error) {
            console.error("Erreur chargement conversation:", error);
            return null;
        }
    }

    /**
     * Supprimer une conversation
     */
    async deleteConversation(conversationId) {
        try {
            const response = await fetch(
                `/NxtAIGen/api/conversations.php?action=delete&id=${conversationId}`,
                {
                    method: "DELETE",
                },
            );
            const data = await response.json();
            if (data.success && conversationId === this.currentConversationId) {
                this.currentConversationId = null;
            }
            return data.success;
        } catch (error) {
            console.error("Erreur suppression conversation:", error);
            return false;
        }
    }

    /**
     * Mettre à jour le titre d'une conversation
     */
    async updateTitle(conversationId, title) {
        try {
            const response = await fetch(
                "/NxtAIGen/api/conversations.php?action=update_title",
                {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        conversation_id: conversationId,
                        title,
                    }),
                },
            );
            const data = await response.json();
            return data.success;
        } catch (error) {
            console.error("Erreur mise à jour titre:", error);
            return false;
        }
    }

    /**
     * Générer automatiquement un titre basé sur le premier message
     */
    async generateTitle(conversationId) {
        try {
            const response = await fetch(
                `/NxtAIGen/api/conversations.php?action=generate_title&id=${conversationId}`,
            );
            const data = await response.json();
            if (data.success) {
                return data.title;
            }
            return null;
        } catch (error) {
            console.error("Erreur génération titre:", error);
            return null;
        }
    }

    /**
     * Démarrer une nouvelle conversation
     */
    async startNewConversation() {
        const conversationId = await this.createConversation();
        if (conversationId) {
            console.log("Nouvelle conversation créée:", conversationId);
        }
        return conversationId;
    }

    /**
     * Activer/désactiver la sauvegarde automatique
     */
    toggleAutoSave(enabled) {
        this.autoSaveEnabled = enabled;
        localStorage.setItem("nxtgenai_autosave", enabled ? "1" : "0");
    }

    /**
     * Vérifier si la sauvegarde auto est activée
     */
    isAutoSaveEnabled() {
        const saved = localStorage.getItem("nxtgenai_autosave");
        return saved !== "0"; // Par défaut activé
    }
}

// Instance globale
const conversationManager = new ConversationManager();

// Initialiser l'état de l'autosave depuis localStorage
conversationManager.autoSaveEnabled = conversationManager.isAutoSaveEnabled();
