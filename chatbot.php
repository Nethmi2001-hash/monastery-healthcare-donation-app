<?php
session_start();

// Require login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "monastery_healthcare";

$conn = new mysqli($servername, $username, $password, $dbname);

// System Stats (for AI context)
$stats = [];
$stats['total_donations'] = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE status='verified'")->fetch_assoc()['total'];
$stats['donation_count'] = $conn->query("SELECT COUNT(*) as count FROM donations WHERE status='verified'")->fetch_assoc()['count'];
$stats['monk_count'] = $conn->query("SELECT COUNT(*) as count FROM monks WHERE status='active'")->fetch_assoc()['count'];
$stats['doctor_count'] = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status='active'")->fetch_assoc()['count'];

$categories_result = $conn->query("SELECT name FROM categories WHERE type='donation'");
$donation_categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $donation_categories[] = $row['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assistant - Monastery System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        /* Chat page layout */
        .chat-page-wrapper {
            display: flex;
            flex-direction: column;
            height: calc(100vh - var(--topbar-height, 64px) - 40px);
            min-height: 500px;
        }

        .chat-card {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: var(--border-radius-lg, 16px);
            box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1));
            display: flex;
            flex-direction: column;
            flex: 1;
            overflow: hidden;
        }

        /* Chat Header */
        .chat-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            display: flex;
            align-items: center;
            gap: 14px;
            background: var(--bg-card, #ffffff);
        }

        .chat-header-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        .chat-header-info h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary, #0f172a);
        }

        .chat-header-info p {
            margin: 0;
            font-size: 12px;
            color: var(--text-secondary, #64748b);
        }

        .chat-header-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: var(--success, #f97316);
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* Messages Area */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            background: var(--bg-body, #f8fafc);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .chat-messages::-webkit-scrollbar {
            width: 5px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--slate-300, #cbd5e1);
            border-radius: 10px;
        }

        /* Messages */
        .message {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            max-width: 80%;
            animation: msg-slide-in 0.3s ease-out;
        }

        @keyframes msg-slide-in {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.user {
            flex-direction: row-reverse;
            align-self: flex-end;
        }

        .message.bot {
            align-self: flex-start;
        }

        .message-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }

        .message.bot .message-avatar {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #fff;
        }

        .message.user .message-avatar {
            background: var(--slate-600, #475569);
            color: #fff;
        }

        .message-content {
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 13.5px;
            line-height: 1.6;
            word-wrap: break-word;
        }

        .message.bot .message-content {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            color: var(--text-primary, #0f172a);
            border-top-left-radius: 4px;
        }

        .message.user .message-content {
            background: #f97316;
        }

        /* Typing Indicator */
        .typing-indicator {
            display: none;
            align-items: center;
            gap: 10px;
            max-width: 80%;
            align-self: flex-start;
        }

        .typing-indicator.active {
            display: flex;
        }

        .typing-bubble {
            background: var(--bg-card, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: 14px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .typing-indicator span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--slate-400, #94a3b8);
            animation: typing 1.4s infinite;
        }

        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-8px); }
        }

        /* Quick Questions */
        .quick-questions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 0 24px 12px;
            background: var(--bg-card, #ffffff);
            border-top: 1px solid var(--border-color, #e2e8f0);
        }

        .quick-questions-label {
            width: 100%;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary, #64748b);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-top: 12px;
            margin-bottom: 2px;
        }

        .quick-question-btn {
            background: var(--bg-body, #f8fafc);
            border: 1px solid var(--border-color, #e2e8f0);
            color: var(--text-primary, #0f172a);
            padding: 6px 14px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 12.5px;
            font-weight: 500;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .quick-question-btn:hover {
            background: #f97316;
            color: #fff;
            border-color: #f97316;
        }

        /* Chat Input Area */
        .chat-input-area {
            padding: 16px 24px 20px;
            background: var(--bg-card, #ffffff);
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .chat-input-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .chat-input-row .form-control-modern {
            flex: 1;
            padding: 11px 16px;
            border: 1px solid var(--input-border, #cbd5e1);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text-primary, #0f172a);
            background: var(--input-bg, #ffffff);
            transition: all 0.2s ease;
        }

        .chat-input-row .form-control-modern:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.12);
        }

        .chat-input-row .form-control-modern::placeholder {
            color: var(--slate-400, #94a3b8);
        }

        .chat-send-btn {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: none;
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .chat-send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(249, 115, 22, 0.35);
        }

        .chat-send-btn:active {
            transform: scale(0.97);
        }

        /* Language & Meta */
        .chat-meta-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .chat-meta-row select {
            padding: 5px 10px;
            border: 1px solid var(--input-border, #cbd5e1);
            border-radius: 8px;
            font-size: 12.5px;
            background: var(--input-bg, #ffffff);
            color: var(--text-primary, #0f172a);
        }

        .chat-meta-row select:focus {
            outline: none;
            border-color: #f97316;
        }

        .chat-meta-row small {
            color: var(--text-secondary, #64748b);
            font-size: 11.5px;
        }

        .chat-meta-row a {
            color: #f97316;
            font-weight: 500;
        }

        .chat-meta-row a:hover {
            text-decoration: underline;
        }

        /* Code Block */
        .code-block {
            background: var(--bg-body, #f8fafc);
            padding: 14px;
            border-radius: 10px;
            border-left: 3px solid #f97316;
            margin: 10px 0;
            font-size: 13px;
        }

        /* Modal overrides */
        .modal-header.chat-modal-header {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: #fff;
            border-radius: var(--border-radius-lg, 16px) var(--border-radius-lg, 16px) 0 0;
        }

        .modal-content {
            border-radius: var(--border-radius-lg, 16px);
            overflow: hidden;
        }

        /* Dark mode adjustments */
        [data-theme="dark"] .chat-messages {
            background: var(--bg-body);
        }

        [data-theme="dark"] .message.bot .message-content {
            background: var(--bg-card);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        [data-theme="dark"] .message.user .message-content {
            background: #f97316;
            color: #fff;
        }

        [data-theme="dark"] .quick-question-btn {
            background: var(--bg-card);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        [data-theme="dark"] .quick-question-btn:hover {
            background: #f97316;
            color: #fff;
            border-color: #f97316;
        }

        [data-theme="dark"] .chat-card,
        [data-theme="dark"] .chat-header,
        [data-theme="dark"] .chat-input-area,
        [data-theme="dark"] .quick-questions {
            background: var(--bg-card);
            border-color: var(--border-color);
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

    <div class="chat-page-wrapper">
        <div class="chat-card">
            <!-- Header -->
            <div class="chat-header">
                <div class="chat-header-icon">
                    <i class="bi bi-robot"></i>
                </div>
                <div class="chat-header-info">
                    <h5>AI Assistant <span style="font-weight:400;font-size:13px;color:var(--text-secondary, #64748b);">- සමුහ බුද්ධි සහායක</span></h5>
                    <p><span class="chat-header-status">Online</span> &middot; GPT-4 &middot; Bilingual (English &amp; Sinhala)</p>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages" id="chatBox">
                <div class="message bot">
                    <div class="message-avatar">🪷</div>
                    <div class="message-content">
                        <strong>Welcome! ආයුබෝවන්!</strong><br><br>
                        I'm your AI assistant for Seela Suwa Herath Bikshu Gilan Arana Healthcare &amp; Donation System.<br><br>
                        I can help you with:<br>
                        • Donation information and processes<br>
                        • Payment methods and procedures<br>
                        • Healthcare services available<br>
                        • Appointment booking guidance<br>
                        • General monastery information<br><br>
                        You can ask questions in <strong>English</strong> or <strong>Sinhala</strong> (සිංහල)!
                    </div>
                </div>
            </div>

            <!-- Quick Questions -->
            <div class="quick-questions">
                <div class="quick-questions-label">Quick questions</div>
                <span class="quick-question-btn" onclick="sendQuickQuestion('How can I make a donation?')">
                    💰 How to donate?
                </span>
                <span class="quick-question-btn" onclick="sendQuickQuestion('What are the payment methods?')">
                    💳 Payment methods
                </span>
                <span class="quick-question-btn" onclick="sendQuickQuestion('What will my donation be used for?')">
                    📊 Donation usage
                </span>
                <span class="quick-question-btn" onclick="sendQuickQuestion('පරිත්‍යාග කරන්නේ කෙසේද?')">
                    🇱🇰 පරිත්‍යාග කරන්නේ කෙසේද?
                </span>
            </div>

            <!-- Input Area -->
            <div class="chat-input-area">
                <div class="chat-input-row">
                    <input type="text" id="userInput" class="form-control-modern" placeholder="Type your question here... / ඔබගේ ප්‍රශ්නය මෙහි ටයිප් කරන්න..." onkeypress="handleKeyPress(event)">
                    <button class="chat-send-btn" onclick="sendMessage()" title="Send">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>

                <div class="typing-indicator" id="typingIndicator">
                    <div class="message-avatar" style="width:28px;height:28px;border-radius:8px;font-size:13px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;display:flex;align-items:center;justify-content:center;">🪷</div>
                    <div class="typing-bubble">
                        <span></span><span></span><span></span>
                        <small class="ms-1" style="color:var(--text-secondary,#64748b);">AI is thinking...</small>
                    </div>
                </div>

                <div class="chat-meta-row">
                    <div style="display:flex;align-items:center;gap:8px;">
                        <i class="bi bi-translate" style="color:var(--text-secondary,#64748b);font-size:13px;"></i>
                        <select id="languageSelect">
                            <option value="auto">🌐 Auto-detect</option>
                            <option value="en">🇬🇧 English</option>
                            <option value="si">🇱🇰 සිංහල (Sinhala)</option>
                        </select>
                    </div>
                    <small>
                        <i class="bi bi-info-circle"></i> Powered by OpenAI GPT-4 |
                        <a href="#" data-bs-toggle="modal" data-bs-target="#setupModal">Setup API Key</a>
                    </small>
                </div>
            </div>
        </div><!-- /.chat-card -->
    </div><!-- /.chat-page-wrapper -->

    <!-- System Context Script -->
    <script>
        const systemContext = {
            monastery_name: "Seela Suwa Herath Bikshu Gilan Arana",
            total_donations: "Rs. <?= number_format($stats['total_donations'], 2) ?>",
            donation_count: <?= $stats['donation_count'] ?>,
            monk_count: <?= $stats['monk_count'] ?>,
            doctor_count: <?= $stats['doctor_count'] ?>,
            donation_categories: <?= json_encode($donation_categories) ?>,
            payment_methods: ["Cash", "Bank Transfer", "Card", "PayHere Online Payment"],
            website: "http://localhost/test/"
        };
    </script>

    <!-- API Key Setup Modal -->
    <div class="modal fade" id="setupModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header chat-modal-header">
                    <h5 class="modal-title"><i class="bi bi-key"></i> OpenAI API Setup</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>🔑 Get Your OpenAI API Key</h6>
                    <ol>
                        <li>Visit <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI API Keys</a></li>
                        <li>Sign up or log in to your account</li>
                        <li>Click "Create new secret key"</li>
                        <li>Copy the API key (starts with "sk-")</li>
                        <li>Paste it in the file: <code>includes/openai_config.php</code></li>
                    </ol>

                    <h6 class="mt-3">💰 Pricing (Pay-as-you-go)</h6>
                    <ul>
                        <li><strong>GPT-4:</strong> ~$0.03 per 1K tokens (~750 words)</li>
                        <li><strong>GPT-3.5:</strong> ~$0.002 per 1K tokens (cheaper alternative)</li>
                        <li>Free tier: $5 credit for new accounts</li>
                    </ul>

                    <h6 class="mt-3">⚙️ Configuration File</h6>
                    <div class="code-block">
                        <pre><code>&lt;?php
define('OPENAI_API_KEY', 'sk-your-api-key-here');
define('OPENAI_MODEL', 'gpt-4'); // or 'gpt-3.5-turbo'
define('OPENAI_MAX_TOKENS', 500);
define('OPENAI_TEMPERATURE', 0.7);
?&gt;</code></pre>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Note:</strong> If API key is not configured, the chatbot will use fallback responses based on predefined rules.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="https://platform.openai.com/api-keys" target="_blank" class="btn" style="background:#f97316;color:#fff;border:none;">
                        <i class="bi bi-box-arrow-up-right"></i> Get API Key
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="chatbot_script.js"></script>
</body>
</html>
