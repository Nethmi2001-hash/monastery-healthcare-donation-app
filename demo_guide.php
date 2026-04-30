<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Demo - Seela Suwa Herath</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --monastery-saffron: #f57c00;
            --monastery-orange: #ff9800;
        }
        body {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            padding: 50px 0;
        }
        .demo-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .demo-header {
            background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .credential-box {
            background: #f8f9fa;
            border-left: 4px solid var(--monastery-saffron);
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .credential-box h5 {
            color: var(--monastery-saffron);
            margin-bottom: 15px;
        }
        .credential-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .credential-item:last-child {
            border-bottom: none;
        }
        .credential-label {
            font-weight: 600;
            color: #666;
        }
        .credential-value {
            font-family: 'Courier New', monospace;
            background: white;
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .feature-list li:before {
            content: "✅ ";
            margin-right: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%);
            border: none;
        }
        .demo-flow {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 2px solid var(--monastery-orange);
        }
        .step-number {
            background: var(--monastery-saffron);
            color: white;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="demo-card">
        <div class="demo-header">
            <h1>🪷 System Demo Guide</h1>
            <p class="mb-0">Seela Suwa Herath Bikshu Gilan Arana</p>
            <p class="mb-0 small">Healthcare & Donation Management System</p>
        </div>

        <div class="p-4">
            <!-- Quick Access Links -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <a href="public_donate.php" class="btn btn-primary w-100 p-3">
                        <i class="bi bi-heart-fill"></i> Public Donation Portal
                    </a>
                </div>
                <div class="col-md-6 mb-3">
                    <a href="login.php" class="btn btn-outline-primary w-100 p-3">
                        <i class="bi bi-box-arrow-in-right"></i> Staff Login
                    </a>
                </div>
            </div>

            <!-- Demo Credentials -->
            <div class="credential-box">
                <h5><i class="bi bi-key"></i> Demo Login Credentials</h5>
                
                <div class="credential-item">
                    <span class="credential-label">Admin Account:</span>
                    <div>
                        <div class="credential-value mb-2">admin@monastery.lk</div>
                        <div class="credential-value">admin123</div>
                    </div>
                </div>
            </div>

            <!-- PayHere Test Cards -->
            <div class="credential-box">
                <h5><i class="bi bi-credit-card"></i> PayHere Test Cards (Sandbox)</h5>
                
                <div class="credential-item">
                    <span class="credential-label">Visa Card:</span>
                    <div class="credential-value">4111 1111 1111 1111</div>
                </div>
                
                <div class="credential-item">
                    <span class="credential-label">MasterCard:</span>
                    <div class="credential-value">5555 5555 5555 4444</div>
                </div>
                
                <div class="credential-item">
                    <span class="credential-label">CVV:</span>
                    <div class="credential-value">Any 3 digits</div>
                </div>
                
                <div class="credential-item">
                    <span class="credential-label">Expiry:</span>
                    <div class="credential-value">Any future date</div>
                </div>
            </div>

            <!-- Demo Flow -->
            <div class="demo-flow">
                <h5 style="color: var(--monastery-saffron); margin-bottom: 20px;">
                    <i class="bi bi-diagram-3"></i> Recommended Demo Flow
                </h5>
                
                <div class="mb-3">
                    <span class="step-number">1</span>
                    <strong>Public Donation Portal</strong>
                    <p class="ms-5 mb-0 text-muted">Show the public-facing donation page → Submit donation → PayHere payment</p>
                </div>
                
                <div class="mb-3">
                    <span class="step-number">2</span>
                    <strong>Admin Login</strong>
                    <p class="ms-5 mb-0 text-muted">Login as admin → View dashboard with real-time stats</p>
                </div>
                
                <div class="mb-3">
                    <span class="step-number">3</span>
                    <strong>Donation Management</strong>
                    <p class="ms-5 mb-0 text-muted">Verify the donation → Generate PDF receipt → Send email notification</p>
                </div>
                
                <div class="mb-3">
                    <span class="step-number">4</span>
                    <strong>Financial Reports</strong>
                    <p class="ms-5 mb-0 text-muted">View analytics charts → Export to CSV → Print report</p>
                </div>
                
                <div class="mb-3">
                    <span class="step-number">5</span>
                    <strong>AI Chatbot</strong>
                    <p class="ms-5 mb-0 text-muted">Demonstrate bilingual support (English + සිංහල)</p>
                </div>
                
                <div class="mb-3">
                    <span class="step-number">6</span>
                    <strong>Healthcare Module</strong>
                    <p class="ms-5 mb-0 text-muted">Show monk management → Doctor appointments → Medical records</p>
                </div>
            </div>

            <!-- Key Features -->
            <div class="row">
                <div class="col-md-6">
                    <h5 style="color: var(--monastery-saffron);">
                        <i class="bi bi-star-fill"></i> Key Features
                    </h5>
                    <ul class="feature-list">
                        <li>PayHere payment integration (sandbox)</li>
                        <li>Automated PDF receipt generation (FPDF)</li>
                        <li>Email notifications (PHPMailer)</li>
                        <li>Bilingual AI chatbot (OpenAI API)</li>
                        <li>Role-based access control</li>
                        <li>Financial reports with charts</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h5 style="color: var(--monastery-saffron);">
                        <i class="bi bi-sparkles"></i> Advanced Features
                    </h5>
                    <ul class="feature-list">
                        <li>🔐 QR code verification (QR Scanner)</li>
                        <li>🇱🇰 Bilingual UI (English/Sinhala)</li>
                        <li>📊 Public financial transparency dashboard</li>
                        <li>🔔 Real-time browser notifications</li>
                        <li>🔍 Advanced search with dynamic filters</li>
                        <li>📱 Fully responsive web design</li>
                    </ul>
                </div>
            </div>

            <!-- Key Features Row 2 -->
            <div class="row mt-3">
                <div class="col-md-6">
                    <h5 style="color: var(--monastery-saffron);">
                        <i class="bi bi-shield-check"></i> Security Features
                    </h5>
                    <ul class="feature-list">
                        <li>Bcrypt password hashing</li>
                        <li>CSRF protection on all forms</li>
                        <li>Prepared SQL statements</li>
                        <li>Session timeout management</li>
                        <li>Input validation & sanitization</li>
                        <li>Audit trail logging</li>
                    </ul>
                </div>
            </div>

            <!-- Technologies Used -->
            <div class="alert alert-info mt-4">
                <h6><i class="bi bi-code-square"></i> Technologies Used</h6>
                <p class="mb-0">
                    <strong>Backend:</strong> PHP 8, MySQL<br>
                    <strong>Frontend:</strong> Bootstrap 5, Chart.js, JavaScript, Web APIs (Notifications, Camera)<br>
                    <strong>Integrations:</strong> PayHere, OpenAI, PHPMailer, FPDF<br>
                    <strong>Architecture:</strong> MVC-inspired, prepared statements, session management, REST APIs
                </p>
            </div>

            <!-- Talking Points -->
            <div class="demo-flow mt-4">
                <h5 style="color: var(--monastery-saffron); margin-bottom: 20px;">
                    <i class="bi bi-megaphone"></i> Presentation Talking Points (10 Differentiators)
                </h5>
                
                <div class="alert alert-warning mb-3">
                    <strong>1️⃣ Bilingual OpenAI Integration:</strong> "We integrated OpenAI API for bilingual chatbot support in Sinhala and English, making it accessible to local monks and donors."
                </div>

                <div class="alert alert-success mb-3">
                    <strong>2️⃣ Real-World Payment Gateway:</strong> "PayHere sandbox integration - switch to production with one config change. Full webhook support for payment notifications."
                </div>

                <div class="alert alert-primary mb-3">
                    <strong>3️⃣ QR Code Verification System:</strong> "Every donation generates a unique QR code for verification. Users can scan via phone camera - no app needed, pure web technology."
                </div>

                <div class="alert alert-info mb-3">
                    <strong>4️⃣ Real-Time Browser Notifications:</strong> "Desktop notifications alert users of new donations/appointments. Auto-polling every 30s, fallback to toast notifications."
                </div>

                <div class="alert alert-warning mb-3">
                    <strong>5️⃣ Advanced Search with Filters:</strong> "Complex database queries with 5+ filters per entity. Real-time search, CSV export, dynamic UI generation."
                </div>

                <div class="alert alert-success mb-3">
                    <strong>6️⃣ Public Transparency Dashboard:</strong> "Non-logged-in users see real-time donation/expense analytics. Proves accountability to donors and community."
                </div>

                <div class="alert alert-primary mb-3">
                    <strong>7️⃣ Sinhala Language Switcher:</strong> "Complete UI translation from English to Sinhala. Session-based persistence across all pages."
                </div>

                <div class="alert alert-info mb-3">
                    <strong>8️⃣ PDF Receipt Generation:</strong> "FPDF library generates receipts with embedded QR codes, donation details, tax information."
                </div>

                <div class="alert alert-warning mb-3">
                    <strong>9️⃣ Financial Analytics:</strong> "Chart.js visualizations: pie charts (donations by category), line graphs (monthly trends), doughnut charts (expense breakdown)."
                </div>

                <div class="alert alert-success mb-0">
                    <strong>🔟 Industry-Standard Security:</strong> "Bcrypt hashing, CSRF tokens, prepared statements, role-based access, audit trails."
                </div>
            </div>

            <!-- Demo Flow -->
            <div class="demo-flow mt-4">
                <h5 style="color: var(--monastery-saffron); margin-bottom: 20px;">
                    <i class="bi bi-play-circle"></i> Recommended Demo Flow
                </h5>
                
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <span class="step-number">1</span>
                    <div>
                        <strong>Start with public_donate.php</strong>
                        <p class="mb-0 text-muted small">Show QR code generation, donation categories, PayHere modal</p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <span class="step-number">2</span>
                    <div>
                        <strong>Switch language to Sinhala</strong>
                        <p class="mb-0 text-muted small">Top navbar dropdown - shows full UI translation</p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <span class="step-number">3</span>
                    <div>
                        <strong>Check public_transparency.php</strong>
                        <p class="mb-0 text-muted small">No login needed - real-time charts, recent activities</p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <span class="step-number">4</span>
                    <div>
                        <strong>Login to dashboard</strong>
                        <p class="mb-0 text-muted small">Accept browser notification permissions (desktop alerts)</p>
                    </div>
                </div>

                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <span class="step-number">5</span>
                    <div>
                        <strong>Show Advanced Search</strong>
                        <p class="mb-0 text-muted small">monk_management.php → search monks by blood group, chronic conditions, etc. + CSV export</p>
                    </div>
                </div>

                <div style="display: flex; align-items: center;">
                    <span class="step-number">6</span>
                    <div>
                        <strong>Verify a donation with QR</strong>
                        <p class="mb-0 text-muted small">verify_donation.php → scan QR code with phone camera (works in browser)</p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="public_donate.php" class="btn btn-primary btn-lg me-2">
                    <i class="bi bi-play-fill"></i> Start Demo
                </a>
                <a href="login.php" class="btn btn-outline-primary btn-lg">
                    <i class="bi bi-box-arrow-in-right"></i> Admin Login
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
