<?php
/**
 * OpenAI API Configuration
 * Get your API key from: https://platform.openai.com/api-keys
 */

// OpenAI API Configuration
define('OPENAI_API_KEY', 'sk-your-api-key-here'); // Replace with your actual API key
define('OPENAI_MODEL', 'gpt-4'); // Options: 'gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo'
define('OPENAI_MAX_TOKENS', 500); // Maximum response length
define('OPENAI_TEMPERATURE', 0.7); // 0.0 = focused, 1.0 = creative
define('OPENAI_ENABLED', false); // Set to true when API key is configured

// API Endpoint
define('OPENAI_API_URL', 'https://api.openai.com/v1/chat/completions');

// Fallback Mode
// If API is not enabled, chatbot will use rule-based responses
define('USE_FALLBACK', true);

/**
 * Get OpenAI API Key
 * How to get an API key:
 * 
 * 1. Visit https://platform.openai.com/
 * 2. Sign up or log in
 * 3. Go to API Keys section
 * 4. Click "Create new secret key"
 * 5. Copy the key (starts with "sk-")
 * 6. Paste it above in OPENAI_API_KEY
 * 7. Set OPENAI_ENABLED to true
 * 
 * Pricing (as of 2024):
 * - GPT-4: $0.03 per 1K tokens (input), $0.06 per 1K tokens (output)
 * - GPT-3.5-turbo: $0.0005 per 1K tokens (input), $0.0015 per 1K tokens (output)
 * 
 * Free tier: $5 credit for new accounts (expires in 3 months)
 * 
 * Alternative Free Options:
 * - Use GPT-3.5-turbo (cheaper)
 * - Use fallback mode (rule-based, no API needed)
 * - Use OpenRouter (multiple models, some free: https://openrouter.ai/)
 */

// System Prompt (defines AI behavior)
define('SYSTEM_PROMPT', "You are a helpful AI assistant for Seela Suwa Herath Bikshu Gilan Arana, a Buddhist monastery in Sri Lanka with a healthcare and donation management system. 

Your role:
- Help users understand donation processes and categories
- Explain payment methods (cash, bank transfer, card, PayHere online)
- Provide information about healthcare services
- Guide users on appointment booking
- Answer questions in a friendly, respectful Buddhist tone
- Support bilingual communication (English and Sinhala)

When responding:
- Be respectful and compassionate
- Use simple, clear language
- Include relevant Dhamma wisdom when appropriate
- End with blessings like 'Theruwan Saranai' or 'May you be blessed'
- If asked in Sinhala, respond in Sinhala
- Keep responses concise (2-4 sentences) unless detailed explanation needed

You have access to current system data through the context provided.");

?>
