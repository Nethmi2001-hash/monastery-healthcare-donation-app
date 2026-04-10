<?php
require_once __DIR__ . '/../../includes/init.php';
session_start();
require_once __DIR__ . '/../../includes/db_config.php';
require_once __DIR__ . '/../../includes/csrf.php';

$conn = getDBConnection();
$error = $_SESSION['register_error'] ?? '';
unset($_SESSION['register_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $role = strtolower(trim($_POST['role'] ?? 'donor'));
        $termsAccepted = isset($_POST['terms']);

        $roleMap = [
            'admin' => 'Admin',
            'doctor' => 'Doctor',
            'donor' => 'Donor',
            'monk' => 'Monk'
        ];

        if ($first_name === '' || $last_name === '' || $email === '' || $password === '') {
            $error = 'Please fill all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match.';
        } elseif (!$termsAccepted) {
            $error = 'You must accept the terms to continue.';
        } elseif (!isset($roleMap[$role])) {
            $error = 'Invalid role selected.';
        } else {
            $roleName = $roleMap[$role];
            $roleStmt = $conn->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');

            if (!$roleStmt) {
                $error = 'Unable to process registration right now. Please try again.';
            } else {
                $roleStmt->bind_param('s', $roleName);
                $roleStmt->execute();
                $roleResult = $roleStmt->get_result();
                $roleRow = $roleResult ? $roleResult->fetch_assoc() : null;
                $roleStmt->close();

                if (!$roleRow) {
                    $error = 'Selected role is not configured in the system.';
                } else {
                    $checkStmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');

                    if (!$checkStmt) {
                        $error = 'Unable to process registration right now. Please try again.';
                    } else {
                        $checkStmt->bind_param('s', $email);
                        $checkStmt->execute();
                        $existing = $checkStmt->get_result();
                        $emailExists = $existing && $existing->num_rows > 0;
                        $checkStmt->close();

                        if ($emailExists) {
                            $error = 'This email is already registered. Please sign in.';
                        } else {
                            $fullName = trim($first_name . ' ' . $last_name);
                            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                            $insertStmt = $conn->prepare(
                                "INSERT INTO users (name, email, phone, role_id, password_hash, status)
                                 VALUES (?, ?, ?, ?, ?, 'active')"
                            );

                            if (!$insertStmt) {
                                $error = 'Unable to complete registration. Please try again.';
                            } else {
                                $roleId = (int)$roleRow['role_id'];
                                $insertStmt->bind_param('sssis', $fullName, $email, $phone, $roleId, $passwordHash);
                                $ok = $insertStmt->execute();
                                $insertStmt->close();

                                if ($ok) {
                                    header('Location: /test/pages/auth/login.php?registered=1');
                                    exit();
                                }

                                $error = 'Registration failed. Please try again.';
                            }
                        }
                    }
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
    <title>Create Account — Seela suwa herath</title>
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
            --orange:     #D4622A;
            --orange-light:#F0A050;
            --gold:       #F0A050;
            --text-dark:  #2C2820;
            --text-mid:   #5A5248;
            --text-light: #8A7F74;
            --white:      #FFFFFF;
            --border:     rgba(210,170,130,0.28);
            --error:      #C0614A;
        }
        html, body {
            font-family: 'Jost', sans-serif;
            font-weight: 300;
            background: var(--ivory);
            color: var(--text-dark);
            min-height: 100vh;
        }
        body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 100vh;
            padding-top: 72px;
        }

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

        /* ── LEFT ── */
        .left-panel {
            background: var(--cream);
            padding: 56px 64px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            border-right: 1px solid var(--border);
            min-height: calc(100vh - 72px);
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
            text-decoration: none; margin-bottom: 72px;
        }
        .brand-mark {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--orange), var(--orange-light));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            border: none;
        }
        .brand-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem; font-weight: 600;
            color: var(--text-dark);
        }
        .brand-sub {
            font-size: 0.65rem; color: var(--text-light);
            letter-spacing: 0.12em; text-transform: uppercase;
            display: block; margin-top: -3px;
        }
        .left-headline {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2rem, 3vw, 2.8rem);
            font-weight: 300; line-height: 1.2;
            color: var(--text-dark); margin-bottom: 20px;
        }
        .left-headline em { font-style: italic; color: var(--orange); }
        .left-desc {
            font-size: 0.95rem; color: var(--text-mid);
            line-height: 1.8; max-width: 320px; margin-bottom: 48px;
        }
        .benefits-list { list-style: none; }
        .benefits-list li {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            color: var(--text-mid);
            font-size: 0.88rem;
        }
        .benefits-list li:last-child { border-bottom: none; }
        .benefit-check {
            width: 20px; height: 20px; flex-shrink: 0;
            background: var(--orange); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.65rem; color: white; margin-top: 2px;
        }
        .left-footer {
            position: relative; z-index: 1;
            font-size: 0.78rem; color: var(--text-light);
        }

        /* ── RIGHT ── */
        .right-panel {
            background: var(--white);
            padding: 48px 72px;
            display: flex; flex-direction: column; justify-content: center;
            overflow-y: auto;
            min-height: calc(100vh - 72px);
        }
        .form-wrapper { max-width: 400px; width: 100%; margin: 0 auto; }
        .form-header { margin-bottom: 32px; }
        .form-header h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem; font-weight: 400;
            color: var(--text-dark); margin-bottom: 8px;
        }
        .form-header p { font-size: 0.9rem; color: var(--text-light); }
        .form-header p a { color: var(--orange); text-decoration: none; font-weight: 500; }
        .form-header p a:hover { text-decoration: underline; }

        .error-msg {
            background: rgba(192,97,74,0.08);
            border: 1px solid rgba(192,97,74,0.25);
            color: var(--error);
            padding: 12px 16px; border-radius: 8px;
            font-size: 0.85rem; margin-bottom: 20px;
        }
        .form-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; font-size: 0.78rem; font-weight: 500;
            letter-spacing: 0.06em; text-transform: uppercase;
            color: var(--text-mid); margin-bottom: 7px;
        }
        .form-group input,
        .form-group select {
            width: 100%; padding: 12px 14px;
            border: 1.5px solid var(--border); border-radius: 10px;
            background: var(--ivory); color: var(--text-dark);
            font-family: 'Jost', sans-serif; font-size: 0.92rem; font-weight: 300;
            transition: border-color 0.2s, background 0.2s; outline: none;
            appearance: none;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--orange); background: var(--white);
        }
        .form-group input::placeholder { color: var(--warm-gray); }
        .pw-wrapper { position: relative; }
        .pw-toggle {
            position: absolute; right: 12px; top: 50%;
            transform: translateY(-50%);
            background: none; border: none; color: var(--warm-gray);
            cursor: pointer; font-size: 0.95rem; padding: 4px;
        }
        .pw-toggle:hover { color: var(--text-mid); }

        .role-select-group label { margin-bottom: 10px; display: block; }
        .role-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
        }
        .role-option { position: relative; }
        .role-option input[type="radio"] {
            position: absolute; opacity: 0; width: 0;
        }
        .role-option label {
            display: flex; flex-direction: column; align-items: center; gap: 6px;
            padding: 14px 8px; border: 1.5px solid var(--border); border-radius: 10px;
            background: var(--ivory); cursor: pointer;
            transition: all 0.2s; text-align: center;
            font-size: 0.78rem; font-weight: 400;
            text-transform: none; letter-spacing: 0; color: var(--text-mid);
        }
        .role-option label .icon { font-size: 1.4rem; }
        .role-option input:checked + label {
            border-color: var(--orange);
            background: rgba(212,98,42,0.06);
            color: var(--orange);
        }
        .role-option label:hover { border-color: var(--muted-sage); }

        .terms-row {
            display: flex; align-items: flex-start; gap: 10px;
            margin: 16px 0 20px;
            font-size: 0.83rem; color: var(--text-mid);
        }
        .terms-row input[type="checkbox"] {
            width: 16px; height: 16px; flex-shrink: 0; margin-top: 2px;
            accent-color: #D4622A; cursor: pointer;
        }
        .terms-row a { color: var(--orange); text-decoration: none; }
        .terms-row a:hover { text-decoration: underline; }

        .btn-submit {
            width: 100%; padding: 14px;
            background: var(--orange); color: var(--text-dark);
            border: none; border-radius: 10px;
            font-family: 'Jost', sans-serif; font-size: 0.95rem; font-weight: 500;
            letter-spacing: 0.04em; cursor: pointer; transition: all 0.25s;
        }
        .btn-submit:hover { background: var(--text-dark); transform: translateY(-1px); }

        .divider {
            display: flex; align-items: center; gap: 12px; margin: 20px 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: var(--border);
        }
        .divider span { font-size: 0.78rem; color: var(--text-light); }

        .donate-link-row { text-align: center; }
        .donate-link-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 24px; border: 1.5px solid var(--border); border-radius: 10px;
            font-size: 0.88rem; color: var(--text-mid); text-decoration: none; transition: all 0.2s;
        }
        .donate-link-btn:hover {
            border-color: var(--orange); color: var(--text-dark);
            background: rgba(240,160,80,0.06);
        }
        .back-link {
            text-align: center; margin-top: 20px; font-size: 0.83rem;
        }
        .back-link a { color: var(--text-light); text-decoration: none; }
        .back-link a:hover { color: var(--orange); }

        /* FOOTER */
        footer{background:#4A3B32;padding:60px 6% 28px;grid-column:1 / -1;width:100%}
        .foot-grid{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:48px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,.07);margin-bottom:24px}
        .foot-brand{font-family:'EB Garamond',serif;font-size:1.5rem;color:#fff;margin-bottom:12px;font-weight:600}
        .foot-tag{font-size:.84rem;color:rgba(255,255,255,.38);line-height:1.7;max-width:240px}
        .foot-col h4{font-size:.68rem;font-weight:500;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.28);margin-bottom:14px}
        .foot-col ul{list-style:none}
        .foot-col ul li{margin-bottom:9px}
        .foot-col ul a{color:rgba(255,255,255,.48);text-decoration:none;font-size:.86rem;transition:color .2s}
        .foot-col ul a:hover{color:var(--orange-light)}
        .foot-btm{max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;font-size:.77rem;color:rgba(255,255,255,.22)}

        @media (max-width: 768px) {
            .nav-links { display: none; }
            body { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { padding: 40px 24px; }
            .form-row { grid-template-columns: 1fr; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .form-wrapper { animation: fadeIn 0.5s ease both; }
    </style>
</head>
<body>

<nav>
    <a href="/test/index.php" class="nav-logo">
        <div class="nav-logo-mark">&#9784;</div>
        <div>
            <span class="nav-logo-name">Seela suwa herath</span>
            <span class="nav-logo-sub">Monastery Welfare</span>
        </div>
    </a>
    <ul class="nav-links">
        <li><a href="/test/index.php#mission">Our Mission</a></li>
        <li><a href="/test/index.php#how">How It Works</a></li>
        <li><a href="/test/pages/public/public_transparency.php">Transparency</a></li>
        <li><a href="register.php" class="active">Register</a></li>
        <li><a href="/test/pages/auth/login.php">Sign In</a></li>
        <li><a href="/test/pages/public/public_donate.php" class="nav-donate">Donate Now</a></li>
    </ul>
</nav>

<!-- LEFT PANEL -->
<div class="left-panel">
    <div class="left-panel-bg"></div>
    <div class="left-content">
        <!-- <a href="/test/index.php" class="brand">
            <div class="brand-mark">☸</div>
            <div>
                <span class="brand-name">Seela suwa herath</span>
                <span class="brand-sub">Monastery Welfare</span>
            </div>
        </a> -->
        <h2 class="left-headline">
            Join Our<br>
            <em>Community</em>
        </h2>
        <p class="left-desc">
            Be part of a compassionate network supporting monastery welfare, healthcare, and dignified living for those who serve.
        </p>
        <ul class="benefits-list">
            <li>
                <div class="benefit-check">✓</div>
                <span>Track your donations and their impact in real time</span>
            </li>
            <li>
                <div class="benefit-check">✓</div>
                <span>Receive instant receipts and annual summaries</span>
            </li>
            <li>
                <div class="benefit-check">✓</div>
                <span>Access healthcare scheduling and welfare management</span>
            </li>
            <li>
                <div class="benefit-check">✓</div>
                <span>View transparent, public financial reports anytime</span>
            </li>
        </ul>
    </div>
    <div class="left-footer">© 2026 Seela suwa herath · Sri Lanka</div>
</div>

<!-- RIGHT PANEL -->
<div class="right-panel">
    <div class="form-wrapper">
        <div class="form-header">
            <h1>Create Account</h1>
            <p>Already registered? <a href="/test/pages/auth/login.php">Sign in here</a></p>
        </div>

        <?php if (!empty($error)): ?>
        <div class="error-msg">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/test/pages/auth/register.php">
            <?php if (function_exists('csrfField')): ?>
            <?php csrfField(); ?>
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" placeholder="Perera" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" placeholder="Saman" required>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" required autocomplete="email">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="+94 77 000 0000">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-wrapper">
                    <input type="password" id="password" name="password" placeholder="At least 6 characters" required>
                    <button type="button" class="pw-toggle" onclick="togglePw('password')">👁</button>
                </div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <div class="pw-wrapper">
                    <input type="password" id="password_confirm" name="password_confirm" placeholder="Repeat your password" required>
                    <button type="button" class="pw-toggle" onclick="togglePw('password_confirm')">👁</button>
                </div>
            </div>

            <!-- Role Selection -->
            <div class="form-group role-select-group">
                <label style="font-size:0.78rem; font-weight:500; letter-spacing:0.06em; text-transform:uppercase; color:var(--text-mid);">I am a</label>
                <div class="role-grid">
                    <div class="role-option">
                        <input type="radio" name="role" id="role_donor" value="donor" checked>
                        <label for="role_donor">
                            <span class="icon">🙏</span>
                            Donor
                        </label>
                    </div>
                    <div class="role-option">
                        <input type="radio" name="role" id="role_doctor" value="doctor">
                        <label for="role_doctor">
                            <span class="icon">🏥</span>
                            Doctor
                        </label>
                    </div>
                    <div class="role-option">
                        <input type="radio" name="role" id="role_monk" value="monk">
                        <label for="role_monk">
                            <span class="icon">☸️</span>
                            Monk
                        </label>
                    </div>
                </div>
            </div>

            <div class="terms-row">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn-submit">Create My Account</button>
        </form>

        <div class="divider"><span>or continue as</span></div>

        <div class="donate-link-row">
            <a href="/test/pages/public/public_donate.php" class="donate-link-btn">
                Guest Donor — donate without account
            </a>
        </div>

        <div class="back-link">
            <a href="/test/index.php">← Back to home</a>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="foot-grid">
        <div><div class="foot-brand">☸ Seela suwa herath</div><p class="foot-tag">Supporting monastery welfare through community generosity, transparent governance, and compassionate care.</p></div>
        <div class="foot-col"><h4>Platform</h4><ul><li><a href="/test/pages/public/public_donate.php">Donate</a></li><li><a href="/test/pages/public/public_transparency.php">Transparency</a></li><li><a href="register.php">Register</a></li><li><a href="/test/pages/auth/login.php">Sign In</a></li></ul></div>
        <div class="foot-col"><h4>Welfare</h4><ul><li><a href="#">Healthcare</a></li><li><a href="#">Housing</a></li><li><a href="#">Appointments</a></li><li><a href="#">Reports</a></li></ul></div>
        <div class="foot-col"><h4>Info</h4><ul><li><a href="#">About Us</a></li><li><a href="#">Contact</a></li><li><a href="#">Privacy Policy</a></li></ul></div>
    </div>
    <div class="foot-btm"><span>© 2026 Seela suwa herath Monastery Welfare Platform</span><span>Made with in Sri Lanka</span></div>
</footer>

<script>
function togglePw(id) {
    const pw = document.getElementById(id);
    pw.type = pw.type === 'password' ? 'text' : 'password';
}

(function() {
    const form = document.querySelector('form');
    const f = {
        fn: document.getElementById('first_name'),
        ln: document.getElementById('last_name'),
        em: document.getElementById('email'),
        pw: document.getElementById('password'),
        pc: document.getElementById('password_confirm'),
        tm: document.getElementById('terms')
    };
    function showErr(el, msg) {
        clearErr(el);
        el.style.borderColor = 'var(--error)';
        const d = document.createElement('div');
        d.className = 'field-error';
        d.style.cssText = 'color:var(--error);font-size:0.75rem;margin-top:4px;';
        d.textContent = msg;
        (el.closest('.form-group') || el.parentElement).appendChild(d);
    }
    function clearErr(el) {
        el.style.borderColor = '';
        const p = el.closest('.form-group') || el.parentElement;
        const e = p.querySelector('.field-error');
        if (e) e.remove();
    }
    function ok(el) { clearErr(el); el.style.borderColor = '#059669'; }

    f.fn.addEventListener('blur', function() {
        this.value.trim().length < 2 ? showErr(this, 'At least 2 characters') : ok(this);
    });
    f.ln.addEventListener('blur', function() {
        this.value.trim().length < 2 ? showErr(this, 'At least 2 characters') : ok(this);
    });
    f.em.addEventListener('blur', function() {
        if (this.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value)) showErr(this, 'Enter a valid email');
        else if (this.value) ok(this);
    });
    f.pw.addEventListener('input', function() {
        if (this.value.length > 0 && this.value.length < 6) showErr(this, 'At least 6 characters');
        else if (this.value.length >= 6) ok(this);
        if (f.pc.value) {
            f.pc.value !== this.value ? showErr(f.pc, 'Passwords do not match') : ok(f.pc);
        }
    });
    f.pc.addEventListener('blur', function() {
        if (this.value && this.value !== f.pw.value) showErr(this, 'Passwords do not match');
        else if (this.value) ok(this);
    });

    form.addEventListener('submit', function(e) {
        let v = true;
        if (f.fn.value.trim().length < 2) { showErr(f.fn, 'First name required'); v = false; }
        if (f.ln.value.trim().length < 2) { showErr(f.ln, 'Last name required'); v = false; }
        if (!f.em.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.em.value)) { showErr(f.em, 'Valid email required'); v = false; }
        if (f.pw.value.length < 6) { showErr(f.pw, 'At least 6 characters'); v = false; }
        if (f.pc.value !== f.pw.value) { showErr(f.pc, 'Passwords do not match'); v = false; }
        if (!f.tm.checked) {
            const tr = f.tm.closest('.terms-row');
            if (!tr.querySelector('.field-error')) {
                const d = document.createElement('div');
                d.className = 'field-error';
                d.style.cssText = 'color:var(--error);font-size:0.75rem;margin-top:4px;width:100%;';
                d.textContent = 'You must accept the terms';
                tr.appendChild(d);
            }
            v = false;
        }
        if (!v) e.preventDefault();
    });
})();
</script>
</body>
</html>
