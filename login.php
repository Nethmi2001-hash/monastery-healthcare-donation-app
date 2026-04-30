<?php
session_start();
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/csrf.php';

$conn = getDBConnection();
$error = $_SESSION['login_error'] ?? '';
$success = '';
unset($_SESSION['login_error']);

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Registration successful. Please sign in.';
}

if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been logged out successfully.';
}

if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Your session has expired. Please sign in again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Both email and password are required.';
        } else {
            $stmt = $conn->prepare(
                "SELECT u.user_id, u.name, u.email, u.password_hash, u.status, u.role_id, r.role_name
                 FROM users u
                 JOIN roles r ON u.role_id = r.role_id
                 WHERE u.email = ?
                 LIMIT 1"
            );

            if (!$stmt) {
                $error = 'Unable to process sign in right now. Please try again.';
            } else {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result ? $result->fetch_assoc() : null;
                $stmt->close();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $error = 'Invalid email or password.';
                } elseif (strtolower((string)$user['status']) !== 'active') {
                    $error = 'Your account is not active. Please contact support.';
                } else {
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = (int)$user['user_id'];
                    $_SESSION['username'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role_id'] = (int)$user['role_id'];
                    $_SESSION['role_name'] = $user['role_name'];
                    $_SESSION['last_activity'] = time();

                    header('Location: dashboard.php');
                    exit();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Seela suwa herath</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
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
            --text-dark:  #2C2820;
            --text-mid:   #5A5248;
            --text-light: #8A7F74;
            --white:      #FFFFFF;
            --border:     rgba(210,170,130,0.28);
            --error:      #C0614A;
        }
        html, body {
            height: 100%;
            font-family: 'Jost', sans-serif;
            font-weight: 300;
            background: var(--ivory);
            color: var(--text-dark);
        }
        body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            background: var(--cream);
            padding: 56px 64px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            border-right: 1px solid var(--border);
        }
        .left-panel-bg {
            position: absolute; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 80% 60% at 20% 80%, rgba(240,160,80,0.10), transparent),
                radial-gradient(ellipse 60% 60% at 80% 20%, rgba(212,98,42,0.08), transparent);
        }
        .left-content { position: relative; z-index: 1; }
        .brand {
            display: flex; align-items: center; gap: 12px;
            text-decoration: none;
            margin-bottom: 56px;
        }
        .brand-mark {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #D4622A, #F0A050);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            color: white;
        }
        .brand-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .brand-sub {
            font-size: 0.65rem;
            color: var(--text-light);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            display: block;
            margin-top: -3px;
        }
        .left-headline {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 3.5vw, 3rem);
            font-weight: 300;
            line-height: 1.2;
            color: var(--text-dark);
            margin-bottom: 20px;
        }
        .left-headline em { font-style: italic; color: var(--deep-sage); }
        .left-desc {
            font-size: 0.95rem;
            color: var(--text-mid);
            line-height: 1.8;
            max-width: 340px;
        }
        .left-ornament {
            margin-top: 48px;
        }
        .ornament-row {
            display: flex; align-items: center; gap: 16px;
            margin-bottom: 20px;
        }
        .ornament-icon {
            width: 44px; height: 44px;
            background: var(--white);
            border-radius: 10px;
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .ornament-text .title {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        .ornament-text .sub {
            font-size: 0.78rem;
            color: var(--text-light);
        }
        .left-footer {
            position: relative; z-index: 1;
            font-size: 0.78rem;
            color: var(--text-light);
        }

        /* ── RIGHT PANEL ── */
        .right-panel {
            padding: 56px 72px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
        }
        .form-wrapper {
            max-width: 360px;
            width: 100%;
            margin: 0 auto;
        }
        .form-header {
            margin-bottom: 36px;
        }
        .form-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 400;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .form-header p {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        .form-header p a {
            color: var(--deep-sage);
            text-decoration: none;
            font-weight: 500;
        }
        .form-header p a:hover { text-decoration: underline; }

        .error-msg {
            background: rgba(192,97,74,0.08);
            border: 1px solid rgba(192,97,74,0.25);
            color: var(--error);
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 24px;
        }
        .success-msg {
            background: rgba(63, 146, 87, 0.08);
            border: 1px solid rgba(63, 146, 87, 0.25);
            color: #2f7a46;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text-mid);
            margin-bottom: 8px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 13px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            background: var(--ivory);
            color: var(--text-dark);
            font-family: 'Jost', sans-serif;
            font-size: 0.95rem;
            font-weight: 300;
            transition: border-color 0.2s, background 0.2s;
            outline: none;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--deep-sage);
            background: var(--white);
        }
        .form-group input::placeholder { color: var(--warm-gray); }

        .pw-wrapper { position: relative; }
        .pw-toggle {
            position: absolute; right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--warm-gray);
            cursor: pointer; font-size: 1rem;
            padding: 4px;
        }
        .pw-toggle:hover { color: var(--text-mid); }

        .form-extras {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px;
        }
        .remember {
            display: flex; align-items: center; gap: 8px;
            font-size: 0.85rem;
            color: var(--text-mid);
            cursor: pointer;
        }
        .remember input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: #D4622A;
        }
        .forgot-link {
            font-size: 0.85rem;
            color: var(--deep-sage);
            text-decoration: none;
        }
        .forgot-link:hover { text-decoration: underline; }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: var(--deep-sage);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-family: 'Jost', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.04em;
            cursor: pointer;
            transition: all 0.25s;
        }
        .btn-submit:hover {
            background: var(--text-dark);
            transform: translateY(-1px);
        }
        .btn-submit:active { transform: translateY(0); }

        .divider {
            display: flex; align-items: center; gap: 12px;
            margin: 24px 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px;
            background: var(--border);
        }
        .divider span {
            font-size: 0.78rem;
            color: var(--text-light);
        }

        .donate-link-row {
            text-align: center;
        }
        .donate-link-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 13px 28px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 0.88rem;
            color: var(--text-mid);
            text-decoration: none;
            transition: all 0.2s;
        }
        .donate-link-btn:hover {
            border-color: var(--gold);
            color: var(--text-dark);
            background: rgba(240,160,80,0.06);
        }

        .back-link {
            text-align: center;
            margin-top: 28px;
            font-size: 0.83rem;
        }
        .back-link a {
            color: var(--text-light);
            text-decoration: none;
        }
        .back-link a:hover { color: var(--deep-sage); }

        @media (max-width: 768px) {
            body { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { padding: 48px 28px; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .form-wrapper { animation: fadeIn 0.5s ease both; }
    </style>
</head>
<body>

<!-- LEFT PANEL -->
<div class="left-panel">
    <div class="left-panel-bg"></div>
    <div class="left-content">
        <a href="index.php" class="brand">
            <div class="brand-mark">☸</div>
            <div>
                <span class="brand-name">Seela suwa herath</span>
                <span class="brand-sub">Monastery Welfare</span>
            </div>
        </a>
        <h2 class="left-headline">
            Welcome<br>
            <em>Back</em>
        </h2>
        <p class="left-desc">
            Manage welfare, track donations, and support the monks who give so much to our community.
        </p>
        <div class="left-ornament">
            <div class="ornament-row">
                <div class="ornament-icon">🏥</div>
                <div class="ornament-text">
                    <div class="title">Doctor Dashboards</div>
                    <div class="sub">Manage appointments & healthcare</div>
                </div>
            </div>
            <div class="ornament-row">
                <div class="ornament-icon">🙏</div>
                <div class="ornament-text">
                    <div class="title">Donor Portal</div>
                    <div class="sub">Track your contributions</div>
                </div>
            </div>
            <div class="ornament-row">
                <div class="ornament-icon">📊</div>
                <div class="ornament-text">
                    <div class="title">Admin & Reports</div>
                    <div class="sub">Full transparency & management</div>
                </div>
            </div>
        </div>
    </div>
    <div class="left-footer">© 2026 Seela suwa herath · Sri Lanka</div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
    <div class="form-wrapper">
        <div class="form-header">
            <h1>Sign In</h1>
            <p>New here? <a href="register.php">Create an account</a></p>
        </div>

        <?php if (!empty($success)): ?>
        <div class="success-msg">✓ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
        <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <?php if (function_exists('csrfField')): ?>
            <?php csrfField(); ?>
            <?php endif; ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="pw-toggle" onclick="togglePw()" title="Show/hide">👁</button>
                </div>
            </div>

            <div class="form-extras">
                <label class="remember">
                    <input type="checkbox" name="remember"> Remember me
                </label>
                <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn-submit">Sign In</button>
        </form>

        <div class="divider"><span>or</span></div>

        <div class="donate-link-row">
            <a href="public_donate.php" class="donate-link-btn">
                🙏 Donate without signing in
            </a>
        </div>

        <div class="back-link">
            <a href="index.php">← Back to home</a>
        </div>
    </div>
</div>

<script>
function togglePw() {
    const pw = document.getElementById('password');
    pw.type = pw.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
