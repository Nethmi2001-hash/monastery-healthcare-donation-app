<?php
session_start();

// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername  = "localhost";
$db_user_name = "root";
$db_password = "";
$dbname      = "monastery_healthcare";

$con = new mysqli($servername, $db_user_name, $db_password, $dbname);
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Search
$search = trim($_POST['search'] ?? '');
if ($search !== '') {
    $stmt = $con->prepare("SELECT u.*, r.role_name FROM users u 
                          JOIN roles r ON u.role_id = r.role_id 
                          WHERE u.name LIKE CONCAT('%', ?, '%') OR u.email LIKE CONCAT('%', ?, '%')");
    $stmt->bind_param("ss", $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $con->query("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id ORDER BY u.user_id");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<?php include 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header">
        <h2><i class="bi bi-people"></i> User Management</h2>
        <p>Manage system users and roles</p>
    </div>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form class="row g-3" method="post">
                <div class="col-md-10">
                    <div class="form-group-modern">
                        <input class="form-control-modern" type="text" name="search" placeholder="Search by name or email" value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button class="btn-modern btn-primary-modern w-100" type="submit"><i class="bi bi-search"></i> Search</button>
                </div>
                <?php if($search): ?>
                <div class="col-12">
                    <a href="table.php" class="btn-modern btn-outline-modern btn-sm"><i class="bi bi-x-circle"></i> Clear Search</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- User Table -->
    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <h5><i class="bi bi-table"></i> All Users</h5>
        </div>
        <div class="table-responsive-modern">
            <table class="modern-table">
               <thead>
                 <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created At</th>
                 </tr>
               </thead>
               <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= htmlspecialchars($row['user_id']) ?></strong></td>
                        <td><i class="bi bi-person-circle"></i> <?= htmlspecialchars($row['name']) ?></td>
                        <td><i class="bi bi-envelope"></i> <?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                        <td><span class="badge-modern badge-info"><?= htmlspecialchars($row['role_name']) ?></span></td>
                        <td>
                            <?php if ($row['status'] == 'active'): ?>
                                <span class="badge-modern badge-success badge-dot">Active</span>
                            <?php else: ?>
                                <span class="badge-modern badge-secondary badge-dot">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> No users found
                        </td>
                    </tr>
                <?php endif; ?>
               </tbody>
            </table>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php $con->close(); ?>
