<?php
require_once __DIR__ . '/../../includes/init.php';
session_start();
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/db_config.php';
$amount = isset($_GET['amount']) ? (int)$_GET['amount'] : 1000;
$errorMsg = trim((string)($_GET['error'] ?? ''));
$successRef = trim((string)($_GET['success_ref'] ?? ''));
$dateError = trim((string)($_GET['date_error'] ?? ''));
$dateSuccess = trim((string)($_GET['date_success'] ?? ''));
$todayDate = date('Y-m-d');

$blockedDates = [];
$conn = getDBConnection();
$conn->query("CREATE TABLE IF NOT EXISTS donation_date_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    donor_name VARCHAR(120) NOT NULL,
    donor_email VARCHAR(160) NOT NULL,
    donor_phone VARCHAR(40) NOT NULL,
    requested_date DATE NOT NULL,
    meal_type VARCHAR(20) NOT NULL DEFAULT 'lunch',
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_requested_date (requested_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$mealColRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_date_requests' AND COLUMN_NAME = 'meal_type'");
if ($mealColRes && (int)$mealColRes->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE donation_date_requests ADD COLUMN meal_type VARCHAR(20) NOT NULL DEFAULT 'lunch' AFTER requested_date");
}
$blockedRes = $conn->query("SELECT requested_date FROM donation_date_requests WHERE status IN ('pending','approved') ORDER BY requested_date ASC");
if ($blockedRes) {
    while ($row = $blockedRes->fetch_assoc()) {
        $blockedDates[] = $row['requested_date'];
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Donation &mdash; Seela suwa herath</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Raleway:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --ivory:      #FFFBF7;
            --cream:      #FEF3E8;
            --sand:       #F5E0C8;
            --warm-gray:  #C9A88A;
            --muted-sage: #F0864A;
            --deep-sage:  #D4622A;
            --gold:       #F0A050;
            --gold-bg:    rgba(240,160,80,0.08);
            --text-dark:  #2C2820;
            --text-mid:   #5A5248;
            --text-light: #8A7F74;
            --white:      #FFFFFF;
            --border:     rgba(210,170,130,0.28);
            --success:    #C05520;
        }
        html, body {
            font-family: 'Raleway', sans-serif;
            font-weight: 300;
            background: var(--ivory);
            color: var(--text-dark);
        }

        /* â”€â”€ TOP BAR â”€â”€ */
        nav{position:sticky;top:0;left:0;right:0;z-index:200;padding:0 6%;height:72px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid var(--border)}
        .nav-logo{display:flex;align-items:center;gap:12px;text-decoration:none}
        .nav-logo-mark{width:36px;height:36px;background:linear-gradient(135deg,var(--deep-sage),var(--gold));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff}
        .nav-logo-name{font-family:'EB Garamond',serif;font-size:1.25rem;font-weight:600;color:var(--text-dark)}
        .nav-logo-sub{font-size:.6rem;color:var(--text-light);letter-spacing:.13em;text-transform:uppercase;display:block;margin-top:-4px}
        .nav-links{display:flex;align-items:center;gap:28px;list-style:none}
        .nav-links a{text-decoration:none;color:var(--text-mid);font-size:.83rem;font-weight:400;letter-spacing:.06em;text-transform:uppercase;transition:color .2s}
        .nav-links a:hover,.nav-links a.active{color:var(--deep-sage)}
        .nav-donate{background:var(--deep-sage)!important;color:#fff!important;padding:9px 24px!important;border-radius:40px!important;font-weight:500!important}
        .nav-donate:hover{background:var(--text-dark)!important}

        /* â”€â”€ HERO STRIP â”€â”€ */
        .donate-hero {
            background: #EDE6DC;
            color: var(--text-dark);
            padding: 36px 6%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .donate-hero::before {
            content: '\2638';
            position: absolute;
            font-size: 220px;
            opacity: 0.04;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
        }
        .donate-hero-eyebrow {
            font-size: 0.72rem; font-weight: 500;
            letter-spacing: 0.18em; text-transform: uppercase;
            color: var(--text-light); margin-bottom: 12px;
        }
        .donate-hero h1 {
            font-family: 'EB Garamond', serif;
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 300; line-height: 1.2;
        }
        .donate-hero h1 em { font-style: italic; color: var(--deep-sage); }
        .donate-hero p {
            margin-top: 12px;
            font-size: 0.95rem; color: var(--text-mid);
        }

        /* â”€â”€ MAIN LAYOUT â”€â”€ */
        .donate-main {
            width: 88%;
            max-width: 1160px;
            margin: 0 auto 80px;
            padding: 56px 0 0;
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
            align-items: start;
        }

        .donate-left {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .donate-wide {
            width: 88%;
            max-width: 1160px;
            margin: 0 auto;
            padding: 36px 0 0;
        }

        /* â”€â”€ FORM PANEL â”€â”€ */
        .form-panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px;
        }

        .date-request-panel {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px 40px;
        }

        .date-request-panel p {
            color: var(--text-mid);
            font-size: 0.92rem;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .date-request-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .date-request-help {
            margin-top: 12px;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .date-request-list {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .date-chip {
            border: 1px solid var(--border);
            background: var(--ivory);
            color: var(--text-mid);
            border-radius: 20px;
            padding: 6px 12px;
            font-size: 0.82rem;
        }
        .panel-section {
            margin-bottom: 36px;
            padding-bottom: 36px;
            border-bottom: 1px solid var(--border);
        }
        .panel-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .panel-section-title {
            font-family: 'EB Garamond', serif;
            font-size: 1.3rem; font-weight: 600;
            color: var(--text-dark); margin-bottom: 20px;
        }
        .panel-section-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 24px; height: 24px;
            background: var(--deep-sage); color: white;
            border-radius: 50%; font-size: 0.75rem; font-weight: 500;
            margin-right: 10px; vertical-align: middle;
        }

        /* Amount Chips */
        .amount-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px; margin-bottom: 16px;
        }
        .amount-chip {
            padding: 16px 10px;
            border: 1.5px solid var(--border); border-radius: 10px;
            background: var(--ivory); text-align: center;
            cursor: pointer; transition: all 0.2s;
        }
        .amount-chip:hover { border-color: var(--deep-sage); }
        .amount-chip.selected {
            border-color: var(--deep-sage);
            background: rgba(212,98,42,0.07);
        }
        .amount-chip .rs {
            font-size: 0.72rem; color: var(--text-light);
            letter-spacing: 0.06em; display: block; margin-bottom: 2px;
        }
        .amount-chip .val {
            font-family: 'EB Garamond', serif;
            font-size: 1.4rem; font-weight: 600;
            color: var(--text-dark);
        }
        .amount-chip.selected .val { color: var(--deep-sage); }
        .amount-chip .lbl {
            font-size: 0.7rem; color: var(--text-light); margin-top: 2px;
        }
        .custom-amount-wrapper {
            position: relative; margin-top: 4px;
        }
        .custom-currency {
            position: absolute; left: 14px; top: 50%;
            transform: translateY(-50%);
            font-size: 0.9rem; color: var(--text-mid); font-weight: 500;
        }
        .custom-amount-input {
            width: 100%; padding: 13px 14px 13px 40px;
            border: 1.5px solid var(--border); border-radius: 10px;
            background: var(--ivory); color: var(--text-dark);
            font-family: 'EB Garamond', serif;
            font-size: 1.3rem; font-weight: 400;
            outline: none; transition: border-color 0.2s;
        }
        .custom-amount-input:focus { border-color: var(--deep-sage); background: var(--white); }

        /* Category select */
        .category-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
        }
        .cat-option { position: relative; }
        .cat-option input { position: absolute; opacity: 0; width: 0; }
        .cat-option label {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px;
            border: 1.5px solid var(--border); border-radius: 10px;
            background: var(--ivory); cursor: pointer;
            transition: all 0.2s; font-size: 0.88rem; color: var(--text-mid);
        }
        .cat-option label .cat-icon { font-size: 1.2rem; }
        .cat-option input:checked + label {
            border-color: var(--deep-sage);
            background: rgba(212,98,42,0.07);
            color: var(--text-dark);
        }
        .cat-option label:hover { border-color: var(--muted-sage); }

        /* Donor info */
        .form-group { margin-bottom: 14px; }
        .form-group label {
            display: block; font-size: 0.75rem; font-weight: 500;
            letter-spacing: 0.07em; text-transform: uppercase;
            color: var(--text-mid); margin-bottom: 7px;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid var(--border); border-radius: 10px;
            background: var(--ivory); color: var(--text-dark);
            font-family: 'Raleway', sans-serif; font-size: 0.92rem; font-weight: 300;
            outline: none; transition: border-color 0.2s, background 0.2s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: var(--deep-sage); background: var(--white);
        }
        .form-group input::placeholder, .form-group textarea::placeholder { color: var(--warm-gray); }
        .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .anonymous-row {
            display: flex; align-items: center; gap: 10px;
            padding: 12px 14px;
            border: 1.5px solid var(--border); border-radius: 10px;
            background: var(--ivory); cursor: pointer;
            font-size: 0.88rem; color: var(--text-mid);
            transition: border-color 0.2s;
        }
        .anonymous-row input { width: auto; accent-color: #D4622A; }
        .anonymous-row:hover { border-color: var(--muted-sage); }

        /* Payment method */
        .payment-methods { display: flex; flex-direction: column; gap: 10px; }
        .pay-option { position: relative; }
        .pay-option input { position: absolute; opacity: 0; width: 0; }
        .pay-option label {
            display: flex; align-items: center; gap: 14px;
            padding: 16px 18px;
            border: 1.5px solid var(--border); border-radius: 10px;
            background: var(--ivory); cursor: pointer; transition: all 0.2s;
        }
        .pay-option input:checked + label {
            border-color: var(--deep-sage);
            background: rgba(212,98,42,0.06);
        }
        .pay-option label:hover { border-color: var(--muted-sage); }
        .pay-icon { font-size: 1.4rem; }
        .pay-info .pay-name {
            font-size: 0.9rem; font-weight: 500; color: var(--text-dark);
        }
        .pay-info .pay-sub {
            font-size: 0.78rem; color: var(--text-light);
        }
        .pay-check {
            margin-left: auto;
            width: 20px; height: 20px; border-radius: 50%;
            border: 1.5px solid var(--border); flex-shrink: 0;
        }
        .pay-option input:checked + label .pay-check {
            background: var(--deep-sage); border-color: var(--deep-sage);
            display: flex; align-items: center; justify-content: center;
        }
        .pay-option input:checked + label .pay-check::after {
            content: '\2713'; color: white; font-size: 0.7rem;
        }

        /* Bank slip upload (conditional) */
        .bank-slip-section {
            display: none;
            margin-top: 16px;
            padding: 16px;
            background: var(--gold-bg);
            border: 1px solid rgba(240,160,80,0.3);
            border-radius: 10px;
        }
        .bank-slip-section.visible { display: block; }
        .bank-slip-section p {
            font-size: 0.83rem; color: var(--text-mid); margin-bottom: 12px; line-height: 1.6;
        }
        .bank-slip-section strong { color: var(--text-dark); }

        /* â”€â”€ SUBMIT BUTTON â”€â”€ */
        .submit-row { margin-top: 32px; }
        .btn-donate {
            width: 100%; padding: 16px;
            background: var(--gold); color: var(--text-dark);
            border: none; border-radius: 12px;
            font-family: 'Raleway', sans-serif; font-size: 1.05rem; font-weight: 500;
            letter-spacing: 0.04em; cursor: pointer; transition: all 0.3s;
        }
        .btn-donate:hover {
            background: var(--text-dark); color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        }
        .secure-note {
            text-align: center; margin-top: 12px;
            font-size: 0.78rem; color: var(--text-light);
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }

        /* â”€â”€ SUMMARY PANEL â”€â”€ */
        .summary-panel {
            position: sticky; top: 80px;
        }
        .summary-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 16px;
        }
        .summary-title {
            font-family: 'EB Garamond', serif;
            font-size: 1.1rem; font-weight: 600;
            color: var(--text-dark); margin-bottom: 20px;
            padding-bottom: 16px; border-bottom: 1px solid var(--border);
        }
        .summary-row {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 12px; font-size: 0.88rem;
        }
        .summary-row .lbl { color: var(--text-light); }
        .summary-row .val { color: var(--text-dark); font-weight: 400; }
        .summary-total {
            display: flex; justify-content: space-between; align-items: baseline;
            padding-top: 16px;
            border-top: 1px solid var(--border); margin-top: 4px;
        }
        .summary-total .lbl {
            font-size: 0.85rem; font-weight: 500;
            letter-spacing: 0.05em; color: var(--text-mid);
        }
        .summary-total .amount {
            font-family: 'EB Garamond', serif;
            font-size: 2rem; font-weight: 600;
            color: var(--deep-sage);
        }
        .impact-card {
            background: var(--cream);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }
        .impact-title {
            font-size: 0.72rem; font-weight: 500;
            letter-spacing: 0.12em; text-transform: uppercase;
            color: var(--text-light); margin-bottom: 16px;
        }
        .impact-item {
            display: flex; align-items: flex-start; gap: 10px;
            margin-bottom: 14px; font-size: 0.85rem; color: var(--text-mid);
        }
        .impact-item:last-child { margin-bottom: 0; }
        .impact-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
        .transparency-link {
            display: block; text-align: center;
            margin-top: 16px; padding: 10px;
            font-size: 0.82rem; color: var(--deep-sage);
            text-decoration: none; border-radius: 8px;
            transition: background 0.2s;
        }
        .transparency-link:hover { background: rgba(212,98,42,0.06); }

        /* Footer */
        footer{background:#4A3B32;padding:60px 6% 28px}
        .foot-grid{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:24px}
        .foot-brand{font-family:'EB Garamond',serif;font-size:1.5rem;color:#fff;margin-bottom:12px;font-weight:600}
        .foot-tag{font-size:.84rem;color:rgba(255,255,255,.38);line-height:1.7;max-width:240px}
        .foot-col h4{font-size:.68rem;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:14px}
        .foot-col ul{list-style:none}
        .foot-col ul li{margin-bottom:9px}
        .foot-col ul a{color:rgba(255,255,255,.48);text-decoration:none;font-size:.86rem;transition:color .2s}
        .foot-col ul a:hover{color:var(--gold)}
        .foot-btm{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;font-size:.77rem;color:rgba(255,255,255,.22)}

        @media (max-width: 900px) {
            .donate-main { grid-template-columns: 1fr; }
            .summary-panel { position: static; }
            .foot-grid{grid-template-columns:1fr 1fr}
        }
        @media (max-width: 600px) {
            .nav-links { display: none; }
            .form-panel { padding: 24px; }
            .date-request-panel { padding: 24px; }
            .date-request-grid { grid-template-columns: 1fr; }
            .amount-grid { grid-template-columns: repeat(2, 1fr); }
            .category-grid { grid-template-columns: 1fr; }
            .form-row-2 { grid-template-columns: 1fr; }
            .foot-grid{grid-template-columns:1fr}
            .foot-btm{flex-direction:column;gap:8px}
        }

        /* Floating chatbot */
        .chatbot-fab {
            position: fixed;
            right: 20px;
            bottom: 20px;
            width: 56px;
            height: 56px;
            border: 0;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--deep-sage), #C05520);
            color: var(--white);
            font-size: 1.35rem;
            cursor: pointer;
            box-shadow: 0 10px 26px rgba(192,85,32,0.36);
            z-index: 120;
        }
        .chatbot-panel {
            position: fixed;
            right: 20px;
            bottom: 86px;
            width: min(360px, calc(100vw - 24px));
            height: min(520px, calc(100vh - 120px));
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 18px 44px rgba(50,38,25,0.20);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 119;
        }
        .chatbot-panel.open { display: flex; }
        .chatbot-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 16px;
            background: linear-gradient(to right, var(--deep-sage), #C05520);
            color: var(--white);
        }
        .chatbot-title {
            font-size: 0.9rem;
            letter-spacing: 0.03em;
            font-weight: 400;
        }
        .chatbot-close {
            border: 0;
            background: transparent;
            color: var(--white);
            font-size: 1.1rem;
            cursor: pointer;
            line-height: 1;
        }
        .chatbot-messages {
            flex: 1;
            overflow-y: auto;
            padding: 12px;
            background: #FFFDFB;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-msg {
            max-width: 86%;
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 0.84rem;
            line-height: 1.45;
            white-space: pre-line;
        }
        .chat-msg.bot {
            align-self: flex-start;
            border: 1px solid var(--border);
            background: var(--white);
            color: var(--text-mid);
        }
        .chat-msg.user {
            align-self: flex-end;
            background: rgba(212,98,42,0.12);
            color: var(--text-dark);
            border: 1px solid rgba(212,98,42,0.24);
        }
        .chatbot-input-row {
            display: flex;
            gap: 8px;
            padding: 10px;
            border-top: 1px solid var(--border);
            background: var(--white);
        }
        .chatbot-input-row input {
            flex: 1;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--ivory);
            font-family: 'Raleway', sans-serif;
            font-size: 0.84rem;
            outline: none;
        }
        .chatbot-input-row input:focus { border-color: var(--deep-sage); background: var(--white); }
        .chatbot-send {
            border: 0;
            border-radius: 10px;
            padding: 0 14px;
            background: var(--gold);
            color: var(--text-dark);
            font-weight: 500;
            cursor: pointer;
        }
        .chatbot-typing {
            display: none;
            padding: 0 12px 10px;
            font-size: 0.75rem;
            color: var(--text-light);
            background: #FFFDFB;
        }
        .chatbot-typing.show { display: block; }

        @media (max-width: 600px) {
            .chatbot-fab { right: 12px; bottom: 12px; }
            .chatbot-panel { right: 12px; bottom: 76px; width: calc(100vw - 24px); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .form-panel { animation: fadeUp 0.5s ease both; }
        .summary-panel { animation: fadeUp 0.5s 0.1s ease both; }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <a href="/test/index.php" class="nav-logo">
        <div class="nav-logo-mark">☸</div>
        <div>
            <span class="nav-logo-name">Seela suwa herath</span>
            <span class="nav-logo-sub">Monastery Welfare</span>
        </div>
    </a>
    <ul class="nav-links">
        <li><a href="/test/index.php#mission">Our Mission</a></li>
        <li><a href="/test/index.php#how">How It Works</a></li>
        <li><a href="/test/pages/public/public_transparency.php">Transparency</a></li>
        <li><a href="/test/pages/auth/login.php">Sign In</a></li>
        <li><a href="/test/pages/public/public_donate.php" class="nav-donate active">Donate Now</a></li>
    </ul>
</nav>


<!-- HERO STRIP -->
<div class="donate-hero">
    <div class="donate-hero-eyebrow">Make a Difference</div>
    <h1>Your <em>Generosity</em> Creates Change</h1>
    <p>Support the welfare, healthcare, and dignified living of our monastery community</p>
</div>

<!-- DATE RESERVATION -->
<div class="donate-wide">
    <div class="date-request-panel" id="date-request">
        <div class="panel-section-title">
            <span class="panel-section-num">1</span>
            Sponsor Daily Alms
        </div>
        <p>Select a donation date. Once requested, that date is held and others cannot choose it until approved or rejected by the admin.</p>

        <?php if ($dateError !== ''): ?>
        <div style="background:rgba(185,64,64,0.08);border:1px solid rgba(185,64,64,0.28);color:#9f2f2f;padding:12px 14px;border-radius:10px;margin:14px 0;font-size:.86rem;">
            &#9888; <?= htmlspecialchars($dateError) ?>
        </div>
        <?php endif; ?>

                <?php if ($dateSuccess !== ''): ?>
                <div id="dateSuccessMsg" style="background:rgba(46,125,82,0.08);border:1px solid rgba(46,125,82,0.28);color:#2f7a46;padding:12px 14px;border-radius:10px;margin:14px 0;font-size:.86rem;">
            &#10003; Date request submitted. Admin will review it soon.
        </div>
                <script>
                    setTimeout(function() {
                        var msg = document.getElementById('dateSuccessMsg');
                        if (!msg) return;
                        msg.style.transition = 'opacity 0.3s ease';
                        msg.style.opacity = '0';
                        setTimeout(function() {
                            if (msg && msg.parentNode) {
                                msg.remove();
                            }
                        }, 350);
                    }, 3000);
                </script>
        <?php endif; ?>

        <form method="POST" action="/test/api/process_donation_date_request.php" id="dateRequestForm">
            <?php if (function_exists('csrfField')): ?>
            <?php csrfField(); ?>
            <?php endif; ?>

            <div class="date-request-grid">
                <div class="form-group" style="margin-bottom:0;">
                    <label for="date_full_name">Full Name</label>
                    <input type="text" id="date_full_name" name="donor_name" placeholder="Perera Saman" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="date_phone">Phone</label>
                    <input type="tel" id="date_phone" name="donor_phone" placeholder="+94 77 000 0000" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="date_email">Email</label>
                    <input type="email" id="date_email" name="donor_email" placeholder="you@example.com" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="donation_date">Donation Date</label>
                    <input type="date" id="donation_date" name="requested_date" min="<?= htmlspecialchars($todayDate) ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label for="meal_type">Meal</label>
                    <select id="meal_type" name="meal_type" required>
                        <option value="morning_food">Morning</option>
                        <option value="lunch" selected>Lunch</option>
                    </select>
                </div>
            </div>

            <div class="date-request-help" id="dateHelp"></div>

            <div class="date-request-list" aria-live="polite">
                <?php if (count($blockedDates) === 0): ?>
                    <span class="date-chip">No reserved dates yet</span>
                <?php else: ?>
                    <?php foreach ($blockedDates as $blocked): ?>
                        <span class="date-chip"><?= htmlspecialchars(date('M d, Y', strtotime($blocked))) ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="submit-row" style="margin-top:18px;">
                <button type="submit" class="btn-donate" style="width:auto; padding:12px 22px;">
                    &#128197; Request Date
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MAIN CONTENT -->
<div class="donate-main">

    <!-- FORM -->
    <div class="form-panel">
        <?php if ($errorMsg !== ''): ?>
        <div style="background:rgba(185,64,64,0.08);border:1px solid rgba(185,64,64,0.28);color:#9f2f2f;padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:.86rem;">
            &#9888; <?= htmlspecialchars($errorMsg) ?>
        </div>
        <?php endif; ?>

        <?php if ($successRef !== ''): ?>
        <div style="background:rgba(46,125,82,0.08);border:1px solid rgba(46,125,82,0.28);color:#2f7a46;padding:12px 14px;border-radius:10px;margin-bottom:18px;font-size:.86rem;">
            &#10003; Donation submitted successfully. Reference: DON-<?= htmlspecialchars($successRef) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/test/api/process_public_donation.php" enctype="multipart/form-data" id="donateForm">
            <?php if (function_exists('csrfField')): ?>
            <?php csrfField(); ?>
            <?php endif; ?>
            <input type="hidden" name="amount" id="hiddenAmount" value="<?= $amount ?>">
            <input type="hidden" name="payment_method" id="hiddenMethod" value="bank_slip">

            <!-- STEP 1: Amount -->
            <div class="panel-section">
                <div style="font-size:.72rem;font-weight:500;letter-spacing:.16em;text-transform:uppercase;color:var(--deep-sage);margin-bottom:8px;">For Donation</div>
                <div class="panel-section-title">
                    <span class="panel-section-num">1</span>
                    Choose Amount
                </div>
                <div class="amount-grid">
                    <div class="amount-chip <?= $amount == 500 ? 'selected' : '' ?>" onclick="selectAmount(500)">
                        <span class="rs">LKR</span>
                        <span class="val">500</span>
                        <span class="lbl">Meal for a day</span>
                    </div>
                    <div class="amount-chip <?= $amount == 1000 ? 'selected' : '' ?>" onclick="selectAmount(1000)">
                        <span class="rs">LKR</span>
                        <span class="val">1,000</span>
                        <span class="lbl">Weekly supplies</span>
                    </div>
                    <div class="amount-chip <?= $amount == 2500 ? 'selected' : '' ?>" onclick="selectAmount(2500)">
                        <span class="rs">LKR</span>
                        <span class="val">2,500</span>
                        <span class="lbl">Medical consult</span>
                    </div>
                    <div class="amount-chip <?= $amount == 5000 ? 'selected' : '' ?>" onclick="selectAmount(5000)">
                        <span class="rs">LKR</span>
                        <span class="val">5,000</span>
                        <span class="lbl">Monthly welfare</span>
                    </div>
                    <div class="amount-chip <?= $amount == 10000 ? 'selected' : '' ?>" onclick="selectAmount(10000)">
                        <span class="rs">LKR</span>
                        <span class="val">10,000</span>
                        <span class="lbl">Full care package</span>
                    </div>
                    <div class="amount-chip <?= !in_array($amount,[500,1000,2500,5000,10000]) ? 'selected' : '' ?>" onclick="selectAmount(0)">
                        <span class="rs">&nbsp;</span>
                        <span class="val" style="font-size:1rem; padding-top:4px;">Custom</span>
                        <span class="lbl">Any amount</span>
                    </div>
                </div>
                <div class="custom-amount-wrapper" id="customAmountWrap" style="<?= !in_array($amount,[500,1000,2500,5000,10000]) ? '' : 'display:none' ?>">
                    <span class="custom-currency">Rs.</span>
                    <input type="number" class="custom-amount-input" id="customAmount"
                           placeholder="Enter amount"
                           value="<?= !in_array($amount,[500,1000,2500,5000,10000]) ? $amount : '' ?>"
                           min="100"
                           oninput="updateAmount(this.value)">
                </div>
            </div>

            <!-- STEP 2: Category -->
            <div class="panel-section">
                <div class="panel-section-title">
                    <span class="panel-section-num">2</span>
                    Donation Purpose
                </div>
                <p style="margin:6px 0 12px;color:#6b7280;font-size:.85rem;">Support with heart and intention.</p>
                <div class="category-grid">
                    <div class="cat-option">
                        <input type="radio" name="category" id="cat_general" value="general" checked>
                        <label for="cat_general">
                            <span class="cat-icon">&#127963;</span> General Welfare
                        </label>
                    </div>
                    <div class="cat-option">
                        <input type="radio" name="category" id="cat_health" value="healthcare">
                        <label for="cat_health">
                            <span class="cat-icon">&#127973;</span> Healthcare
                        </label>
                    </div>
                    <div class="cat-option">
                        <input type="radio" name="category" id="cat_food" value="food">
                        <label for="cat_food">
                            <span class="cat-icon">&#127834;</span> Food & Supplies
                        </label>
                    </div>
                    <div class="cat-option">
                        <input type="radio" name="category" id="cat_housing" value="housing">
                        <label for="cat_housing">
                            <span class="cat-icon">&#127968;</span> Housing & Rooms
                        </label>
                    </div>
                </div>
                <div class="form-group" style="margin-top:14px; margin-bottom:0;">
                    <label for="message">Personal Message (optional)</label>
                    <textarea id="message" name="message" rows="2" placeholder="A note of blessing or dedication..."></textarea>
                </div>
            </div>

            <!-- STEP 3: Donor Info -->
            <div class="panel-section">
                <div class="panel-section-title">
                    <span class="panel-section-num">3</span>
                    Your Details
                </div>
                <label class="anonymous-row" style="display:flex; cursor:pointer; margin-bottom:16px;">
                    <input type="checkbox" name="anonymous" id="anonymousCheck" onchange="toggleAnon(this)">
                    <span>Donate anonymously</span>
                </label>
                <div id="donorFields">
                    <div class="form-row-2">
                        <div class="form-group">
                            <label for="donor_name">Full Name</label>
                            <input type="text" id="donor_name" name="donor_name" placeholder="Perera Saman" required>
                        </div>
                        <div class="form-group">
                            <label for="donor_phone">Phone (optional)</label>
                            <input type="tel" id="donor_phone" name="donor_phone" placeholder="+94 77 000 0000">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="donor_email">Email (for receipt)</label>
                        <input type="email" id="donor_email" name="donor_email" placeholder="you@example.com" required>
                    </div>
                </div>
            </div>

            <!-- STEP 4: Payment -->
            <div class="panel-section">
                <div class="panel-section-title">
                    <span class="panel-section-num">4</span>
                    Payment Method
                </div>
                <div class="payment-methods">
                    <div class="pay-option">
                        <input type="radio" name="pay_method" id="pay_bank" value="bank_slip" checked>
                        <label for="pay_bank">
                            <span class="pay-icon">&#127974;</span>
                            <div class="pay-info">
                                <div class="pay-name">Bank Transfer + Slip</div>
                                <div class="pay-sub">Transfer & upload your receipt</div>
                            </div>
                            <div class="pay-check"></div>
                        </label>
                    </div>
                </div>

                <!-- Bank slip upload (shown when bank selected) -->
                <div class="bank-slip-section visible" id="bankSlipSection">
                    <p>
                        Please transfer your donation to:<br>
                        <strong>Bank:</strong> People's Bank &nbsp;|&nbsp;
                        <strong>Account:</strong> 123-456-789-0 &nbsp;|&nbsp;
                        <strong>Name:</strong> Seela suwa herath Monastery Trust
                    </p>
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="bank_slip">Upload Bank Slip (JPG, PNG, PDF)</label>
                        <input type="file" id="bank_slip" name="bank_slip" accept="image/*,.pdf" required>
                    </div>
                </div>
            </div>

            <!-- SUBMIT -->
            <div class="submit-row">
                <button type="submit" class="btn-donate" id="submitBtn">
                    Donate Rs. <span id="btnAmount"><?= number_format($amount) ?></span>
                </button>
                <p class="secure-note">Secure & encrypted &middot; Instant receipt via email</p>
            </div>
        </form>
    </div>


    <!-- SUMMARY -->
    <div class="summary-panel">
        <div class="summary-card">
            <div class="summary-title">Donation Summary</div>
            <div class="summary-row">
                <span class="lbl">Amount</span>
                <span class="val">Rs. <span class="js-amount"><?= number_format($amount) ?></span></span>
            </div>
            <div class="summary-row">
                <span class="lbl">Purpose</span>
                <span class="val" id="summaryCategory">General Welfare</span>
            </div>
            <div class="summary-row">
                <span class="lbl">Payment</span>
                <span class="val" id="summaryPayment">Bank Transfer</span>
            </div>
            <div class="summary-total">
                <span class="lbl">TOTAL</span>
                <span class="amount">Rs. <span class="js-amount"><?= number_format($amount) ?></span></span>
            </div>
        </div>

        <div class="impact-card">
            <div class="impact-title">Your Impact</div>
            <div class="impact-item">
                <span class="impact-icon"></span>
                <span>Rs. 500 feeds a monk for a full day</span>
            </div>
            <div class="impact-item">
                <span class="impact-icon"></span>
                <span>Rs. 2,500 covers one medical consultation</span>
            </div>
            <div class="impact-item">
                <span class="impact-icon"></span>
                <span>Rs. 5,000 supports monthly welfare needs</span>
            </div>
            <div class="impact-item">
                <span class="impact-icon"></span>
                <span>100% of funds are publicly reported</span>
            </div>
            <a href="/test/pages/public/public_transparency.php" class="transparency-link">
                View transparency reports &rarr;
            </a>
        </div>
    </div>

</div>

<footer>
    <div class="foot-grid">
        <div>
            <div class="foot-brand">☸ Seela suwa herath</div>
            <p class="foot-tag">Supporting monastery welfare through community generosity, transparent governance, and compassionate care.</p>
        </div>
        <div class="foot-col">
            <h4>Platform</h4>
            <ul>
                <li><a href="/test/pages/public/public_donate.php">Donate</a></li>
                <li><a href="/test/pages/public/public_transparency.php">Transparency</a></li>
                <li><a href="/test/pages/auth/register.php">Register</a></li>
                <li><a href="/test/pages/auth/login.php">Sign In</a></li>
            </ul>
        </div>
        <div class="foot-col">
            <h4>Welfare</h4>
            <ul>
                <li><a href="#">Healthcare</a></li>
                <li><a href="#">Housing</a></li>
                <li><a href="#">Appointments</a></li>
                <li><a href="#">Reports</a></li>
            </ul>
        </div>
        <div class="foot-col">
            <h4>Info</h4>
            <ul>
                <li><a href="#">About Us</a></li>
                <li><a href="#">Contact</a></li>
                <li><a href="#">Privacy Policy</a></li>
            </ul>
        </div>
    </div>
    <div class="foot-btm"><span>© 2026 Seela suwa herath Monastery Welfare Platform</span><span>Made with in Sri Lanka</span></div>
</footer>

<button type="button" class="chatbot-fab" id="chatbotFab" aria-label="Open chatbot">&#128172;</button>
<div class="chatbot-panel" id="chatbotPanel" aria-live="polite">
    <div class="chatbot-header">
        <div class="chatbot-title">AI Donation Assistant</div>
        <button type="button" class="chatbot-close" id="chatbotClose" aria-label="Close chatbot">&times;</button>
    </div>
    <div class="chatbot-messages" id="chatbotMessages">
        <div class="chat-msg bot">Welcome. Ask about donation process, categories, or account details.</div>
    </div>
    <div class="chatbot-typing" id="chatbotTyping">Assistant is typing...</div>
    <div class="chatbot-input-row">
        <input type="text" id="chatbotInput" placeholder="Type your question..." maxlength="500">
        <button type="button" class="chatbot-send" id="chatbotSend">Send</button>
    </div>
</div>

<script>
let currentAmount = <?= $amount ?>;

function selectAmount(val) {
    document.querySelectorAll('.amount-chip').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    const wrap = document.getElementById('customAmountWrap');
    if (val === 0) {
        wrap.style.display = 'block';
        document.getElementById('customAmount').focus();
    } else {
        wrap.style.display = 'none';
        updateAmount(val);
    }
}

function updateAmount(val) {
    currentAmount = parseInt(val) || 0;
    document.getElementById('hiddenAmount').value = currentAmount;
    const fmt = currentAmount.toLocaleString('en-LK');
    document.querySelectorAll('.js-amount').forEach(el => el.textContent = fmt);
    document.getElementById('btnAmount').textContent = fmt;
}

function showPayment(method) {
    document.getElementById('hiddenMethod').value = method;
    const bs = document.getElementById('bankSlipSection');
    bs.classList.toggle('visible', method === 'bank_slip');
    document.getElementById('bank_slip').required = method === 'bank_slip';
    document.getElementById('summaryPayment').textContent = 'Bank Transfer';
}

function toggleAnon(cb) {
    document.getElementById('donorFields').style.display = cb.checked ? 'none' : 'block';
    document.getElementById('donor_name').required = !cb.checked;
    document.getElementById('donor_email').required = !cb.checked;
}

if (window.location.search.includes('error=') || window.location.search.includes('success_ref=')) {
    window.history.replaceState({}, document.title, window.location.pathname + window.location.hash);
}

// Update category summary
document.querySelectorAll('input[name="category"]').forEach(r => {
    r.addEventListener('change', function() {
        const labels = {
            general: 'General Welfare', healthcare: 'Healthcare',
            food: 'Food & Supplies', housing: 'Housing & Rooms'
        };
        document.getElementById('summaryCategory').textContent = labels[this.value] || this.value;
    });
});

const blockedDates = <?= json_encode($blockedDates) ?>;
const dateInput = document.getElementById('donation_date');
const dateHelp = document.getElementById('dateHelp');
if (dateInput) {
    dateInput.addEventListener('change', function() {
        if (blockedDates.includes(this.value)) {
            dateHelp.textContent = 'That date is already reserved. Please choose another.';
            this.value = '';
        } else {
            dateHelp.textContent = '';
        }
    });
}

const chatbotPanel = document.getElementById('chatbotPanel');
const chatbotFab = document.getElementById('chatbotFab');
const chatbotClose = document.getElementById('chatbotClose');
const chatbotMessages = document.getElementById('chatbotMessages');
const chatbotInput = document.getElementById('chatbotInput');
const chatbotSend = document.getElementById('chatbotSend');
const chatbotTyping = document.getElementById('chatbotTyping');

chatbotFab.addEventListener('click', function() {
    chatbotPanel.classList.add('open');
    chatbotInput.focus();
});

chatbotClose.addEventListener('click', function() {
    chatbotPanel.classList.remove('open');
});

chatbotSend.addEventListener('click', sendChatbotMessage);
chatbotInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        sendChatbotMessage();
    }
});

function appendChatMessage(text, type) {
    const msg = document.createElement('div');
    msg.className = 'chat-msg ' + type;
    msg.textContent = text;
    chatbotMessages.appendChild(msg);
    chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
}

function sendChatbotMessage() {
    const message = chatbotInput.value.trim();
    if (message === '') return;

    appendChatMessage(message, 'user');
    chatbotInput.value = '';
    chatbotTyping.classList.add('show');

    fetch('/test/api/chatbot_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            message: message,
            language: 'auto',
            history: []
        })
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        chatbotTyping.classList.remove('show');
        if (data && data.success && data.response) {
            appendChatMessage(data.response, 'bot');
        } else {
            appendChatMessage('Sorry, I could not process that right now. Please try again.', 'bot');
        }
    })
    .catch(function() {
        chatbotTyping.classList.remove('show');
        appendChatMessage('Connection issue. Please try again in a moment.', 'bot');
    });
}
</script>
</body>
</html>

