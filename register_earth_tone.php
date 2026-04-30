<?php
session_start();
require_once __DIR__ . '/includes/db_config.php';

$error = "";
$success = "";
$con = getDBConnection();

// Get all roles
$roles = [];
$result = $con->query("SELECT role_id, role_name FROM roles");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $role_id = $_POST['role_id'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($name) || empty($email) || empty($phone) || empty($role_id) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^(071|072|073|074|075|076|077|078|070)\d{7}$/', $phone)) {
        $error = "Please enter a valid Sri Lankan phone number (e.g., 0712345678).";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $password_confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $con->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            $error = "This email is already registered.";
        } else {
            // Hash password and insert user
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $con->prepare("INSERT INTO users (name, email, phone, role_id, password_hash, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssss", $name, $email, $phone, $role_id, $password_hash);
            
            if ($stmt->execute()) {
                $success = "Registration successful! Redirecting to login...";
                header("refresh:2;url=login.php");
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --monastery-green: #2d5016;
            --monastery-dark-green: #1a3009;
            --monastery-gold: #D4AF37;
            --monastery-light-gold: #F5DEB3;
            --monastery-cream: #F5F1E8;
            --text-dark: #333;
            --text-light: #666;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--monastery-green) 0%, var(--monastery-dark-green) 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-attachment: fixed;
            padding: 40px 20px;
        }
        
        .container-register {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.3);
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
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .monastery-emoji {
            font-size: 50px;
            display: inline-block;
            animation: pulse 2.5s ease-in-out infinite;
            margin-bottom: 15px;
        }
        
        .register-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--monastery-green);
            margin-bottom: 10px;
        }
        
        .register-subtitle {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: block;
            font-size: 14px;
        }
        
        .form-control {
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            color: var(--text-dark);
            width: 100%;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--monastery-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }
        
        select.form-control {
            cursor: pointer;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .btn-register {
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--monastery-green) 0%, #1a3009 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(45, 80, 22, 0.3);
        }
        
        .btn-register:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            animation: slideIn 0.3s ease-out;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .login-link-text {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .btn-login {
            color: var(--monastery-green);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            color: var(--monastery-gold);
        }
        
        .password-hint {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        @media (max-width: 500px) {
            .container-register {
                padding: 25px;
            }
            
            .register-title {
                font-size: 24px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-register">
        <div class="register-header">
            <div class="monastery-emoji">🤝</div>
            <h1 class="register-title">Create Account</h1>
            <p class="register-subtitle">Join our monastery healthcare community</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="name">
                    <i class="bi bi-person"></i> Full Name
                </label>
                <input 
                    type="text" 
                    id="name" 
                    name="name" 
                    class="form-control" 
                    placeholder="Enter your full name"
                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label class="form-label" for="email">
                    <i class="bi bi-envelope"></i> Email Address
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    placeholder="Enter your email"
                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label class="form-label" for="phone">
                    <i class="bi bi-telephone"></i> Phone Number
                </label>
                <input 
                    type="tel" 
                    id="phone" 
                    name="phone" 
                    class="form-control" 
                    placeholder="0712345678"
                    value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                    required
                >
                <small class="password-hint">Sri Lankan format: 10 digits (071-078 prefix)</small>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="role_id">
                    <i class="bi bi-person-badge"></i> Role
                </label>
                <select id="role_id" name="role_id" class="form-control" required>
                    <option value="">Select your role...</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['role_id']; ?>">
                            <?php echo htmlspecialchars($role['role_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password">
                    <i class="bi bi-lock"></i> Password
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Enter your password"
                    required
                >
                <small class="password-hint">At least 6 characters</small>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="password_confirm">
                    <i class="bi bi-lock-check"></i> Confirm Password
                </label>
                <input 
                    type="password" 
                    id="password_confirm" 
                    name="password_confirm" 
                    class="form-control" 
                    placeholder="Confirm your password"
                    required
                >
            </div>
            
            <button type="submit" class="btn-register">
                <i class="bi bi-person-plus"></i> Create Account
            </button>
        </form>
        
        <div class="login-link">
            <p class="login-link-text">Already have an account?</p>
            <a href="login.php" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i> Login here
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>