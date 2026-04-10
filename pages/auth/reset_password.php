<?php
require_once __DIR__ . '/../../includes/init.php';
/**
 * Reset Password - Set new password using token from email
 */
session_start();
require_once __DIR__ . '/../../includes/db_config.php';
require_once __DIR__ . '/../../includes/csrf.php';

$conn = getDBConnection();
$error = '';
$success = '';
$validToken = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

// Validate token on GET
if (!empty($token) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $conn->prepare(
        "SELECT t.id, t.user_id, t.expires_at, u.name, u.email
         FROM password_reset_tokens t
         JOIN users u ON t.user_id = u.user_id
         WHERE t.token = ? AND t.used = 0 AND t.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $tokenData = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if ($tokenData) {
        $validToken = true;
    } else {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($token)) {
            $error = 'Invalid reset token.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
            $validToken = true;
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match.';
            $validToken = true;
        } else {
            // Verify token again
            $stmt = $conn->prepare(
                "SELECT t.id, t.user_id, u.name
                 FROM password_reset_tokens t
                 JOIN users u ON t.user_id = u.user_id
                 WHERE t.token = ? AND t.used = 0 AND t.expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $tokenData = $result ? $result->fetch_assoc() : null;
            $stmt->close();

            if (!$tokenData) {
                $error = 'This reset link is invalid or has expired. Please request a new one.';
            } else {
                // Update password
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->bind_param('si', $passwordHash, $tokenData['user_id']);

                if ($stmt->execute()) {
                    // Mark token as used
                    $stmt2 = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
                    $stmt2->bind_param('i', $tokenData['id']);
                    $stmt2->execute();
                    $stmt2->close();

                    // Also invalidate all other tokens for this user
                    $stmt3 = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
                    $stmt3->bind_param('i', $tokenData['user_id']);
                    $stmt3->execute();
                    $stmt3->close();

                    $success = 'Your password has been reset successfully! You can now sign in with your new password.';
                } else {
                    $error = 'Failed to update password. Please try again.';
                    $validToken = true;
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Seela suwa herath</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --ivory:#FFFBF7;--cream:#FEF3E8;--sand:#F5E0C8;--warm-gray:#C9A88A;
            --deep-sage:#D4622A;--gold:#F0A050;
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
        .nav-links a:hover{color:var(--deep-sage)}
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

        .error-msg{background:rgba(192,97,74,.08);border:1px solid rgba(192,97,74,.25);color:var(--error);padding:12px 16px;border-radius:8px;font-size:.85rem;margin-bottom:24px;line-height:1.6}
        .success-msg{background:rgba(63,146,87,.08);border:1px solid rgba(63,146,87,.25);color:#2f7a46;padding:14px 16px;border-radius:8px;font-size:.85rem;margin-bottom:24px;line-height:1.6}
        .success-msg .icon{font-size:1.5rem;display:block;margin-bottom:6px}

        .form-group{margin-bottom:20px}
        .form-group label{display:block;font-size:.78rem;font-weight:500;letter-spacing:.06em;text-transform:uppercase;color:var(--text-mid);margin-bottom:8px}
        .form-group input{width:100%;padding:13px 16px;border:1.5px solid var(--border);border-radius:10px;background:var(--ivory);color:var(--text-dark);font-family:'Jost',sans-serif;font-size:.95rem;font-weight:300;transition:border-color .2s,background .2s;outline:none}
        .form-group input:focus{border-color:var(--deep-sage);background:var(--white)}
        .form-group input::placeholder{color:var(--warm-gray)}

        .pw-wrapper{position:relative}
        .pw-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--warm-gray);cursor:pointer;font-size:1rem;padding:4px}
        .pw-toggle:hover{color:var(--text-mid)}

        .pw-strength{height:4px;border-radius:2px;background:var(--sand);margin-top:8px;overflow:hidden;transition:all .3s}
        .pw-strength-bar{height:100%;border-radius:2px;transition:width .3s,background .3s;width:0}
        .pw-hint{font-size:.75rem;color:var(--text-light);margin-top:6px}

        .btn-submit{width:100%;padding:14px;background:var(--deep-sage);color:var(--white);border:none;border-radius:10px;font-family:'Jost',sans-serif;font-size:.95rem;font-weight:500;letter-spacing:.04em;cursor:pointer;transition:all .25s}
        .btn-submit:hover{background:var(--text-dark);transform:translateY(-1px)}
        .btn-submit:active{transform:translateY(0)}

        .btn-signin{display:inline-flex;align-items:center;gap:8px;padding:13px 28px;border:1.5px solid var(--border);border-radius:10px;font-size:.88rem;color:var(--text-mid);text-decoration:none;transition:all .2s;margin-top:16px}
        .btn-signin:hover{border-color:var(--deep-sage);color:var(--text-dark);background:rgba(212,98,42,.04)}

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
        <li><a href="register.php">Register</a></li>
        <li><a href="/test/pages/auth/login.php">Sign In</a></li>
        <li><a href="/test/pages/public/public_donate.php" class="nav-donate">Donate Now</a></li>
    </ul>
</nav>

<div class="left-panel">
    <div class="left-panel-bg"></div>
    <div class="left-content">
        <h2 class="left-headline">Create a<br><em>New Password</em></h2>
        <p class="left-desc">Choose a strong, unique password to keep your account secure. We recommend at least 8 characters with a mix of letters and numbers.</p>
        <div class="left-ornament">
            <div class="ornament-row"><div class="ornament-icon">🔑</div><div class="ornament-text"><div class="title">Strong Password</div><div class="sub">At least 6 characters required</div></div></div>
            <div class="ornament-row"><div class="ornament-icon">🛡️</div><div class="ornament-text"><div class="title">Account Security</div><div class="sub">Your data is encrypted and protected</div></div></div>
            <div class="ornament-row"><div class="ornament-icon">✅</div><div class="ornament-text"><div class="title">Instant Access</div><div class="sub">Sign in immediately after reset</div></div></div>
        </div>
    </div>
    <div class="left-footer">© 2026 Seela suwa herath · Sri Lanka</div>
</div>

<div class="right-panel">
    <div class="form-wrapper">
        <div class="form-header">
            <h1>Reset Password</h1>
            <p>Enter your new password below.</p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="success-msg">
            <span class="icon">✅</span>
            <?= htmlspecialchars($success) ?>
        </div>
        <div style="text-align:center">
            <a href="/test/pages/auth/login.php" class="btn-signin">🔐 Sign In Now</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($validToken): ?>
        <form method="POST" action="/test/pages/auth/reset_password.php">
            <?php csrfField(); ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label for="password">New Password</label>
                <div class="pw-wrapper">
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required minlength="6" autocomplete="new-password">
                    <button type="button" class="pw-toggle" onclick="togglePw('password')" title="Show/hide">👁</button>
                </div>
                <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
                <div class="pw-hint" id="pwHint">Enter at least 6 characters</div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm New Password</label>
                <div class="pw-wrapper">
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Repeat your new password" required minlength="6" autocomplete="new-password">
                    <button type="button" class="pw-toggle" onclick="togglePw('password_confirm')" title="Show/hide">👁</button>
                </div>
            </div>

            <button type="submit" class="btn-submit">Set New Password</button>
        </form>
        <?php endif; ?>

        <?php if (!$validToken && empty($success)): ?>
        <div style="text-align:center;margin-top:8px">
            <a href="forgot_password.php" class="btn-signin">📧 Request New Reset Link</a>
        </div>
        <?php endif; ?>

        <div class="back-link"><a href="/test/pages/auth/login.php">← Back to Sign In</a></div>
    </div>
</div>

<footer>
    <div class="foot-grid">
        <div><div class="foot-brand">☸ Seela suwa herath</div><p class="foot-tag">Supporting monastery welfare through community generosity, transparent governance, and compassionate care.</p></div>
        <div class="foot-col"><h4>Platform</h4><ul><li><a href="/test/pages/public/public_donate.php">Donate</a></li><li><a href="/test/pages/public/public_transparency.php">Transparency</a></li><li><a href="register.php">Register</a></li><li><a href="/test/pages/auth/login.php">Sign In</a></li></ul></div>
        <div class="foot-col"><h4>Welfare</h4><ul><li><a href="#">Healthcare</a></li><li><a href="#">Housing</a></li><li><a href="#">Appointments</a></li><li><a href="#">Reports</a></li></ul></div>
        <div class="foot-col"><h4>Info</h4><ul><li><a href="#">About Us</a></li><li><a href="#">Contact</a></li><li><a href="#">Privacy Policy</a></li></ul></div>
    </div>
    <div class="foot-btm"><span>© 2026 Seela suwa herath Monastery Welfare Platform</span><span>Made with ❤ in Sri Lanka</span></div>
</footer>

<script>
function togglePw(id) {
    const pw = document.getElementById(id);
    pw.type = pw.type === 'password' ? 'text' : 'password';
}

// Password strength indicator
const pwInput = document.getElementById('password');
if (pwInput) {
    pwInput.addEventListener('input', function() {
        const val = this.value;
        const bar = document.getElementById('pwBar');
        const hint = document.getElementById('pwHint');
        let strength = 0, label = '', color = '';

        if (val.length >= 6) strength++;
        if (val.length >= 8) strength++;
        if (/[A-Z]/.test(val)) strength++;
        if (/[0-9]/.test(val)) strength++;
        if (/[^A-Za-z0-9]/.test(val)) strength++;

        if (val.length === 0) { label = 'Enter at least 6 characters'; color = '#F5E0C8'; bar.style.width = '0'; }
        else if (strength <= 1) { label = 'Weak'; color = '#ef4444'; bar.style.width = '20%'; }
        else if (strength <= 2) { label = 'Fair'; color = '#f59e0b'; bar.style.width = '40%'; }
        else if (strength <= 3) { label = 'Good'; color = '#22c55e'; bar.style.width = '70%'; }
        else { label = 'Strong'; color = '#16a34a'; bar.style.width = '100%'; }

        bar.style.background = color;
        hint.textContent = val.length === 0 ? label : label + ' password';
        hint.style.color = color;
    });
}
</script>
</body>
</html>
