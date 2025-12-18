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
  // === PROVIDERS CONFIGURÉS ===
  'OLLAMA_API_KEY' => 'REDACTED_OLLAMA_API_KEY',
  'OLLAMA_API_URL' => 'https://REDACTED_OLLAMA_SERVER_URL',
  'OPENAI_API_KEY' => 'REDACTED_OPENAI_API_KEY',
  'ANTHROPIC_API_KEY' => 'REDACTED_ANTHROPIC_API_KEY',

  // DeepSeek - https://platform.deepseek.com/api-keys
  'DEEPSEEK_API_KEY' => 'REDACTED_DEEPSEEK_API_KEY',

  // Google Gemini - https://makersuite.google.com/app/apikey
  'GEMINI_API_KEY' => '',

  // Mistral AI - https://console.mistral.ai/api-keys
  'MISTRAL_API_KEY' => 'REDACTED_MISTRAL_API_KEY',

  // Hugging Face - https://huggingface.co/settings/tokens
  'HUGGINGFACE_API_KEY' => 'REDACTED_HUGGINGFACE_API_KEY',

  // OpenRouter - https://openrouter.ai/keys
  'OPENROUTER_API_KEY' => 'REDACTED_OPENROUTER_API_KEY',

  // Perplexity - https://www.perplexity.ai/settings/api
  'PERPLEXITY_API_KEY' => '',

  // xAI (Grok) - https://console.x.ai/
  'XAI_API_KEY' => 'REDACTED_XAI_API_KEY',

  // Moonshot (Kimi) - https://platform.moonshot.cn/console/api-keys
  'MOONSHOT_API_KEY' => 'REDACTED_MOONSHOT_API_KEY',

];
