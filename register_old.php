<?php
require_once __DIR__ . '/includes/db_config.php';

$con = getDBConnection();

$success = "";
$error = "";

// Fetch roles for dropdown (monastery_healthcare schema)
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
                $success = "Registration successful! <a href='login.php' class='alert-link'>Login here</a>";
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
            --monastery-saffron: #f57c00;
            --monastery-orange: #ff9800;
            --monastery-pale: #fff3e0;
        }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(245, 124, 0, 0.1) 0%, rgba(255, 152, 0, 0.1) 100%);
        }
        .register-card {
            max-width: 600px;
            margin: 50px auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border-radius: 15px;
        }
        .card-header {
            background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 30px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 124, 0, 0.3);
        }
        .form-label {
            font-weight: 500;
            color: #555;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--monastery-saffron);
            box-shadow: 0 0 0 0.2rem rgba(245, 124, 0, 0.15);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card register-card">
            <div class="card-header text-center">
                <h2 class="mb-2">🪷 Create Account</h2>
                <p class="mb-0">Join Seela Suwa Herath Monastery</p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= $error ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= $success ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="post">
                    <div class="mb-3">
                        <label for="full_name" class="form-label"><i class="bi bi-person"></i> Full Name</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" 
                               placeholder="Enter your full name" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label"><i class="bi bi-envelope"></i> Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               placeholder="your@email.com" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone_number_prefix" class="form-label"><i class="bi bi-telephone"></i> Phone Number</label>
                        <div class="input-group">
                            <select name="phone_number_prefix" id="phone_number_prefix" class="form-select" style="max-width: 150px;" required>
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
                        <small class="text-muted">Enter 7 digits after prefix</small>
                    </div>

                    <div class="mb-3">
                        <label for="role_id" class="form-label"><i class="bi bi-person-badge"></i> Register As</label>
                        <select name="role_id" id="role_id" class="form-control" required>
                            <?php 
                            $roles_res->data_seek(0); // Reset pointer
                            while ($role = $roles_res->fetch_assoc()): 
                            ?>
                                <option value="<?= $role['role_id'] ?>" <?= $role['role_name'] == 'Donor' ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($role['role_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Select your role in the system</small>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label"><i class="bi bi-lock"></i> Password</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Minimum 6 characters" required>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label"><i class="bi bi-lock-fill"></i> Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               placeholder="Re-enter password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                        <i class="bi bi-person-plus"></i> Create Account
                    </button>
                </form>

                <div class="text-center mt-3">
                    <p class="mb-0">Already have an account? <a href="login.php" class="text-decoration-none">
                        <strong>Login here</strong>
                    </a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $con->close(); ?>
