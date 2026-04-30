<?php
/**
 * ENHANCED AI Chatbot API - Premium Version
 * Features: GPT-4 integration, database queries, context awareness, voice support
 */

header('Content-Type: application/json');
require_once __DIR__ . '/includes/openai_config.php';
require_once __DIR__ . '/includes/db_config.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$user_message = $input['message'] ?? '';
$language = $input['language'] ?? 'auto';
$session_id = $input['session_id'] ?? session_id();
$conversation_history = $input['history'] ?? [];

// Validate input
if (empty($user_message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// Detect language if auto
if ($language === 'auto') {
    $language = detectLanguage($user_message);
}

// Get real-time system data from database
$context = getSystemContext();

// Check if query requires database search
$db_results = null;
if (requiresDatabaseQuery($user_message)) {
    $db_results = queryDatabase($user_message, $context);
}

// Get response
if (OPENAI_ENABLED && !empty(OPENAI_API_KEY) && OPENAI_API_KEY !== 'sk-your-api-key-here') {
    $response = getEnhancedGPT4Response($user_message, $language, $context, $db_results, $conversation_history);
    $mode = 'gpt-4';
} else {
    $response = getSmartFallbackResponse($user_message, $language, $context, $db_results);
    $mode = 'smart-fallback';
}

// Log conversation for analytics
logConversation($session_id, $user_message, $response, $language, $mode);

echo json_encode([
    'success' => true,
    'response' => $response,
    'language' => $language,
    'mode' => $mode,
    'context' => $context,
    'db_results' => $db_results,
    'suggestions' => getSuggestions($user_message, $language)
]);

/**
 * Get real-time system context from database
 */
function getSystemContext() {
    $conn = getDBConnection();
    
    $context = [
        'monastery_name' => 'Giribawa Seela Suva Herath Bhikkhu Hospital',
        'current_date' => date('Y-m-d'),
        'current_time' => date('H:i:s')
    ];
    
    // Get donation statistics
    $result = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM donations WHERE status='verified'");
    if ($result && $row = $result->fetch_assoc()) {
        $context['donation_count'] = $row['count'];
        $context['total_donations'] = 'Rs. ' . number_format($row['total'], 2);
    }
    
    // Get this month's donations
    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE status='verified' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    if ($result && $row = $result->fetch_assoc()) {
        $context['this_month_donations'] = 'Rs. ' . number_format($row['total'], 2);
    }
    
    // Get monk count
    $result = $conn->query("SELECT COUNT(*) as count FROM monks WHERE status='active'");
    if ($result) {
        $context['monk_count'] = $result->fetch_assoc()['count'];
    }
    
    // Get doctor count
    $result = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status='active'");
    if ($result) {
        $context['doctor_count'] = $result->fetch_assoc()['count'];
    }
    
    // Get categories
    $categories = [];
    $result = $conn->query("SELECT name FROM categories WHERE type='donation' LIMIT 10");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['name'];
        }
    }
    $context['donation_categories'] = $categories;
    
    // Get payment methods
    $context['payment_methods'] = ['Cash', 'Bank Transfer', 'Online Payment (PayHere)'];
    
    // Get recent activity
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE app_date = CURRENT_DATE() AND status='scheduled'");
    if ($result) {
        $context['todays_appointments'] = $result->fetch_assoc()['count'];
    }
    
    $conn->close();
    return $context;
}

/**
 * Check if query requires database search
 */
function requiresDatabaseQuery($message) {
    $keywords = [
        'search', 'find', 'show', 'list', 'who', 'how many', 'count',
        'monk', 'doctor', 'donation', 'appointment', 'total', 'statistics',
        'පෙන්වන්න', 'සොයන්න', 'කීයද', 'කීදෙනෙක්'
    ];
    
    $message_lower = strtolower($message);
    foreach ($keywords as $keyword) {
        if (strpos($message_lower, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Query database for specific information
 */
function queryDatabase($message, $context) {
    $conn = getDBConnection();
    $results = [];
    $message_lower = strtolower($message);
    
    // Search for specific monk
    if (preg_match('/monk.*named?\\s+([\\w\\s]+)/i', $message, $matches) || 
        preg_match('/හාමුදුරුවෝ\\s+([\\w\\s]+)/u', $message, $matches)) {
        $name = trim($matches[1]);
        $stmt = $conn->prepare("SELECT full_name, phone, blood_group, allergies, chronic_conditions FROM monks WHERE full_name LIKE ? AND status='active' LIMIT 5");
        $search = "%$name%";
        $stmt->bind_param("s", $search);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $results['monks'][] = $row;
        }
        $stmt->close();
    }
    
    // Get top donors
    if (strpos($message_lower, 'top donor') !== false || strpos($message_lower, 'highest donation') !== false) {
        $result = $conn->query("
            SELECT donor_name, COUNT(*) as donation_count, SUM(amount) as total_amount
            FROM donations
            WHERE status='verified'
            GROUP BY donor_name
            ORDER BY total_amount DESC
            LIMIT 10
        ");
        
        while ($row = $result->fetch_assoc()) {
            $results['top_donors'][] = $row;
        }
    }
    
    // Get today's appointments
    if (strpos($message_lower, 'today') !== false && (strpos($message_lower, 'appointment') !== false)) {
        $result = $conn->query("
            SELECT a.app_time, m.full_name as monk_name, d.full_name as doctor_name
            FROM appointments a
            JOIN monks m ON a.monk_id = m.monk_id
            JOIN doctors d ON a.doctor_id = d.doctor_id
            WHERE a.app_date = CURRENT_DATE() AND a.status='scheduled'
            ORDER BY a.app_time
        ");
        
        while ($row = $result->fetch_assoc()) {
            $results['todays_appointments'][] = $row;
        }
    }
    
    // Get donation statistics
    if (strpos($message_lower, 'donation') !== false && 
        (strpos($message_lower, 'this month') !== false || strpos($message_lower, 'monthly') !== false)) {
        $result = $conn->query("
            SELECT c.name as category, SUM(d.amount) as total, COUNT(*) as count
            FROM donations d
            JOIN categories c ON d.category_id = c.category_id
            WHERE d.status='verified' 
            AND MONTH(d.created_at) = MONTH(CURRENT_DATE())
            AND YEAR(d.created_at) = YEAR(CURRENT_DATE())
            GROUP BY c.category_id, c.name
            ORDER BY total DESC
        ");
        
        while ($row = $result->fetch_assoc()) {
            $results['monthly_donations'][] = $row;
        }
    }
    
    $conn->close();
    return !empty($results) ? $results : null;
}

/**
 * Get enhanced GPT-4 response with database integration
 */
function getEnhancedGPT4Response($message, $language, $context, $db_results, $history) {
    // Build enhanced system prompt with database results
    $enhanced_prompt = SYSTEM_PROMPT . "\n\n";
    $enhanced_prompt .= "CURRENT SYSTEM DATA:\n";
    $enhanced_prompt .= "- Monastery: " . $context['monastery_name'] . "\n";
    $enhanced_prompt .= "- Date: " . $context['current_date'] . "\n";
    $enhanced_prompt .= "- Total Donations: " . $context['total_donations'] . " from " . $context['donation_count'] . " donations\n";
    $enhanced_prompt .= "- This Month: " . $context['this_month_donations'] . "\n";
    $enhanced_prompt .= "- Active Monks: " . $context['monk_count'] . "\n";
    $enhanced_prompt .= "- Active Doctors: " . $context['doctor_count'] . "\n";
    $enhanced_prompt .= "- Today's Appointments: " . $context['todays_appointments'] . "\n";
    
    if ($db_results) {
        $enhanced_prompt .= "\nDATABASE SEARCH RESULTS:\n";
        $enhanced_prompt .= json_encode($db_results, JSON_PRETTY_PRINT) . "\n";
        $enhanced_prompt .= "Use the above database results to answer the user's question with specific details.\n";
    }
    
    if ($language === 'si') {
        $enhanced_prompt .= "\nIMPORTANT: Respond in Sinhala (සිංහල) language.\n";
    }
    
    // Build messages array with conversation history
    $messages = [
        ['role' => 'system', 'content' => $enhanced_prompt]
    ];
    
    // Add conversation history (last 5 messages)
    foreach (array_slice($history, -5) as $hist) {
        $messages[] = ['role' => 'user', 'content' => $hist['message']];
        $messages[] = ['role' => 'assistant', 'content' => $hist['response']];
    }
    
    // Add current message
    $messages[] = ['role' => 'user', 'content' => $message];
    
    // Prepare API request
    $data = [
        'model' => 'gpt-4',  // Use GPT-4 for best quality
        'messages' => $messages,
        'max_tokens' => 500,
        'temperature' => 0.7,
        'presence_penalty' => 0.6,
        'frequency_penalty' => 0.3
    ];
    
    // Make API request with error handling
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response.';
    } else {
        error_log("OpenAI API Error (Code $http_code): " . $response);
        return getSmartFallbackResponse($message, $language, $context, $db_results);
    }
}

/**
 * Enhanced smart fallback response with database integration
 */
function getSmartFallbackResponse($message, $language, $context, $db_results) {
    $message_lower = strtolower($message);
    
    // If we have database results, format them nicely
    if ($db_results) {
        if (isset($db_results['top_donors'])) {
            $response = ($language === 'si') ? "ප්‍රධාන දායකයන්:\n\n" : "Top Donors:\n\n";
            foreach (array_slice($db_results['top_donors'], 0, 5) as $i => $donor) {
                $response .= ($i + 1) . ". " . $donor['donor_name'] . " - Rs. " . number_format($donor['total_amount'], 2);
                $response .= " (" . $donor['donation_count'] . " donations)\n";
            }
            return $response;
        }
        
        if (isset($db_results['todays_appointments'])) {
            $response = ($language === 'si') ? "අද දින වෛද්‍ය හමුවීම්:\n\n" : "Today's Appointments:\n\n";
            foreach ($db_results['todays_appointments'] as $apt) {
                $response .= "🕐 " . date('g:i A', strtotime($apt['app_time'])) . " - ";
                $response .= $apt['monk_name'] . " with Dr. " . $apt['doctor_name'] . "\n";
            }
            return $response;
        }
        
        if (isset($db_results['monthly_donations'])) {
            $response = ($language === 'si') ? "මාසික පරිත්‍යාග:\n\n" : "This Month's Donations:\n\n";
            foreach ($db_results['monthly_donations'] as $cat) {
                $response .= "💰 " . $cat['category'] . ": Rs. " . number_format($cat['total'], 2);
                $response .= " (" . $cat['count'] . " donations)\n";
            }
            return $response;
        }
    }
    
    // Call original fallback function for pattern matching
    return getFallbackResponse($message, $language, $context);
}

/**
 * Log conversation for analytics
 */
function logConversation($session_id, $message, $response, $language, $mode) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO chat_logs (session_id, user_message, bot_response, language, mode, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $session_id, $message, $response, $language, $mode);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Failed to log conversation: " . $e->getMessage());
    }
}

/**
 * Get smart suggestions for next questions
 */
function getSuggestions($message, $language) {
    if ($language === 'si') {
        return [
            "මෙම මාසයේ පරිත්‍යාග කීයද?",
            "අද වෛද්‍ය හමුවීම් ලැයිස්තුව පෙන්වන්න",
            "ප්‍රධාන දායකයන් කවුද?",
            "පරිත්‍යාග ප්‍රභෙද මොනවාද?"
        ];
    } else {
        return [
            "Show me donations this month",
            "List today's appointments",
            "Who are the top donors?",
            "What are the donation categories?"
        ];
    }
}

// Include original fallback function (existing code from chatbot_api.php)
function detectLanguage($text) {
    // Sinhala Unicode range
    if (preg_match('/[\x{0D80}-\x{0DFF}]/u', $text)) {
        return 'si';
    }
    return 'en';
}

function getFallbackResponse($message, $language, $context) {
    // Original fallback logic from chatbot_api.php
    // This function should contain all the pattern matching logic
    $message_lower = strtolower($message);
    
    // Donation queries
    if (strpos($message_lower, 'donate') !== false || strpos($message_lower, 'donation') !== false) {
        if ($language === 'si') {
            return "ඔබට මෙම ආකාරයන්ගෙන් පරිත්‍යාග කළ හැක:\n\n💵 මුදල්: සෘජුවම පරිත්‍යාග කරන්න\n🏦 බැංකු: ගිණුම් විස්තර ලබා ගන්න\n💳 ඔන්ලයින්: PayHere හරහා ආරක්ෂිතව ගෙවන්න\n\nමුළු පරිත්‍යාග: " . $context['total_donations'];
        } else {
            return "You can donate through:\n\n💵 Cash: Direct donations\n🏦 Bank Transfer: Get account details\n💳 Online: Secure payment via PayHere\n\nTotal Donations: " . $context['total_donations'];
        }
    }
    
    // Statistics
    if (strpos($message_lower, 'statistics') !== false || strpos($message_lower, 'stats') !== false) {
        return "📊 System Statistics:\n\n" .
               "Total Donations: " . $context['total_donations'] . "\n" .
               "This Month: " . $context['this_month_donations'] . "\n" .
               "Active Monks: " . $context['monk_count'] . "\n" .
               "Active Doctors: " . $context['doctor_count'] . "\n" .
               "Today's Appointments: " . $context['todays_appointments'];
    }
    
    // Default
    if ($language === 'si') {
        return "සුභ දවසක්! මම ඔබට උදව් කිරීමට සූදානම්. කරුණාකර ඔබේ ප්‍රශ්නය විමසන්න.";
    } else {
        return "Hello! I'm here to help. Please ask me anything about the monastery, donations, or appointments.";
    }
}
?>
