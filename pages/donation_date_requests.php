<?php
require_once __DIR__ . '/../includes/init.php';
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: /test/login.php");
    exit();
}

if (($_SESSION['role_name'] ?? '') !== 'Admin') {
    header("HTTP/1.1 403 Forbidden");
    die("Access Denied. You don't have permission to view this page.");
}

require_once __DIR__ . '/../includes/db_config.php';
require_once __DIR__ . '/../includes/csrf.php';

$conn = getDBConnection();
$conn->query("CREATE TABLE IF NOT EXISTS donation_date_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    donor_name VARCHAR(120) NOT NULL,
    donor_email VARCHAR(160) NOT NULL,
    donor_phone VARCHAR(40) NOT NULL,
    requested_date DATE NOT NULL,
    meal_type VARCHAR(20) NOT NULL DEFAULT 'lunch',
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_requested_date (requested_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$mealColRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_date_requests' AND COLUMN_NAME = 'meal_type'");
if ($mealColRes && (int)$mealColRes->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE donation_date_requests ADD COLUMN meal_type VARCHAR(20) NOT NULL DEFAULT 'lunch' AFTER requested_date");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken()) {
        $error = 'Security validation failed. Please refresh and try again.';
    } else {
        $request_id = (int)($_POST['request_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $reviewed_by = (int)($_SESSION['user_id'] ?? 0);

        if ($request_id > 0 && in_array($action, ['approve', 'reject'], true)) {
            $nextStatus = ($action === 'approve') ? 'approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE donation_date_requests
                SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE request_id = ? AND status = 'pending'");
            $stmt->bind_param('sii', $nextStatus, $reviewed_by, $request_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $success = $action === 'approve' ? 'Request approved.' : 'Request rejected.';
            } else {
                $error = 'Unable to update the request. It may have already been processed.';
            }
            $stmt->close();
        } else {
            $error = 'Invalid request action.';
        }
    }
}

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$countRes = $conn->query("SELECT status, COUNT(*) AS c FROM donation_date_requests GROUP BY status");
if ($countRes) {
    while ($row = $countRes->fetch_assoc()) {
        $counts[$row['status']] = (int)$row['c'];
    }
}

$requests = [];
$listRes = $conn->query("SELECT * FROM donation_date_requests ORDER BY created_at DESC");
if ($listRes) {
    while ($row = $listRes->fetch_assoc()) {
        $requests[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Date Requests - Seela Suwa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<?php include ROOT_PATH . 'includes/navbar.php'; ?>

<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
        <h1 class="page-title"><i class="bi bi-calendar-event"></i> Donation Date Requests</h1>
        <p class="page-subtitle">Approve or reject requested donation dates.</p>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert-modern alert-danger-modern">
        <i class="bi bi-exclamation-triangle"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <div class="alert-modern alert-success-modern">
        <i class="bi bi-check-circle"></i>
        <span><?= htmlspecialchars($success) ?></span>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-info">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= (int)$counts['pending'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon emerald"><i class="bi bi-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?= (int)$counts['approved'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon rose"><i class="bi bi-x-circle"></i></div>
            <div class="stat-info">
                <div class="stat-label">Rejected</div>
                <div class="stat-value"><?= (int)$counts['rejected'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="modern-table-wrapper">
    <div class="modern-table-header">
        <h5><i class="bi bi-list-ul"></i> All Requests</h5>
    </div>
    <div class="table-responsive-modern">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Donor</th>
                    <th>Meal</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($requests) === 0): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No requests yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($requests as $req): ?>
                <tr>
                    <td><strong><?= htmlspecialchars(date('M d, Y', strtotime($req['requested_date']))) ?></strong></td>
                    <td><?= htmlspecialchars($req['donor_name']) ?></td>
                    <td>
                        <?php
require_once __DIR__ . '/../includes/init.php';
                        $mealLabel = $req['meal_type'] === 'morning_food' ? 'Morning Food' : 'Lunch';
                        ?>
                        <?= htmlspecialchars($mealLabel) ?>
                    </td>
                    <td>
                        <div><?= htmlspecialchars($req['donor_email']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($req['donor_phone']) ?></small>
                    </td>
                    <td>
                        <?php
require_once __DIR__ . '/../includes/init.php';
                        $status = $req['status'];
                        $badgeClass = $status === 'approved' ? 'bg-success' : ($status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                    </td>
                    <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($req['created_at']))) ?></td>
                    <td>
                        <?php if ($req['status'] === 'pending'): ?>
                        <div class="d-flex gap-2">
                            <form method="POST" action="donation_date_requests.php">
                                <?php if (function_exists('csrfField')): ?>
                                <?php csrfField(); ?>
                                <?php endif; ?>
                                <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2"></i> Approve</button>
                            </form>
                            <form method="POST" action="donation_date_requests.php">
                                <?php if (function_exists('csrfField')): ?>
                                <?php csrfField(); ?>
                                <?php endif; ?>
                                <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                <input type="hidden" name="action" value="reject">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x"></i> Reject</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span class="text-muted">Processed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
