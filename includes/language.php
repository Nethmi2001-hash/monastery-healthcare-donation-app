<?php
/**
 * Language Switcher and Translation System
 * Simple session-based multi-language support
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set language from URL parameter or session
if (isset($_GET['lang'])) {
    $_SESSION['language'] = $_GET['lang'];
}

// Default to English if not set
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

$current_language = $_SESSION['language'];

// Translation arrays
$translations = [
    'en' => [
        // Navigation
        'dashboard' => 'Dashboard',
        'monks' => 'Monks',
        'doctors' => 'Doctors',
        'appointments' => 'Appointments',
        'donations' => 'Donations',
        'bills' => 'Bills',
        'reports' => 'Reports',
        'logout' => 'Logout',
        'manage' => 'Manage',
        'users' => 'Users',
        'ai_assistant' => 'AI Assistant',
        'import_monks' => 'Import Monks',
        'categories' => 'Categories',
        'titles' => 'Titles',
        'doctor_availability' => 'Doctor Availability',
        'rooms' => 'Rooms',
        'room_slots' => 'Room Slots',
        'language' => 'Language',
        
        // Common
        'welcome' => 'Welcome',
        'search' => 'Search',
        'filter' => 'Filter',
        'export' => 'Export',
        'print' => 'Print',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'view' => 'View',
        'add_new' => 'Add New',
        'total' => 'Total',
        'status' => 'Status',
        'actions' => 'Actions',
        'date' => 'Date',
        'amount' => 'Amount',
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        
        // Dashboard
        'total_monks' => 'Total Monks',
        'total_donations' => 'Total Donations',
        'pending_appointments' => 'Pending Appointments',
        'recent_donations' => 'Recent Donations',
        'upcoming_appointments' => 'Upcoming Appointments',
        
        // Donations
        'make_donation' => 'Make a Donation',
        'donate_now' => 'Donate Now',
        'donor_name' => 'Donor Name',
        'donation_amount' => 'Donation Amount',
        'payment_method' => 'Payment Method',
        'category' => 'Category',
        'thank_you' => 'Thank You',
        'donation_success' => 'Your donation has been received successfully',
        
        // Forms
        'full_name' => 'Full Name',
        'phone_number' => 'Phone Number',
        'password' => 'Password',
        'confirm_password' => 'Confirm Password',
        'login' => 'Login',
        'register' => 'Register',
        'submit' => 'Submit',
        
        // Messages
        'success' => 'Success',
        'error' => 'Error',
        'warning' => 'Warning',
        'info' => 'Information',
        'no_data' => 'No data available',
        'loading' => 'Loading...',
        
        // Reports
        'financial_report' => 'Financial Report',
        'appointment_report' => 'Appointment Statistics',
        'donor_report' => 'Donor Report',
        'generate_report' => 'Generate Report',
        'start_date' => 'Start Date',
        'end_date' => 'End Date',
    ],
    
    'si' => [
        // Navigation
        'dashboard' => 'උපකරණ පුවරුව',
        'monks' => 'භික්ෂූන්',
        'doctors' => 'වෛද්‍යවරු',
        'appointments' => 'හමුවීම්',
        'donations' => 'පරිත්‍යාග',
        'bills' => 'බිල්පත්',
        'reports' => 'වාර්තා',
        'logout' => 'ඉවත් වන්න',
        'manage' => 'පරිපාලනය',
        'users' => 'පරිශීලකයන්',
        'ai_assistant' => 'AI සහායක',
        'import_monks' => 'භික්ෂූන් ආනයනය',
        'categories' => 'වර්ග',
        'titles' => 'පදවි',
        'doctor_availability' => 'වෛද්‍ය ලබාගැනීමේ වේලාව',
        'rooms' => 'කාමර',
        'room_slots' => 'කාමර කාලසටහන්',
        'language' => 'භාෂාව',
        
        // Common
        'welcome' => 'ආයුබෝවන්',
        'search' => 'සොයන්න',
        'filter' => 'පෙරහන',
        'export' => 'නිර්යාත',
        'print' => 'මුද්‍රණය',
        'save' => 'සුරකින්න',
        'cancel' => 'අවලංගු',
        'delete' => 'මකන්න',
        'edit' => 'සංස්කරණය',
        'view' => 'පෙන්වන්න',
        'add_new' => 'අලුත් එකතු කරන්න',
        'total' => 'මුළු එකතුව',
        'status' => 'තත්ත්වය',
        'actions' => 'ක්‍රියාමාර්ග',
        'date' => 'දිනය',
        'amount' => 'මුදල',
        'name' => 'නම',
        'email' => 'ඊමේල්',
        'phone' => 'දුරකථන',
        
        // Dashboard
        'total_monks' => 'මුළු භික්ෂූන් සංඛ්‍යාව',
        'total_donations' => 'මුළු පරිත්‍යාග',
        'pending_appointments' => 'පොරොත්තු හමුවීම්',
        'recent_donations' => 'මෑත පරිත්‍යාග',
        'upcoming_appointments' => 'ඉදිරි හමුවීම්',
        
        // Donations
        'make_donation' => 'පරිත්‍යාගයක් කරන්න',
        'donate_now' => 'දැන් පරිත්‍යාග කරන්න',
        'donor_name' => 'පරිත්‍යාගශීලී නම',
        'donation_amount' => 'පරිත්‍යාග මුදල',
        'payment_method' => 'ගෙවීමේ ක්‍රමය',
        'category' => 'වර්ගය',
        'thank_you' => 'ඔබට ස්තූතියි',
        'donation_success' => 'ඔබගේ පරිත්‍යාගය සාර්ථකව ලැබී ඇත',
        
        // Forms
        'full_name' => 'සම්පූර්ණ නම',
        'phone_number' => 'දුරකථන අංකය',
        'password' => 'මුරපදය',
        'confirm_password' => 'මුරපදය තහවුරු කරන්න',
        'login' => 'ඇතුල් වන්න',
        'register' => 'ලියාපදිංචි වන්න',
        'submit' => 'ඉදිරිපත් කරන්න',
        
        // Messages
        'success' => 'සාර්ථකයි',
        'error' => 'දෝෂයකි',
        'warning' => 'අවවාදයයි',
        'info' => 'තොරතුරු',
        'no_data' => 'දත්ත නැත',
        'loading' => 'පූරණය වෙමින්...',
        
        // Reports
        'financial_report' => 'මූල්‍ය වාර්තාව',
        'appointment_report' => 'හමුවීම් සංඛ්‍යාලේඛන',
        'donor_report' => 'පරිත්‍යාගශීලී වාර්තාව',
        'generate_report' => 'වාර්තාව නිර්මාණය කරන්න',
        'start_date' => 'ආරම්භක දිනය',
        'end_date' => 'අවසාන දිනය',
    ]
];

/**
 * Get translated text
 * @param string $key Translation key
 * @return string Translated text
 */
function __($key) {
    global $translations, $current_language;
    
    if (isset($translations[$current_language][$key])) {
        return $translations[$current_language][$key];
    }
    
    // Fallback to English
    if (isset($translations['en'][$key])) {
        return $translations['en'][$key];
    }
    
    // Return key if translation not found
    return $key;
}

/**
 * Get current language code
 * @return string Language code (en/si)
 */
function getCurrentLanguage() {
    global $current_language;
    return $current_language;
}

/**
 * Get language name
 * @param string $code Language code
 * @return string Language name
 */
function getLanguageName($code) {
    $languages = [
        'en' => 'English',
        'si' => 'සිංහල'
    ];
    return $languages[$code] ?? 'Unknown';
}
?>
