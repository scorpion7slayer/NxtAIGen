<?php

/**
 * Configuration des clés API pour tous les providers
 * 
 * PROVIDERS DISPONIBLES:
 * - OpenAI: https://platform.openai.com/api-keys
 * - Anthropic (Claude): https://console.anthropic.com/
 * - Ollama: https://ollama.com/
 * - DeepSeek: https://platform.deepseek.com/
 * - Google Gemini: https://makersuite.google.com/app/apikey
 * - Mistral AI: https://console.mistral.ai/
 * - Hugging Face: https://huggingface.co/settings/tokens
 * - OpenRouter: https://openrouter.ai/keys
 * - Perplexity: https://www.perplexity.ai/settings/api
 * - xAI (Grok): https://console.x.ai/
 * - Moonshot (Kimi): https://platform.moonshot.cn/
 */

return [
  // === OPTIONS GLOBALES ===
  // Désactiver la vérification SSL (pour WAMP sans CA bundle configuré)
  'SKIP_SSL_VERIFY' => true,

  // === PROVIDERS CONFIGURÉS ===
  'OLLAMA_API_KEY' => '4203f4b766174f2183a0a7a90db21dd9.o511iDtjX3HS2LuFa2gPx8cp',
  'OLLAMA_API_URL' => 'https://ia-api-oollama.serverscorpion1601.site',
  'OPENAI_API_KEY' => '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',
  'ANTHROPIC_API_KEY' => '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',

  // DeepSeek - https://platform.deepseek.com/api-keys
  'DEEPSEEK_API_KEY' => 'sk-3532a68fb5b747c59bbca4ed625ec1de',

  // Google Gemini - https://makersuite.google.com/app/apikey
  'GEMINI_API_KEY' => '',

  // Mistral AI - https://console.mistral.ai/api-keys
  'MISTRAL_API_KEY' => 'bMloRMCUEpZPc9lwZ2EOT7Xhhbz2DPAQ',

  // Hugging Face - https://huggingface.co/settings/tokens
  'HUGGINGFACE_API_KEY' => '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',

  // OpenRouter - https://openrouter.ai/keys
  'OPENROUTER_API_KEY' => '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',

  // Perplexity - https://www.perplexity.ai/settings/api
  'PERPLEXITY_API_KEY' => '',

  // xAI (Grok) - https://console.x.ai/
  'XAI_API_KEY' => 'xai-q5RkXyf7qkdntKqYalCbq8HWi0XDwEXYy9a4Di46Uq8sZQlTfDK8qHx07Tboj9WtFPKvfQTIM2a65dMV',

  // Moonshot (Kimi) - https://platform.moonshot.cn/console/api-keys
  'MOONSHOT_API_KEY' => 'sk-GsDxYiUbQtE9aVIIZ5ekRmy7zeBnNbvFObyWR2edKd4LRBx3',

];
