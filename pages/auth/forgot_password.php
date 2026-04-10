<?php
require_once __DIR__ . '/../../includes/init.php';
/**
 * Forgot Password - Request Reset Link
 */
session_start();
require_once __DIR__ . '/../../includes/db_config.php';
require_once __DIR__ . '/../../includes/csrf.php';

$conn = getDBConnection();

// Ensure password_reset_tokens table exists
$conn->query("CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$error = '';
$success = '';
$devResetLink = '';
$TOKEN_EXPIRY_MINUTES = 30;
$isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare("SELECT user_id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            // Always show success to prevent email enumeration
            $success = 'If an account with that email exists, we\'ve sent a password reset link. Please check your inbox and spam folder.';

            if ($user) {
                // Invalidate existing tokens
                $stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
                $stmt->bind_param('i', $user['user_id']);
                $stmt->execute();
                $stmt->close();

                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime("+{$TOKEN_EXPIRY_MINUTES} minutes"));

                $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->bind_param('iss', $user['user_id'], $token, $expiresAt);
                $stmt->execute();
                $stmt->close();

                // Build reset link
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                $resetLink = "{$protocol}://{$host}{$basePath}/reset_password.php?token=" . urlencode($token);

                // On localhost, show link directly (SMTP likely not configured)
                if ($isLocalhost) {
                    $devResetLink = $resetLink;
                }

                // Send email
                try {
                    require_once __DIR__ . '/../../includes/email_helper.php';
                    require_once __DIR__ . '/../../email_templates/password_reset.php';
                    $emailBody = getPasswordResetTemplate($user['name'], $resetLink, $TOKEN_EXPIRY_MINUTES);
                    $subject = "Password Reset - " . (defined('MONASTERY_NAME') ? MONASTERY_NAME : 'Seela Suwa Herath');
                    sendEmail($user['email'], $subject, $emailBody, $user['name']);
                } catch (Exception $e) {
                    error_log("Password reset email error: " . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Seela suwa herath</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/public-auth.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --ivory:#FFFBF7;--cream:#FEF3E8;--sand:#F5E0C8;--warm-gray:#C9A88A;
            --muted-sage:#F0864A;--deep-sage:#D4622A;--gold:#F0A050;
            --text-dark:#2C2820;--text-mid:#5A5248;--text-light:#8A7F74;
            --white:#FFFFFF;--border:rgba(210,170,130,0.28);--error:#C0614A;
        }
        html,body{height:100%;font-family:'Jost',sans-serif;font-weight:300;background:var(--ivory);color:var(--text-dark)}
        body{display:grid;grid-template-columns:1fr 1fr;min-height:100vh;padding-top:72px}

        nav{position:fixed;top:0;left:0;right:0;z-index:200;padding:0 6%;height:72px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.97);backdrop-filter:blur(16px);border-bottom:1px solid var(--border)}
        .nav-logo{display:flex;align-items:center;gap:12px;text-decoration:none}
        .nav-logo-mark{width:36px;height:36px;background:linear-gradient(135deg,var(--deep-sage),var(--gold));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff}
        .nav-logo-name{font-family:'Cormorant Garamond',serif;font-size:1.25rem;font-weight:600;color:var(--text-dark)}
        .nav-logo-sub{font-size:.6rem;color:var(--text-light);letter-spacing:.13em;text-transform:uppercase;display:block;margin-top:-4px}
        .nav-links{display:flex;align-items:center;gap:28px;list-style:none}
        .nav-links a{text-decoration:none;color:var(--text-mid);font-size:.83rem;font-weight:400;letter-spacing:.06em;text-transform:uppercase;transition:color .2s}
        .nav-links a:hover,.nav-links a.active{color:var(--deep-sage)}
        .nav-donate{background:var(--deep-sage)!important;color:#fff!important;padding:9px 24px!important;border-radius:40px!important;font-weight:500!important}
        .nav-donate:hover{background:var(--text-dark)!important}

        .left-panel{background:var(--cream);padding:56px 64px;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;border-right:1px solid var(--border);min-height:calc(100vh - 72px)}
        .left-panel-bg{position:absolute;inset:0;z-index:0;background:radial-gradient(ellipse 80% 60% at 20% 80%,rgba(240,160,80,.10),transparent),radial-gradient(ellipse 60% 60% at 80% 20%,rgba(212,98,42,.08),transparent)}
        .left-content{position:relative;z-index:1}
        .left-headline{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,3.5vw,3rem);font-weight:300;line-height:1.2;color:var(--text-dark);margin-bottom:20px}
        .left-headline em{font-style:italic;color:var(--deep-sage)}
        .left-desc{font-size:.95rem;color:var(--text-mid);line-height:1.8;max-width:340px}
        .left-ornament{margin-top:48px}
        .ornament-row{display:flex;align-items:center;gap:16px;margin-bottom:20px}
        .ornament-icon{width:44px;height:44px;background:var(--white);border-radius:10px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0}
        .ornament-text .title{font-size:.9rem;font-weight:500;color:var(--text-dark)}
        .ornament-text .sub{font-size:.78rem;color:var(--text-light)}
        .left-footer{position:relative;z-index:1;font-size:.78rem;color:var(--text-light)}

        .right-panel{padding:56px 72px;display:flex;flex-direction:column;justify-content:center;background:var(--white);min-height:calc(100vh - 72px)}
        .form-wrapper{max-width:380px;width:100%;margin:0 auto}
        .form-header{margin-bottom:36px}
        .form-header h1{font-family:'Cormorant Garamond',serif;font-size:2.2rem;font-weight:400;color:var(--text-dark);margin-bottom:8px}
        .form-header p{font-size:.9rem;color:var(--text-light);line-height:1.7}

        .error-msg{background:rgba(192,97,74,.08);border:1px solid rgba(192,97,74,.25);color:var(--error);padding:12px 16px;border-radius:8px;font-size:.85rem;margin-bottom:24px}
        .success-msg{background:rgba(63,146,87,.08);border:1px solid rgba(63,146,87,.25);color:#2f7a46;padding:14px 16px;border-radius:8px;font-size:.85rem;margin-bottom:24px;line-height:1.6}
        .success-msg .icon{font-size:1.5rem;display:block;margin-bottom:6px}

        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-size:.78rem;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--text-mid);margin-bottom:8px}
        .form-group input{width:100%;padding:13px 16px;border:1.5px solid var(--border);border-radius:10px;background:var(--ivory);color:var(--text-dark);font-family:'Jost',sans-serif;font-size:.95rem;font-weight:300;transition:border-color .2s,background .2s;outline:none}
        .form-group input:focus{border-color:var(--deep-sage);background:var(--white)}
        .form-group input::placeholder{color:var(--warm-gray)}

        .btn-submit{width:100%;padding:14px;background:var(--deep-sage);color:var(--white);border:none;border-radius:10px;font-family:'Jost',sans-serif;font-size:.95rem;font-weight:500;letter-spacing:.04em;cursor:pointer;transition:all .25s}
        .btn-submit:hover{background:var(--text-dark);transform:translateY(-1px)}
        .btn-submit:active{transform:translateY(0)}

        .back-link{text-align:center;margin-top:28px;font-size:.83rem}
        .back-link a{color:var(--text-light);text-decoration:none}
        .back-link a:hover{color:var(--deep-sage)}

        footer{background:#4A3B32;padding:60px 6% 28px;grid-column:1/-1;width:100%}
        .foot-grid{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:24px}
        .foot-brand{font-family:'EB Garamond',serif;font-size:1.5rem;color:#fff;margin-bottom:12px;font-weight:600}
        .foot-tag{font-size:.84rem;color:rgba(255,255,255,.38);line-height:1.7;max-width:240px}
        .foot-col h4{font-size:.68rem;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:14px}
        .foot-col ul{list-style:none}.foot-col ul li{margin-bottom:9px}
        .foot-col ul a{color:rgba(255,255,255,.48);text-decoration:none;font-size:.86rem;transition:color .2s}
        .foot-col ul a:hover{color:var(--gold)}
        .foot-btm{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;font-size:.77rem;color:rgba(255,255,255,.22)}

        @media(max-width:768px){.nav-links{display:none}body{grid-template-columns:1fr}.left-panel{display:none}.right-panel{padding:48px 28px}}
        @keyframes fadeIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
        .form-wrapper{animation:fadeIn .5s ease both}
    </style>
</head>
<body>
<nav>
    <a href="/test/index.php" class="nav-logo">
        <div class="nav-logo-mark">&#9784;</div>
        <div><span class="nav-logo-name">Seela suwa herath</span><span class="nav-logo-sub">Monastery Welfare</span></div>
    </a>
    <ul class="nav-links">
        <li><a href="/test/index.php#mission">Our Mission</a></li>
        <li><a href="/test/index.php#how">How It Works</a></li>
        <li><a href="/test/pages/public/public_transparency.php">Transparency</a></li>
        <li><a href="/test/pages/auth/register.php">Register</a></li>
        <li><a href="/test/pages/auth/login.php">Sign In</a></li>
        <li><a href="/test/pages/public/public_donate.php" class="nav-donate">Donate Now</a></li>
    </ul>
</nav>

<div class="left-panel">
    <div class="left-panel-bg"></div>
    <div class="left-content">
        <h2 class="left-headline">Reset Your<br><em>Password</em></h2>
        <p class="left-desc">Don't worry — it happens to everyone. Enter your email and we'll send you a secure link to reset your password.</p>
        <div class="left-ornament">
            <div class="ornament-row"><div class="ornament-icon">📧</div><div class="ornament-text"><div class="title">Check Your Inbox</div><div class="sub">We'll send a reset link to your email</div></div></div>
            <div class="ornament-row"><div class="ornament-icon">🔒</div><div class="ornament-text"><div class="title">Secure & Private</div><div class="sub">Link expires in 30 minutes</div></div></div>
            <div class="ornament-row"><div class="ornament-icon">✨</div><div class="ornament-text"><div class="title">Quick & Easy</div><div class="sub">Set a new password in seconds</div></div></div>
        </div>
    </div>
    <div class="left-footer">© 2026 Seela suwa herath · Sri Lanka</div>
</div>

<div class="right-panel">
    <div class="form-wrapper">
        <div class="form-header">
            <h1>Forgot Password</h1>
            <p>Enter the email address associated with your account and we'll send you a link to reset your password.</p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="success-msg"><span class="icon">✉️</span> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($devResetLink)): ?>
        <div style="background:rgba(212,98,42,0.06);border:1.5px solid rgba(212,98,42,0.2);padding:16px;border-radius:10px;margin-bottom:20px;">
            <div style="font-size:.72rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--deep-sage);margin-bottom:8px;">🔧 Localhost Dev Mode</div>
            <p style="font-size:.82rem;color:var(--text-mid);margin-bottom:12px;line-height:1.5;">SMTP not configured. Use this link to reset your password:</p>
            <a href="<?= htmlspecialchars($devResetLink) ?>" style="display:inline-block;background:var(--deep-sage);color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-size:.85rem;font-weight:500;transition:all .2s;">Reset Password Now →</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (empty($success)): ?>
        <form method="POST" action="/test/pages/auth/forgot_password.php">
            <?php csrfField(); ?>
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email" autofocus>
            </div>
            <button type="submit" class="btn-submit">Send Reset Link</button>
        </form>
        <?php endif; ?>

        <div class="back-link"><a href="/test/pages/auth/login.php">← Back to Sign In</a></div>
    </div>
</div>

<footer>
    <div class="foot-grid">
        <div><div class="foot-brand">☸ Seela suwa herath</div><p class="foot-tag">Supporting monastery welfare through community generosity, transparent governance, and compassionate care.</p></div>
        <div class="foot-col"><h4>Platform</h4><ul><li><a href="/test/pages/public/public_donate.php">Donate</a></li><li><a href="/test/pages/public/public_transparency.php">Transparency</a></li><li><a href="/test/pages/auth/register.php">Register</a></li><li><a href="/test/pages/auth/login.php">Sign In</a></li></ul></div>
        <div class="foot-col"><h4>Welfare</h4><ul><li><a href="#">Healthcare</a></li><li><a href="#">Housing</a></li><li><a href="#">Appointments</a></li><li><a href="#">Reports</a></li></ul></div>
        <div class="foot-col"><h4>Info</h4><ul><li><a href="#">About Us</a></li><li><a href="#">Contact</a></li><li><a href="#">Privacy Policy</a></li></ul></div>
    </div>
    <div class="foot-btm"><span>© 2026 Seela suwa herath Monastery Welfare Platform</span><span>Made with ❤ in Sri Lanka</span></div>
</footer>
</body>
</html>
