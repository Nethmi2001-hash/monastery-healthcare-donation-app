<?php
/**
 * Google Gemini API Configuration
 * Get your API key from: https://aistudio.google.com/apikey
 */

// Gemini API Configuration
define('GEMINI_API_KEY', 'AIzaSyCNPyTzvtrD0D66HoG-pVTJHaL_syH8O84');
define('GEMINI_MODEL', 'gemini-pro');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent');
define('GEMINI_ENABLED', true);

// Fallback to rule-based system if API fails
define('USE_RULE_BASE_FALLBACK', true);

/**
 * Get Gemini API Key
 * How to get an API key:
 * 
 * 1. Visit https://aistudio.google.com/apikey
 * 2. Click "Create API Key"
 * 3. Select or create a project
 * 4. Copy the API key
 * 5. Paste it in GEMINI_API_KEY above
 * 6. Set GEMINI_ENABLED to true
 * 
 * Pricing:
 * - Free tier: 60 requests per minute
 * - Gemini 1.5 Pro/Flash available with higher rates
 * - No credit card required for free tier
 */

/**
 * Call Gemini API
 */
function callGeminiAPI($message, $context = []) {
    if (!GEMINI_ENABLED) {
        return null;
    }
    
    // Build context for Gemini
    $systemPrompt = buildGeminiSystemPrompt($context);
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $systemPrompt . "\n\nUser: " . $message]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 500,
            'topP' => 0.95,
            'topK' => 40
        ]
    ];
    
    try {
        $url = GEMINI_API_URL . '?key=' . GEMINI_API_KEY;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }
        }
    } catch (Exception $e) {
        error_log("Gemini API Error: " . $e->getMessage());
    }
    
    return null;
}

/**
 * Build system prompt with context
 */
function buildGeminiSystemPrompt($context = []) {
    $prompt = "You are a helpful AI assistant for Seela Suwa Herath Monastery in Sri Lanka.

Your role:
- Help users understand donation processes
- Explain payment methods (cash, bank transfer, card, PayHere)
- Provide information about healthcare services
- Answer questions about monks and doctors
- Maintain transparency about fund usage
- Support both English and Sinhala languages
- Be respectful and compassionate";

    if (!empty($context)) {
        $prompt .= "\n\nCurrent System Information:
- Total Donations: " . ($context['total_donations'] ?? 'N/A') . "
- Active Monks: " . ($context['monk_count'] ?? 'N/A') . "
- Active Doctors: " . ($context['doctor_count'] ?? 'N/A') . "
- Today's Appointments: " . ($context['todays_appointments'] ?? 'N/A');
    }

    return $prompt;
}
?>
