<?php
session_start();
require_once __DIR__ . '/includes/db_config.php';

$con = getDBConnection();

$success = "";
$error = "";

// Fetch roles for dropdown
$roles_res = $con->query("SELECT role_id, role_name FROM roles WHERE role_name IN ('Donor', 'Monk')");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone_prefix = $_POST['phone_number_prefix'] ?? '';
    $phone_number_part = trim($_POST['phone_number'] ?? '');
    $phone = ($phone_prefix && $phone_number_part) ? $phone_prefix . $phone_number_part : '';
    $role_id = (int)($_POST['role_id'] ?? 3); // Default to Donor
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$name) {
        $error = "Full name is required.";
    } elseif (!$email) {
        $error = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!$phone_prefix || !$phone_number_part) {
        $error = "Please select a phone prefix and enter the rest of your number.";
    } elseif (!preg_match('/^0(71|72|74|75|76|77|78)[0-9]{7}$/', $phone)) {
        $error = "Invalid Sri Lankan phone number format.";
    } elseif (!$password || !$confirm_password) {
        $error = "Password and Confirm Password are required.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt_check = $con->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = "Email already registered. Please <a href='login.php'>login</a> instead.";
        } else {
            // Insert new user
            $password_hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $con->prepare("INSERT INTO users (name, email, phone, password_hash, role_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("ssssi", $name, $email, $phone, $password_hash, $role_id);

            if ($stmt->execute()) {
                $success = "Registration successful! Redirecting to login...";
                header("refresh:2;url=login.php");
            } else {
                $error = "Registration failed: " . $con->error;
            }
            $stmt->close();
        }
        $stmt_check->close();
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
            --healthcare-blue: #0066cc;
            --healthcare-dark: #0052a3;
            --healthcare-light: #e6f2ff;
            --monastery-saffron: #f57c00;
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
            padding: 20px;
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
        
        .register-container {
            max-width: 700px;
            width: 100%;
            background: white;
            border-radius: 20px;
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
        
        .register-header {
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .register-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255, 152, 0, 0.2) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .register-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .register-header .helping-hands {
            font-size: 3.5rem;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .register-header p {
            font-size: 1.05rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
            margin: 0;
        }
        
        .register-body {
            padding: 40px;
        }
        
        .form-label {
            color: #555;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .form-control, .form-select {
            border: 2px solid #ddd;
            padding: 12px 15px;
            height: 50px;
            transition: all 0.3s;
            background: white;
            color: #333;
            font-size: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
            background: white;
            color: #333;
        }
        
        .form-control::placeholder {
            color: #999;
        }
        
        .input-group .form-select {
            max-width: 150px;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #0066cc 0%, #0052a3 100%);
            border: none;
            height: 50px;
            font-size: 1.05rem;
            font-weight: bold;
            color: white;
            border-radius: 8px;
            transition: all 0.3s;
            margin-top: 10px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }
        
        .btn-register:hover {
            background: linear-gradient(135deg, #0052a3 0%, #003d7a 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
            color: white;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid;
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 20px;
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
        
        .text-center-custom {
            text-align: center;
            margin-top: 20px;
            color: #666;
        }
        
        .text-center-custom a {
            color: #0066cc;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .text-center-custom a:hover {
            color: #0052a3;
            text-decoration: underline;
        }
        
        .form-text {
            color: #888;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        @media (max-width: 600px) {
            .register-container {
                border-radius: 15px;
            }
            
            .register-header {
                padding: 30px 20px;
            }
            
            .register-header h1 {
                font-size: 1.8rem;
            }
            
            .register-header .helping-hands {
                font-size: 2.5rem;
            }
            
            .register-body {
                padding: 25px;
            }
            
            .input-group .form-select {
                max-width: 120px;
            }
        }
    </style>
    
    <script>
        function validateForm() {
            var password = document.getElementById("password").value;
            var confirmPassword = document.getElementById("confirm_password").value;
            
            if (password !== confirmPassword) {
                alert("Passwords do not match!");
                return false;
            }
            
            if (password.length < 6) {
                alert("Password must be at least 6 characters!");
                return false;
            }
            
            return true;
        }
    </script>
</head>
<body>
    <div class="register-container">
        <!-- HEADER -->
        <div class="register-header">
            <div class="helping-hands">🤝</div>
            <h1>Create Your Account</h1>
            <p>Join Seela Suwa Herath Monastery Community</p>
        </div>
        
        <!-- BODY -->
        <div class="register-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Error!</strong> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <strong>Success!</strong> <?= $success ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form action="register.php" method="post" onsubmit="return validateForm();">
                <!-- Full Name -->
                <div class="mb-4">
                    <label for="full_name" class="form-label">
                        <i class="bi bi-person"></i> Full Name
                    </label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           placeholder="Enter your full name" required autofocus>
                </div>
                
                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope"></i> Email Address
                    </label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="your@email.com" required>
                </div>
                
                <!-- Phone Number -->
                <div class="mb-4">
                    <label for="phone_number_prefix" class="form-label">
                        <i class="bi bi-telephone"></i> Phone Number
                    </label>
                    <div class="input-group">
                        <select name="phone_number_prefix" id="phone_number_prefix" class="form-select" required>
                            <option value="">Prefix</option>
                            <option value="071">Dialog (071)</option>
                            <option value="077">Dialog (077)</option>
                            <option value="078">Dialog (078)</option>
                            <option value="072">Mobitel (072)</option>
                            <option value="076">Hutch (076)</option>
                            <option value="075">Airtel (075)</option>
                            <option value="074">Hutch (074)</option>
                        </select>
                        <input type="tel" id="phone_number" name="phone_number" class="form-control" 
                               placeholder="1234567" pattern="[0-9]{7}" maxlength="7" required>
                    </div>
                    <small class="form-text">Enter 7 digits after the prefix</small>
                </div>
                
                <!-- Role Selection -->
                <div class="mb-4">
                    <label for="role_id" class="form-label">
                        <i class="bi bi-person-badge"></i> Join As
                    </label>
                    <select name="role_id" id="role_id" class="form-select" required>
                        <option value="">Select your role</option>
                        <?php 
                        $roles_res->data_seek(0); // Reset pointer
                        while ($role = $roles_res->fetch_assoc()): 
                        ?>
                            <option value="<?= $role['role_id'] ?>" <?= $role['role_name'] == 'Donor' ? 'selected' : '' ?>>
                                <?= htmlspecialchars($role['role_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <small class="form-text">Choose how you'll participate in the community</small>
                </div>
                
                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock"></i> Password
                    </label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Minimum 6 characters" required>
                    <small class="form-text">Use a strong password with letters and numbers</small>
                </div>
                
                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">
                        <i class="bi bi-lock-fill"></i> Confirm Password
                    </label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                           placeholder="Re-enter your password" required>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="btn btn-register w-100">
                    <i class="bi bi-person-plus"></i> Create Account
                </button>
            </form>
            
            <!-- Login Link -->
            <div class="text-center-custom">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $con->close(); ?>
