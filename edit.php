<?php
session_start();
include 'navbar.php';

// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername  = "localhost";
$db_username = "root";
$db_password = "";
$dbname      = "nethmi";

$con = new mysqli($servername, $db_username, $db_password, $dbname);
if ($con->connect_error) die("Connection failed: " . $con->connect_error);

// Get user ID
$id = $_GET['id'] ?? $_POST['id'] ?? '';
if (!$id) die("User ID not specified.");

// Fetch user
$stmt = $con->prepare("SELECT * FROM user WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) die("User not found.");

// Fetch user titles for dropdown
$title_res = $con->query("SELECT * FROM user_titles ORDER BY name ASC");
$user_titles = [];
while ($t = $title_res->fetch_assoc()) $user_titles[] = $t;

// Handle form submission
$success = $error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $age = (int)$_POST['age'];
    $dob = $_POST['dob'];
    $phone_number = trim($_POST['phone_number']);
    $gender = $_POST['gender'] ?? '';
    $user_title_id = $_POST['user_title_id'] ?: null;
    $password_new = $_POST['password'] ?? '';
    $confirm_password_new = $_POST['confirm_password'] ?? '';

    // Validation
    if (!$full_name || !$dob || !$gender) {
        $error = "Full Name, DOB, and Gender are required.";
    } elseif ($password_new !== $confirm_password_new) {
        $error = "Passwords do not match.";
    } else {
        $fields = [];
        $params = [];
        $types = '';

        $fields[] = "full_name=?"; $params[] = $full_name; $types .= 's';
        $fields[] = "age=?"; $params[] = $age; $types .= 'i';
        $fields[] = "dob=?"; $params[] = $dob; $types .= 's';
        $fields[] = "phone_number=?"; $params[] = $phone_number; $types .= 's';
        $fields[] = "gender=?"; $params[] = $gender; $types .= 's';
        $fields[] = "user_title_id=?"; $params[] = $user_title_id; $types .= 'i';

        if ($password_new !== '') {
            $fields[] = "password=?"; 
            $params[] = password_hash($password_new, PASSWORD_DEFAULT);
            $types .= 's';
        }

        $sql = "UPDATE user SET " . implode(", ", $fields) . " WHERE id=?";
        $stmt = $con->prepare($sql);
        $types .= 'i';
        $params[] = $id;
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $success = "User updated successfully!";
            $user = $con->query("SELECT * FROM user WHERE id=$id")->fetch_assoc();
        } else {
            $error = "Failed to update user: " . $con->error;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container d-flex align-items-center justify-content-center min-vh-100">
    <div class="card shadow" style="max-width: 600px; width: 100%;">
        <div class="card-body">
            <h2 class="text-center mb-3">Edit User</h2>

            <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

            <form method="post">
                <input type="hidden" name="id" value="<?= $id ?>">

                <div class="mb-3">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                </div>
				

                <div class="mb-3">
                    <label>Age</label>
                    <input type="number" name="age" class="form-control" value="<?= $user['age'] ?>">
                </div>

                <div class="mb-3">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" class="form-control" value="<?= $user['dob'] ?>" required>
                </div>

                <div class="mb-3">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" class="form-control" value="<?= htmlspecialchars($user['phone_number']) ?>">
                </div>

                <div class="mb-3">
                    <label>Gender</label><br>
                    <input type="radio" name="gender" value="male" <?= $user['gender']=='male'?'checked':'' ?>> Male
                    <input type="radio" name="gender" value="female" <?= $user['gender']=='female'?'checked':'' ?>> Female
                </div>

                <div class="mb-3">
    <label for="user_title_id">User Title:</label>
    <select name="user_title_id" id="user_title_id" class="form-control">
        <option value="">-- Select Title --</option>
        <?php while ($t = $titles_res->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['user_title']) ?></option>
        <?php endwhile; ?>
    </select>
</div>

                
                <div class="mb-3">
                    <label>New Password (leave blank to keep unchanged)</label>
                    <input type="password" name="password" class="form-control">
                </div>

                <div class="mb-3">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary w-100">Update User</button>
            </form>

            <p class="mt-3 text-center"><a href="table.php">Back to Users Table</a></p>
        </div>
    </div>
</div>
</body>
</html>

<?php $con->close(); ?>
