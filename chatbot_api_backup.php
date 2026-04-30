<?php
/**
 * AI Chatbot API Backend
 * Handles communication with OpenAI API and fallback responses
 */

header('Content-Type: application/json');
require_once __DIR__ . '/includes/openai_config.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$user_message = $input['message'] ?? '';
$language = $input['language'] ?? 'auto';
$context = $input['context'] ?? [];

// Validate input
if (empty($user_message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// Detect language if auto
if ($language === 'auto') {
    $language = detectLanguage($user_message);
}

// Get response
if (OPENAI_ENABLED && !empty(OPENAI_API_KEY) && OPENAI_API_KEY !== 'sk-your-api-key-here') {
    $response = getOpenAIResponse($user_message, $language, $context);
} else {
    $response = getFallbackResponse($user_message, $language, $context);
}

echo json_encode([
    'success' => true,
    'response' => $response,
    'language' => $language,
    'mode' => OPENAI_ENABLED ? 'openai' : 'fallback'
]);

/**
 * Get response from OpenAI API
 */
function getOpenAIResponse($message, $language, $context) {
    // Build context message
    $context_message = "Current System Data:\n";
    $context_message .= "- Monastery: " . $context['monastery_name'] . "\n";
    $context_message .= "- Total Donations: " . $context['total_donations'] . "\n";
    $context_message .= "- Number of Donations: " . $context['donation_count'] . "\n";
    $context_message .= "- Active Monks: " . $context['monk_count'] . "\n";
    $context_message .= "- Active Doctors: " . $context['doctor_count'] . "\n";
    $context_message .= "- Donation Categories: " . implode(', ', $context['donation_categories']) . "\n";
    $context_message .= "- Payment Methods: " . implode(', ', $context['payment_methods']) . "\n";
    
    if ($language === 'si') {
        $context_message .= "\nPlease respond in Sinhala (සිංහල).";
    }
    
    // Prepare API request
    $data = [
        'model' => OPENAI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => SYSTEM_PROMPT . "\n\n" . $context_message
            ],
            [
                'role' => 'user',
                'content' => $message
            ]
        ],
        'max_tokens' => OPENAI_MAX_TOKENS,
        'temperature' => OPENAI_TEMPERATURE
    ];
    
    // Make API request
    $ch = curl_init(OPENAI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';
    } else {
        error_log("OpenAI API Error: " . $response);
        return getFallbackResponse($message, $language, $context);
    }
}

/**
 * Get fallback rule-based response
 */
function getFallbackResponse($message, $language, $context) {
    $message_lower = strtolower($message);
    
    // Sinhala responses
    if ($language === 'si') {
        // Donation questions in Sinhala
        if (preg_match('/පරිත්‍යාග|දන්|ගෙවන්න/', $message_lower)) {
            return "ඔබට පරිත්‍යාග කිරීමට ක්‍රම තුනක් තිබේ:\n\n" .
                   "1. **සජීවීව** - පන්සලට පැමිණ මුදල් ලබා දෙන්න\n" .
                   "2. **බැංකු ගිණුමට** - අපගේ බැංකු ගිණුමට මුදල් මාරු කරන්න\n" .
                   "3. **ඔන්ලයින්** - PayHere හරහා ඕනෑම කාඩ් පතක් භාවිතා කරන්න\n\n" .
                   "සියලු පරිත්‍යාග සදහා රිසිට් පත් ලබා දෙනු ලැබේ.\n\n" .
                   "ත්‍රිවිධ රත්නයේ ආශීර්වාදය ඔබ සමග වේවා! 🙏";
        }
        
        if (preg_match('/ප්‍රවර්ග|කාණ්ඩ/', $message_lower)) {
            return "අප සතුව පරිත්‍යාග ප්‍රවර්ග කිහිපයක් ඇත:\n\n" .
                   implode("\n", array_map(fn($cat) => "• " . $cat, $context['donation_categories'])) . "\n\n" .
                   "ඔබට කැමති ප්‍රවර්ගයක් තෝරාගෙන පරිත්‍යාග කළ හැක.\n\n" .
                   "තෙරුවන් සරණයි! 🪷";
        }
        
        if (preg_match('/වෛද්‍ය|ඖෂධ|ප්‍රතිකාර/', $message_lower)) {
            return "අපගේ පන්සලේ සෞඛ්‍ය සේවා:\n\n" .
                   "• වෛද්‍යවරුන්: " . $context['doctor_count'] . "\n" .
                   "• ලියාපදිංචි භික්ෂූන් වහන්ස: " . $context['monk_count'] . "\n" .
                   "• වෛද්‍ය පරීක්‍ෂණ හා ප්‍රතිකාර\n" .
                   "• ඖෂධ සහ උපදේශන\n\n" .
                   "දින පුරාම නිරෝගී සුව ලැබේවා! 🙏";
        }
        
        // Default Sinhala response
        return "සමාවන්න, මට ඔබගේ ප්‍රශ්නය නිවැරදිව තේරුම් ගත නොහැකි විය.\n\n" .
               "කරුණාකර වෙනත් ආකාරයකින් අසන්න හෝ මෙම කරුණු ගැන විමසන්න:\n" .
               "• පරිත්‍යාග කරන්නේ කෙසේද?\n" .
               "• ගෙවීම් ක්‍රම මොනවාද?\n" .
               "• වෛද්‍ය සේවා ගැන\n\n" .
               "ත්‍රිවිධ රත්නයේ ආශීර්වාදය ඔබ සමග වේවා! 🪷";
    }
    
    // English responses
    
    // Donation questions
    if (preg_match('/donate|donation|give|contribute/', $message_lower)) {
        return "**How to Make a Donation:**\n\n" .
               "You have three convenient options:\n\n" .
               "1. **In Person** - Visit the monastery and donate directly\n" .
               "2. **Bank Transfer** - Transfer to our bank account\n" .
               "3. **Online Payment** - Use PayHere with any credit/debit card\n\n" .
               "All donations receive official receipts for tax purposes.\n\n" .
               "Current total donations: **" . $context['total_donations'] . "** from " . $context['donation_count'] . " generous donors.\n\n" .
               "May you be blessed for your generosity! 🙏";
    }
    
    // Payment methods
    if (preg_match('/payment|pay|method|how to pay/', $message_lower)) {
        return "**Available Payment Methods:**\n\n" .
               implode("\n", array_map(fn($method) => "• **" . $method . "**", $context['payment_methods'])) . "\n\n" .
               "For online payments via PayHere, we accept:\n" .
               "• Visa and MasterCard\n" .
               "• All major debit cards\n" .
               "• Secure 3D authentication\n\n" .
               "All transactions are encrypted and secure.\n\n" .
               "Theruwan Saranai! 🪷";
    }
    
    // Donation categories
    if (preg_match('/categor|type|purpose|use/', $message_lower)) {
        return "**Donation Categories:**\n\n" .
               implode("\n", array_map(fn($cat) => "• " . $cat, $context['donation_categories'])) . "\n\n" .
               "Your donations help us:\n" .
               "• Provide healthcare services to monks and community\n" .
               "• Maintain medical facilities and equipment\n" .
               "• Support monastery operations\n" .
               "• Spread Dhamma teachings\n\n" .
               "Every contribution makes a difference! 🙏";
    }
    
    // Receipt questions
    if (preg_match('/receipt|tax|deduct/', $message_lower)) {
        return "**Donation Receipts:**\n\n" .
               "Yes! We provide official receipts for all donations:\n\n" .
               "• PDF receipts available for download\n" .
               "• Sent via email automatically\n" .
               "• Include all donation details\n" .
               "• Can be used for tax deductions\n\n" .
               "Please consult your tax advisor for eligibility.\n\n" .
               "May the Triple Gem bless you! 🪷";
    }
    
    // Healthcare questions
    if (preg_match('/health|doctor|medical|appointment|treatment/', $message_lower)) {
        return "**Healthcare Services:**\n\n" .
               "Our monastery provides comprehensive healthcare:\n\n" .
               "• **Active Doctors:** " . $context['doctor_count'] . "\n" .
               "• **Registered Monks:** " . $context['monk_count'] . "\n" .
               "• Medical consultations\n" .
               "• Treatments and prescriptions\n" .
               "• Appointment booking system\n\n" .
               "Contact us to schedule an appointment.\n\n" .
               "May you be blessed with good health! 🙏";
    }
    
    // Statistics questions
    if (preg_match('/how many|total|stats|statistic/', $message_lower)) {
        return "**System Statistics:**\n\n" .
               "• **Total Donations:** " . $context['total_donations'] . "\n" .
               "• **Number of Donors:** " . $context['donation_count'] . "\n" .
               "• **Active Monks:** " . $context['monk_count'] . "\n" .
               "• **Active Doctors:** " . $context['doctor_count'] . "\n\n" .
               "Thank you to all our generous supporters!\n\n" .
               "Theruwan Saranai! 🪷";
    }
    
    // Greeting
    if (preg_match('/hello|hi|hey|good morning|good evening/', $message_lower)) {
        return "Hello! Welcome to " . $context['monastery_name'] . "!\n\n" .
               "I'm here to help you with:\n" .
               "• Donation information and processes\n" .
               "• Payment methods and procedures\n" .
               "• Healthcare services available\n" .
               "• General monastery information\n\n" .
               "How can I assist you today?\n\n" .
               "Theruwan Saranai! 🙏";
    }
    
    // Thank you
    if (preg_match('/thank|thanks|appreciate/', $message_lower)) {
        return "You're very welcome! 🙏\n\n" .
               "May your generosity bring you happiness and peace.\n\n" .
               "\"Dānaṃ dadanti saddhāya\" - Giving with faith brings great merit.\n\n" .
               "If you have any other questions, feel free to ask!\n\n" .
               "Theruwan Saranai! 🪷";
    }
    
    // Default response
    return "I'm here to help! I can answer questions about:\n\n" .
           "• **Donations** - How to donate, payment methods, categories\n" .
           "• **Receipts** - Tax deductions, PDF downloads\n" .
           "• **Healthcare** - Medical services, appointments, doctors\n" .
           "• **Monastery** - General information and statistics\n\n" .
           "Please ask me anything about these topics!\n\n" .
           "You can also ask in Sinhala (සිංහල). 🙏\n\n" .
           "Theruwan Saranai! 🪷";
}

/**
 * Detect language from message
 */
function detectLanguage($message) {
    // Check for Sinhala Unicode characters
    if (preg_match('/[\x{0D80}-\x{0DFF}]/u', $message)) {
        return 'si';
    }
    return 'en';
}

/**
 * Log chat interactions (for analytics)
 */
function logChat($message, $response, $language) {
    $conn = new mysqli("localhost", "root", "", "monastery_healthcare");
    if ($conn->connect_error) {
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO chat_logs (user_message, bot_response, language, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("sss", $message, $response, $language);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}
?>
