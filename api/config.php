<?php

require_once __DIR__ . '/env_loader.php';

return [
  'SKIP_SSL_VERIFY' => env('SKIP_SSL_VERIFY', true),

  'OLLAMA_API_KEY'      => env('OLLAMA_API_KEY', ''),
  'OLLAMA_API_URL'      => env('OLLAMA_API_URL', 'http://localhost:11434'),
  'OPENAI_API_KEY'      => env('OPENAI_API_KEY', ''),
  'ANTHROPIC_API_KEY'   => env('ANTHROPIC_API_KEY', ''),
  'DEEPSEEK_API_KEY'    => env('DEEPSEEK_API_KEY', ''),
  'GEMINI_API_KEY'      => env('GEMINI_API_KEY', ''),
  'MISTRAL_API_KEY'     => env('MISTRAL_API_KEY', ''),
  'HUGGINGFACE_API_KEY' => env('HUGGINGFACE_API_KEY', ''),
  'OPENROUTER_API_KEY'  => env('OPENROUTER_API_KEY', ''),
  'PERPLEXITY_API_KEY'  => env('PERPLEXITY_API_KEY', ''),
  'XAI_API_KEY'         => env('XAI_API_KEY', ''),
  'MOONSHOT_API_KEY'    => env('MOONSHOT_API_KEY', ''),
];
