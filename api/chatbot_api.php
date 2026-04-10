<?php
require_once __DIR__ . '/../includes/init.php';
/**
 * AI CHATBOT - MONASTERY DONATION ASSISTANT
 * Bilingual: English & Sinhala
 * Powered by Google Gemini API + Rule-Based Fallback
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/gemini_config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$language = $input['language'] ?? 'auto';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message required']);
    exit;
}

if ($language === 'auto') {
    $language = preg_match('/[\x{0D80}-\x{0DFF}]/u', $message) ? 'si' : 'en';
}

$context = getSystemContext();

// Try Gemini API first
$response = null;
$mode = 'rule-based'; // Default mode

if (defined('GEMINI_ENABLED') && GEMINI_ENABLED) {
    $response = callGeminiAPI($message, $context);
    if ($response) {
        $mode = 'gemini-ai';
    }
}

// Fallback to rule-based system if Gemini fails or is disabled
if (!$response) {
    $response = processQuery($message, $language, $context);
    $mode = 'rule-based';
}

logConversation($message, $response, $language);

echo json_encode([
    'success' => true,
    'response' => $response,
    'language' => $language,
    'mode' => $mode,
    'suggestions' => getSuggestions($language)
]);

/**
 * Route query to appropriate handler
 */
function processQuery($msg, $lang, $ctx) {
    $clean = strtolower(preg_replace('/[^\w\s]/', '', $msg));
    
    // Check for donation-related keywords
    if (matchKeywords($clean, ['donate', 'donation', 'give', 'sponsor', 'contribute'])) {
        return handleDonationQuery($lang, $ctx);
    }
    // Check for medical keywords
    if (matchKeywords($clean, ['doctor', 'health', 'appointment', 'medical', 'consult'])) {
        return handleMedicalQuery($lang, $ctx);
    }
    // Check for statistics keywords
    if (matchKeywords($clean, ['stat', 'total', 'how many', 'count', 'number'])) {
        return handleStatisticsQuery($lang, $ctx);
    }
    // Check for category keywords
    if (matchKeywords($clean, ['category', 'where', 'money', 'spent', 'used'])) {
        return handleCategoryQuery($lang);
    }
    // Check for monk keywords
    if (matchKeywords($clean, ['monk', 'bhikkhu', 'community'])) {
        return handleMonkQuery($lang, $ctx);
    }
    // Check for payment keywords
    if (matchKeywords($clean, ['payment', 'bank', 'cash', 'pay', 'transfer'])) {
        return handlePaymentQuery($lang);
    }
    // Check for transparency keywords
    if (matchKeywords($clean, ['transparency', 'report', 'audit', 'fund'])) {
        return handleTransparencyQuery($lang);
    }
    // Check for greeting keywords
    if (matchKeywords($clean, ['hello', 'hi', 'hey', 'help', 'greet'])) {
        return getWelcomeMessage($lang);
    }
    
    return getDefaultResponse($lang);
}

/**
 * Helper function to match multiple keywords
 */
function matchKeywords($text, $keywords) {
    foreach ($keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

function handleDonationQuery($lang, $ctx) {
    if ($lang === 'si') {
        return "🙏 **පරිත්‍යාග ගැන**\n\n" .
               "✅ **සෙවන්දි** - පරිපාලන කාර්යාලයට\n" .
               "✅ **බැංකුව** - People's Bank 123-456-789-0\n" .
               "✅ **ඔන්ලයින්** - PayHere ගිණුම\n\n" .
               "💰 **මුළු පරිත්‍යාග:** " . $ctx['total_donations'] . "\n" .
               "📦 **මෙම මාසය:** " . $ctx['this_month_donations'] . "\n\n" .
               "🙏 ස්වර්ගීය ස්තූතියි!";
    } else {
        return "🙏 **How to Donate**\n\n" .
               "✅ **Cash** - Visit office\n" .
               "✅ **Bank Transfer** - People's Bank 123-456-789-0\n" .
               "✅ **Online** - PayHere secure\n\n" .
               "💰 **Total:** " . $ctx['total_donations'] . "\n" .
               "📦 **This Month:** " . $ctx['this_month_donations'] . "\n\n" .
               "🙏 Thank you!";
    }
}

function handleMedicalQuery($lang, $ctx) {
    if ($lang === 'si') {
        return "🏥 **වෛද්‍ය සේවා**\n\n" .
               "🌿 ආයුර්වේද | ⚕️ බටහිර | 🩺 සාමාන්‍ය\n\n" .
               "👨‍⚕️ වෛද්‍යවරු: " . $ctx['doctor_count'] . "\n" .
               "📅 අද: " . $ctx['todays_appointments'] . " හමුවීම්\n" .
               "🆓 නිදහසින්\n\n" .
               "පරිපාලනයට එක්වන්න.";
    } else {
        return "🏥 **Medical Services**\n\n" .
               "🌿 Ayurvedic | ⚕️ Western | 🩺 General\n\n" .
               "👨‍⚕️ Doctors: " . $ctx['doctor_count'] . "\n" .
               "📅 Today: " . $ctx['todays_appointments'] . " appointments\n" .
               "🆓 Completely Free\n\n" .
               "Contact administration to book.";
    }
}

function handleStatisticsQuery($lang, $ctx) {
    if ($lang === 'si') {
        return "📊 **සිස්ටම සංඛ්‍යා**\n\n" .
               "💰 " . $ctx['total_donations'] . "\n" .
               "📦 " . $ctx['this_month_donations'] . "\n" .
               "👥 " . $ctx['monk_count'] . " භික්ෂුවරු\n" .
               "🩺 " . $ctx['doctor_count'] . " වෛද්‍යවරු\n" .
               "📅 " . $ctx['todays_appointments'] . " අද";
    } else {
        return "📊 **System Overview**\n\n" .
               "💰 " . $ctx['total_donations'] . "\n" .
               "📦 " . $ctx['this_month_donations'] . "\n" .
               "👥 " . $ctx['monk_count'] . " monks\n" .
               "🩺 " . $ctx['doctor_count'] . " doctors\n" .
               "📅 " . $ctx['todays_appointments'] . " today";
    }
}

function handleCategoryQuery($lang) {
    if ($lang === 'si') {
        return "📂 **වර්ගයන්**\n\n" .
               "🏛️ සාමාන්‍ය සුභසාධනය\n" .
               "🏥 සෞඛ්‍ය සේවා\n" .
               "🍚 ආහාර සහ සපයුම\n" .
               "🏠 නිවස සහ නඩත්තු\n\n" .
               "✅ 100% පරිවර්තනතාව";
    } else {
        return "📂 **Donation Categories**\n\n" .
               "🏛️ General Welfare\n" .
               "🏥 Healthcare\n" .
               "🍚 Food & Supplies\n" .
               "🏠 Housing & Maintenance\n\n" .
               "✅ 100% Transparent";
    }
}

function handleMonkQuery($lang, $ctx) {
    if ($lang === 'si') {
        return "👨‍🔬 **භික්ෂුවරු**\n\n" .
               "👥 " . $ctx['monk_count'] . " සක්‍රිය\n\n" .
               "🧘 භාවනා\n" .
               "📚 අධ්‍යයනය\n" .
               "🏥 සෞඛ්‍ය සේවාව\n" .
               "🍚 ප්‍රජා සේවාව";
    } else {
        return "👨‍🔬 **Our Monks**\n\n" .
               "👥 " . $ctx['monk_count'] . " active\n\n" .
               "🧘 Meditation\n" .
               "📚 Study\n" .
               "🏥 Healthcare\n" .
               "🍚 Service";
    }
}

function handlePaymentQuery($lang) {
    if ($lang === 'si') {
        return "💳 **ගිණුම් ක්‍රම**\n\n" .
               "💵 සෙවන්දි\n" .
               "🏦 බැංකුව - People's Bank 123-456-789-0\n" .
               "💻 ඔන්ලයින් - PayHere\n\n" .
               "🔒 100% සුරක්ෂිතයි";
    } else {
        return "💳 **Payment Methods**\n\n" .
               "💵 Cash\n" .
               "🏦 Bank - People's Bank 123-456-789-0\n" .
               "💻 Online - PayHere\n\n" .
               "🔒 100% Secure";
    }
}

function handleTransparencyQuery($lang) {
    if ($lang === 'si') {
        return "🔍 **පරිවර්තනතාව**\n\n" .
               "📊 ප්‍රතිමාසික වාර්තා\n" .
               "💰 බැඳුම්කරණ විස්තරයන්\n" .
               "👥 බලපෑම ගණනය\n" .
               "📈 ප්‍රතිසිද්ධිය\n\n" .
               "✅ ස්වාධීන අඩුසිටුවීම";
    } else {
        return "🔍 **Transparency**\n\n" .
               "📊 Monthly reports\n" .
               "💰 Budget details\n" .
               "👥 Impact stats\n" .
               "📈 Results\n\n" .
               "✅ Independent audit";
    }
}

function getWelcomeMessage($lang) {
    if ($lang === 'si') {
        return "🙏 **ස්වාගතයි!**\n\n" .
               "මම Seela Suwa Herath AI .\n\n" .
               "විමසිය හැක:\n" .
               "💰 පරිත්‍යාග\n" .
               "🏥 වෛද්‍ය\n" .
               "📊 සංඛ්‍යා\n" .
               "📂 වර්ගයන්\n" .
               "💳 ගිණුම්\n" .
               "🔍 පරිවර්තනතාව";
    } else {
        return "🙏 **Welcome!**\n\n" .
               "I'm Seela Suwa Herath's AI.\n\n" .
               "Ask about:\n" .
               "💰 Donations\n" .
               "🏥 Healthcare\n" .
               "📊 Statistics\n" .
               "📂 Categories\n" .
               "💳 Payment\n" .
               "🔍 Transparency";
    }
}

function getDefaultResponse($lang) {
    if ($lang === 'si') {
        return "😊 අවබෝධ නොවිණි.\n\nකරුණාකර නැවතත් විමසන්න! 🙏";
    } else {
        return "😊 I didn't understand.\n\nPlease try again! 🙏";
    }
}

function getSystemContext() {
    $ctx = [
        'total_donations' => 'Rs. 0',
        'this_month_donations' => 'Rs. 0',
        'monk_count' => 0,
        'doctor_count' => 0,
        'todays_appointments' => 0
    ];
    
    try {
        $conn = getDBConnection();
        
        $r = $conn->query("SELECT COUNT(*) as c, COALESCE(SUM(amount), 0) as t FROM donations WHERE status IN ('verified', 'paid')");
        if ($r && ($row = $r->fetch_assoc())) {
            $ctx['total_donations'] = 'Rs. ' . number_format($row['t'], 2);
        }
        
        $r = $conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM donations WHERE status IN ('verified', 'paid') AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
        if ($r && ($row = $r->fetch_assoc())) {
            $ctx['this_month_donations'] = 'Rs. ' . number_format($row['t'], 2);
        }
        
        $r = $conn->query("SELECT COUNT(*) as c FROM monks WHERE status='active'");
        if ($r) $ctx['monk_count'] = $r->fetch_assoc()['c'];
        
        $r = $conn->query("SELECT COUNT(*) as c FROM doctors WHERE status='active'");
        if ($r) $ctx['doctor_count'] = $r->fetch_assoc()['c'];
        
        $r = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE app_date = CURDATE() AND status='scheduled'");
        if ($r) $ctx['todays_appointments'] = $r->fetch_assoc()['c'];
        
        $conn->close();
    } catch (Exception $e) {
        error_log("DB Error: " . $e->getMessage());
    }
    
    return $ctx;
}

function logConversation($msg, $resp, $lang) {
    try {
        $conn = getDBConnection();
        $sid = session_id() ?: 'anon';
        $mode = 'rule-based';
        $stmt = $conn->prepare("INSERT INTO chat_logs (session_id, user_message, bot_response, language, mode, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssss", $sid, $msg, $resp, $lang, $mode);
        $stmt->execute();
        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        error_log("Log Error: " . $e->getMessage());
    }
}

function getSuggestions($lang) {
    return $lang === 'si' ? 
        ["පරිත්‍යාග", "වෛද්‍ය", "සංඛ්‍යා", "ගිණුම්"] :
        ["Donations", "Healthcare", "Statistics", "Payment"];
}
?>
