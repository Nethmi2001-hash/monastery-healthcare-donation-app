<?php
session_start();

require_once __DIR__ . '/includes/db_config.php';

$con = getDBConnection();

$error = "";
$success = "";

//check logout success message 
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $success = "You have been logged out successfully.";
}

//check session timeout message 
if (isset($_GET['timeout']) && $_GET['timeout'] == '1') {
    $error = "Your session has expired. Please login again.";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Both email and password are required.";
    } else {
        // prepare and execute query to prevent sql injection 
        $stmt = $con->prepare("SELECT u.user_id, u.name, u.email, u.password_hash, u.role_id, r.role_name 
                               FROM users u 
                               JOIN roles r ON u.role_id = r.role_id 
                               WHERE u.email = ? AND u.status = 'active'
                               LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();

        $user = null;
        if (method_exists($stmt, 'get_result')) {
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $stmt->bind_result($user_id, $name, $email_db, $password_hash, $role_id, $role_name);
            if ($stmt->fetch()) {
                $user = [
                    'user_id' => $user_id,
                    'name' => $name,
                    'email' => $email_db,
                    'password_hash' => $password_hash,
                    'role_id' => $role_id,
                    'role_name' => $role_name
                ];
            }
        }

        if ($user && password_verify($password, $user['password_hash'])) {
            // set session variables
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['last_activity'] = time();
            
            // redirect to dashboard
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid email or password.";
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --healthcare-blue: #0066cc;
            --healthcare-dark: #0052a3;
            --healthcare-light: #e6f2ff;
            --monastery-saffron: #f57c00;
            --monastery-orange: #ff9800;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 50%, #f57c00 100%);
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y="50" font-size="40" opacity="0.05">🤝</text></svg>') repeat;
            animation: float 60s linear infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0); }
            100% { transform: translateY(-100px); }
        }
        
        .login-container {
            display: flex;
            max-width: 1100px;
            width: 95%;
            background: white;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
            position: relative;
            z-index: 1;
            animation: slideIn 0.6s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-image {
            flex: 1;
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 50px;
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-image::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 152, 0, 0.2) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .helping-hands {
            font-size: 6rem;
            margin-bottom: 30px;
            text-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            animation: pulse 2.5s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.08); }
        }
        
        .login-image h1 {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 15px;
            text-shadow: 0 3px 10px rgba(0, 0, 0, 0.3);
            position: relative;
            z-index: 1;
        }
        
        .login-image p {
            font-size: 1.05rem;
            opacity: 0.95;
            line-height: 1.6;
            position: relative;
            z-index: 1;
            margin-bottom: 0;
        }
        
        .login-image .tagline {
            font-size: 0.95rem;
            font-weight: 500;
            opacity: 0.9;
            margin-top: 15px;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            flex: 1;
            padding: 60px 50px;
            position: relative;
            background: #f9f9f9;
            display: flex;
            flex-direction: column;
        }
        
        .login-card::before {
            content: "🏥";
            position: absolute;
            font-size: 120px;
            opacity: 0.04;
            right: -30px;
            top: -30px;
            transform: rotate(20deg);
        }
        
        .login-header {
            margin-bottom: 40px;
        }
        
        .login-header h2 {
            color: #0052a3;
            font-weight: bold;
            margin-bottom: 8px;
            font-size: 1.8rem;
        }
        
        .login-header .subtitle {
            color: #666;
            font-size: 0.95rem;
        }
        
        .form-floating label {
            color: #555;
            font-weight: 500;
        }
        
        .form-floating input:focus ~ label {
            color: #0066cc !important;
        }
        
        .form-control {
            border: 2px solid #ddd;
            padding: 12px 15px;
            height: 55px;
            transition: all 0.3s;
            background: white;
            color: #333;
            font-size: 1rem;
        }
        
        .form-control:focus {
            background: white;
            color: #333;
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
        
        .form-control::placeholder {
            color: #999;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            border: none;
            height: 55px;
            font-size: 1.05rem;
            font-weight: bold;
            color: white;
            border-radius: 8px;
            transition: all 0.3s;
            margin-top: 20px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #0052a3 0%, #003d7a 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
            color: white;
        }
        
        .btn-signup-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 25px;
            padding: 12px;
            border: 2px solid #0066cc;
            border-radius: 8px;
            background: white;
            color: #0066cc;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .btn-signup-link:hover {
            background: #e6f2ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.2);
            color: #0066cc;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .alert-danger {
            background: #fee;
            border-color: #f77;
            color: #c33;
        }
        
        .alert-success {
            background: #efe;
            border-color: #7f7;
            color: #3c3;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: -10px;
            margin-bottom: 20px;
        }
        
        .forgot-password a {
            color: #0066cc;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .forgot-password a:hover {
            color: #0052a3;
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            margin: 20px 0;
            color: #999;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #ddd;
        }
        
        .divider span {
            background: #f9f9f9;
            padding: 0 10px;
            position: relative;
            font-size: 0.85rem;
        }
        
        .info-box {
            background: #e6f2ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: white;
        }
        
        .feature-item i {
            font-size: 1.5rem;
            margin-right: 15px;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 10px;
        }
        
        .donate-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 100;
        }
        
        .donate-btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff5252 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .donate-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.5);
            color: white;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-image {
                padding: 30px;
            }
            
            .login-image .helping-hands {
                font-size: 4rem;
            }
            
            .login-card {
                padding: 40px 30px;
            }
            
            .donate-button {
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
    
    <script>
        function validateForm() {
            var email = document.getElementById("email").value.trim();
            var password = document.getElementById("password").value;

            if (email === "" || password === "") {
                alert("Both email and password are required.");
                return false;
            }
            
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert("Please enter a valid email address.");
                return false;
            }
            
            return true;
        }
    </script>
</head>
<body>
    <div class="login-container">
        <!-- LEFT SIDE - Image & Info -->
        <div class="login-image">
            <div class="helping-hands">🤝</div>
            <h1>Seela Suwa Herath<br>Bikshu Gilan Arana</h1>
            <p>Healthcare & Donation Management System</p>
            <div class="tagline">Serving the Monastic Community with Compassion</div>
            
            <div class="features-list" style="margin-top: 30px;">
                <div class="feature-item">
                    <i class="bi bi-heart-pulse"></i>
                    <div>
                        <strong>Healthcare Support</strong><br>
                        <small>Medical care for monks</small>
                    </div>
                </div>
                <div class="feature-item">
                    <i class="bi bi-hand-thumbs-up"></i>
                    <div>
                        <strong>Easy Donations</strong><br>
                        <small>Secure & transparent giving</small>
                    </div>
                </div>
                <div class="feature-item">
                    <i class="bi bi-globe"></i>
                    <div>
                        <strong>Bilingual Support</strong><br>
                        <small>සිංහල & English</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- RIGHT SIDE - Login Form -->
        <div class="login-card">
            <div class="login-header">
                <h2>Welcome Back</h2>
                <p class="subtitle">Login to access the monastery system</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Error!</strong> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <strong>Success!</strong> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" onsubmit="return validateForm();">
                <div class="form-floating mb-4">
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="admin@monastery.lk" required autofocus>
                    <label for="email"><i class="bi bi-envelope"></i> Email Address</label>
                </div>
                
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock"></i> Password</label>
                </div>
                
                <div class="forgot-password">
                    <a href="javascript:void(0);" title="Feature coming soon">Forgot password?</a>
                </div>
                
                <button type="submit" class="btn btn-login w-100">
                    <i class="bi bi-box-arrow-in-right"></i> SIGN IN
                </button>
                
                <div class="divider"><span>New to the system?</span></div>
                
                <a href="register.php" class="btn-signup-link">
                    <i class="bi bi-person-plus"></i> CREATE NEW ACCOUNT
                </a>
                
                <div class="info-box text-center">
                    <small><i class="bi bi-info-circle"></i> <strong>Demo Credentials</strong></small><br>
                    <small class="text-muted">
                        Email: <code>admin@monastery.lk</code><br>
                        Password: <code>admin123</code>
                    </small>
                </div>
            </form>
        </div>
    </div>
    
    <!-- DONATION CALL-TO-ACTION BUTTON -->
    <div class="donate-button">
        <a href="public_donate.php" class="donate-btn">
            <i class="bi bi-heart-fill"></i> Donate Now
        </a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $con->close(); ?>
