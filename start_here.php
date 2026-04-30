<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Start - Monastery Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/modern-design.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-500: #f97316;
            --green-600: #ea580c;
            --green-700: #c2410c;
            --green-50: #fff7ed;
            --green-100: #ffedd5;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--slate-50, #f8fafc);
            min-height: 100vh;
        }
        .landing-navbar {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--slate-200, #e2e8f0);
            padding: 0 0;
        }
        .landing-navbar .navbar-brand {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--green-600);
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--green-500), var(--green-700));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 18px;
        }
        .hero-section {
            padding: 64px 0 40px;
            text-align: center;
        }
        .hero-section h1 {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            letter-spacing: -1px;
            color: var(--slate-900, #0f172a);
            margin-bottom: 12px;
        }
        .hero-section .subtitle {
            font-size: 1.1rem;
            color: var(--slate-500, #64748b);
            margin-bottom: 24px;
        }
        .badge-status {
            font-weight: 600;
            font-size: 0.82rem;
            padding: 6px 16px;
            border-radius: 9999px;
        }
        .action-card {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: none;
            border-radius: var(--border-radius, 12px);
            padding: 28px 24px;
            color: #fff;
            text-decoration: none;
            transition: transform 0.22s cubic-bezier(.4,0,.2,1), box-shadow 0.22s cubic-bezier(.4,0,.2,1);
            height: 100%;
            overflow: hidden;
        }
        .action-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.08) 0%, rgba(0,0,0,0.06) 100%);
            pointer-events: none;
        }
        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1));
            color: #fff;
            text-decoration: none;
        }
        .action-card .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 18px;
        }
        .action-card h3 {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            font-weight: 700;
            font-size: 1.15rem;
            margin-bottom: 6px;
        }
        .action-card p {
            opacity: 0.88;
            font-size: 0.9rem;
            margin-bottom: 0;
            line-height: 1.5;
        }
        .action-card .step-pill {
            display: inline-block;
            background: rgba(255,255,255,0.22);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 3px 10px;
            border-radius: 9999px;
            margin-bottom: 12px;
        }
        .bg-green  { background: linear-gradient(135deg, var(--green-500), var(--green-700)); }
        .bg-emerald { background: linear-gradient(135deg, #f97316, #c2410c); }
        .bg-violet { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
        .bg-amber  { background: linear-gradient(135deg, #d97706, #b45309); }
        .bg-cyan   { background: linear-gradient(135deg, #0891b2, #0e7490); }

        .section-label {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--slate-400, #94a3b8);
            margin-bottom: 16px;
        }
        .quick-link-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 10px;
        }
        .quick-link-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #fff;
            border: 1px solid var(--slate-200, #e2e8f0);
            border-radius: var(--border-radius-sm, 8px);
            color: var(--slate-700, #334155);
            font-weight: 500;
            font-size: 0.88rem;
            text-decoration: none;
            transition: all 0.18s ease;
        }
        .quick-link-item:hover {
            background: var(--green-50);
            border-color: var(--green-500);
            color: var(--green-700);
            box-shadow: var(--shadow-xs, 0 1px 2px rgba(0,0,0,0.05));
        }
        .quick-link-item i {
            font-size: 1.1rem;
            color: var(--green-500);
            width: 20px;
            text-align: center;
        }
        .doc-card {
            background: linear-gradient(135deg, #fffbeb, #fef3c7);
            border: 1px solid #fde68a;
            border-radius: var(--border-radius, 12px);
            padding: 24px;
        }
        .doc-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #fff;
            border: 1px solid #fde68a;
            border-radius: var(--border-radius-sm, 8px);
            color: #92400e;
            font-weight: 500;
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.18s ease;
        }
        .doc-link:hover {
            background: #fef3c7;
            border-color: #fbbf24;
            color: #78350f;
        }
        .checklist-card {
            background: #fff;
            border: 1px solid var(--slate-200, #e2e8f0);
            border-radius: var(--border-radius, 12px);
            padding: 24px 28px;
        }
        .checklist-card h5 {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            color: var(--slate-800, #1e293b);
        }
        .checklist-card ol li {
            padding: 4px 0;
            color: var(--slate-600, #475569);
            font-size: 0.92rem;
        }
        .cta-banner {
            background: linear-gradient(135deg, var(--green-500), var(--green-700));
            border-radius: var(--border-radius, 12px);
            padding: 28px 32px;
            color: #fff;
            text-align: center;
        }
        .cta-banner h4 {
            font-family: 'Plus Jakarta Sans', 'Inter', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            margin-bottom: 8px;
        }
        .cta-banner p {
            margin-bottom: 0;
            opacity: 0.9;
            font-size: 0.92rem;
        }
        .landing-footer {
            text-align: center;
            padding: 24px 0 32px;
            color: var(--slate-400, #94a3b8);
            font-size: 0.85rem;
        }
        @media (max-width: 768px) {
            .hero-section h1 { font-size: 1.8rem; }
            .hero-section { padding: 40px 0 24px; }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar landing-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand" href="start_here.php">
            <span class="brand-icon"><i class="bi bi-heart-pulse"></i></span>
            Monastery Healthcare
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="login.php" class="btn btn-sm" style="border:1px solid var(--green-500); color:var(--green-600); font-weight:600; border-radius:8px; padding:6px 18px;">Log In</a>
            <a href="register.php" class="btn btn-sm text-white" style="background:var(--green-500); font-weight:600; border-radius:8px; padding:6px 18px;">Register</a>
        </div>
    </div>
</nav>

<div class="container" style="max-width: 1080px;">

    <!-- Hero -->
    <div class="hero-section">
        <h1>Monastery Healthcare System</h1>
        <p class="subtitle">Giribawa Seela Suva Herath Bhikkhu Hospital</p>
        <div class="d-flex justify-content-center gap-2 flex-wrap">
            <span class="badge-status text-white" style="background:#f97316;">
                <i class="bi bi-check-circle-fill me-1"></i>90% Complete
            </span>
            <span class="badge-status text-white" style="background:#0891b2;">
                <i class="bi bi-rocket-takeoff-fill me-1"></i>Ready to Test
            </span>
        </div>
    </div>

    <!-- Step 1: Primary CTA -->
    <div class="row g-3 mb-3">
        <div class="col-12">
            <a href="generate_sample_data.php" class="action-card bg-emerald">
                <div>
                    <span class="step-pill">Step 1 — Start Here</span>
                    <div class="card-icon"><i class="bi bi-database-fill-gear"></i></div>
                    <h3>Generate Sample Data</h3>
                    <p>Create 50+ sample donations, expenses, and appointments for testing reports</p>
                    <p style="margin-top: 10px;"><small><i class="bi bi-clock me-1"></i>Takes 5 seconds &bull; Run this FIRST</small></p>
                </div>
            </a>
        </div>
    </div>

    <!-- Steps 2 & 3 -->
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <a href="reports.php" class="action-card bg-green">
                <div>
                    <span class="step-pill">Step 2</span>
                    <div class="card-icon"><i class="bi bi-bar-chart-line-fill"></i></div>
                    <h3>View Reports &amp; Charts</h3>
                    <p>Financial analytics, appointment stats, donor rankings with beautiful charts</p>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="dashboard.php" class="action-card bg-violet">
                <div>
                    <span class="step-pill">Step 3</span>
                    <div class="card-icon"><i class="bi bi-speedometer2"></i></div>
                    <h3>Dashboard</h3>
                    <p>Overview with live statistics, charts, and today's appointments</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Extra Features -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <a href="donation_management.php" class="action-card bg-amber">
                <div>
                    <div class="card-icon"><i class="bi bi-credit-card-2-front-fill"></i></div>
                    <h3>Test Online Payment</h3>
                    <p>Try PayHere sandbox with test cards (no real money)</p>
                </div>
            </a>
        </div>
        <div class="col-md-6">
            <a href="chatbot.php" class="action-card bg-cyan">
                <div>
                    <div class="card-icon"><i class="bi bi-robot"></i></div>
                    <h3>AI Chatbot</h3>
                    <p>Test bilingual assistant (English &amp; Sinhala)</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Quick Access -->
    <div class="mb-4">
        <div class="section-label"><i class="bi bi-grid-3x3-gap-fill me-1"></i> Quick Access</div>
        <div class="quick-link-grid">
            <a href="login.php" class="quick-link-item"><i class="bi bi-box-arrow-in-right"></i> Login</a>
            <a href="donation_management.php" class="quick-link-item"><i class="bi bi-cash-coin"></i> Donations</a>
            <a href="bill_management.php" class="quick-link-item"><i class="bi bi-receipt"></i> Expenses</a>
            <a href="patient_appointments.php" class="quick-link-item"><i class="bi bi-calendar-check"></i> Appointments</a>
            <a href="monk_management.php" class="quick-link-item"><i class="bi bi-person-hearts"></i> Monks</a>
            <a href="doctor_management.php" class="quick-link-item"><i class="bi bi-person-badge"></i> Doctors</a>
            <a href="table.php" class="quick-link-item"><i class="bi bi-people"></i> Users</a>
        </div>
    </div>

    <!-- Documentation -->
    <div class="doc-card mb-4">
        <div class="section-label" style="color:#92400e;"><i class="bi bi-book-half me-1"></i> Documentation &amp; Guides</div>
        <div class="d-flex flex-wrap gap-2">
            <a href="COMPLETE_TESTING_GUIDE.md" target="_blank" class="doc-link"><i class="bi bi-flag-fill"></i> Complete Testing Guide</a>
            <a href="PAYHERE_SETUP_GUIDE.md" target="_blank" class="doc-link"><i class="bi bi-credit-card"></i> PayHere Setup</a>
            <a href="LATEST_UPDATES.md" target="_blank" class="doc-link"><i class="bi bi-newspaper"></i> Latest Updates</a>
            <a href="PROJECT_STATUS.md" target="_blank" class="doc-link"><i class="bi bi-clipboard-check"></i> Project Status</a>
        </div>
    </div>

    <!-- Checklist -->
    <div class="checklist-card mb-4">
        <h5><i class="bi bi-list-check me-2" style="color:var(--green-500);"></i>Testing Checklist (15 Minutes)</h5>
        <ol style="margin-bottom: 0; padding-left: 20px; margin-top: 12px;">
            <li>Click "Generate Sample Data" above (5 seconds)</li>
            <li>Go to "View Reports" and see all 3 report types</li>
            <li>Try CSV Export and Print Report buttons</li>
            <li>Test PayHere payment with card: 4111 1111 1111 1111</li>
            <li>Chat with AI bot in English and Sinhala</li>
        </ol>
    </div>

    <!-- CTA Banner -->
    <div class="cta-banner mb-3">
        <h4><i class="bi bi-stars me-1"></i> All Features Ready!</h4>
        <p>90% Complete &bull; Reports Module &bull; PayHere Integration &bull; AI Chatbot</p>
        <p style="opacity:0.75; margin-top:6px; font-size:0.85rem;">Default Login: admin@monastery.lk / admin123</p>
    </div>

    <!-- Footer -->
    <div class="landing-footer">
        Theruwan Saranai! May this helping-hand service bless all beings. 🙏
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
