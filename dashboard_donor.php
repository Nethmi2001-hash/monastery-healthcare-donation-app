<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/includes/db_config.php';
$conn = getDBConnection();

$userId = (int)($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['username'] ?? 'Donor';
$userEmail = $_SESSION['email'] ?? '';
$userRole = $_SESSION['role_name'] ?? 'Donor';

// If session lacks user data, reload it from DB using the logged-in user's ID
if (($userId <= 0 && empty($userEmail)) || ($userId <= 0 && !empty($userEmail))) {
    // Try looking up by email first
    if (!empty($userEmail)) {
        $fallbackStmt = $conn->prepare("SELECT u.user_id, u.name, u.email FROM users u WHERE u.email = ? LIMIT 1");
        $fallbackStmt->bind_param("s", $userEmail);
        $fallbackStmt->execute();
        $fallbackResult = $fallbackStmt->get_result();
        if ($fallbackResult && $fallbackRow = $fallbackResult->fetch_assoc()) {
            $userId = (int)$fallbackRow['user_id'];
            $userEmail = $fallbackRow['email'];
            $userName = $fallbackRow['name'];
        }
        $fallbackStmt->close();
    }
}

// If still no user, try to get any Donor from roles join
if ($userId <= 0 && empty($userEmail)) {
    $fallbackStmt = $conn->prepare("SELECT u.user_id, u.name, u.email FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Donor' AND u.status = 'active' LIMIT 1");
    $fallbackStmt->execute();
    $fallbackResult = $fallbackStmt->get_result();
    if ($fallbackResult && $fallbackRow = $fallbackResult->fetch_assoc()) {
        $userId = (int)$fallbackRow['user_id'];
        $userEmail = $fallbackRow['email'];
        $userName = $fallbackRow['name'];
    }
    $fallbackStmt->close();
}

// Debug: Log current session info
error_log("Dashboard Donor - User ID: $userId, Email: $userEmail, Role: $userRole");

// Get donor's donation stats with better filtering - use both ID and email for maximum compatibility
$stats = [
    'total_donated' => 0,
    'donation_count' => 0,
    'this_month' => 0,
    'verified_count' => 0
];

// Debug: Check what we're filtering by
error_log("Filtering by - User ID: $userId, Email: $userEmail");

// Simplified query - use either user ID or email, whichever is available
if ($userId > 0 || !empty($userEmail)) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total, COUNT(*) as cnt FROM donations WHERE (donor_user_id = ? OR donor_email = ?) AND status IN ('paid', 'verified', 'pending', 'rejected')");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_donated'] = $row['total'];
        $stats['donation_count'] = $row['cnt'];
        error_log("Stats query result - Total: {$row['total']}, Count: {$row['cnt']}");
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE (donor_user_id = ? OR donor_email = ?) AND status IN ('paid', 'verified', 'pending') AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['this_month'] = $result->fetch_assoc()['total'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM donations WHERE (donor_user_id = ? OR donor_email = ?) AND status = 'verified'");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $stats['verified_count'] = $result->fetch_assoc()['c'];
    }
    $stmt->close();
} else {
    error_log("No valid user ID or email for donation queries");
}

// Recent donations
$recent_donations = [];
if ($userId > 0 || !empty($userEmail)) {
    $stmt = $conn->prepare("
        SELECT d.*, c.name as category_name 
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        WHERE (d.donor_user_id = ? OR d.donor_email = ?) 
        ORDER BY d.created_at DESC 
        LIMIT 10
    ");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_donations[] = $row;
        }
    }
    $stmt->close();
}

// Monthly donation trend (last 6 months)
$monthly_trend = [];
if ($userId > 0 || !empty($userEmail)) {
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $trendStmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE (donor_user_id = ? OR donor_email = ?) AND DATE_FORMAT(created_at, '%Y-%m') = ? AND status IN ('paid', 'verified', 'pending')");
        $trendStmt->bind_param("iss", $userId, $userEmail, $month);
        $trendStmt->execute();
        $trendResult = $trendStmt->get_result();
        $monthlyAmount = 0;
        if ($trendResult) {
            $trendRow = $trendResult->fetch_assoc();
            $monthlyAmount = $trendRow['total'] ?? 0;
        }
        $monthly_trend[] = [
            'month' => date('M', strtotime($month)),
            'amount' => (float)$monthlyAmount
        ];
        $trendStmt->close();
    }
}

// Donations by category
$by_category = [];
if ($userId > 0 || !empty($userEmail)) {
    $stmt = $conn->prepare("
        SELECT c.name as category_name, COALESCE(SUM(d.amount), 0) as total 
        FROM donations d 
        JOIN categories c ON d.category_id = c.category_id 
        WHERE (d.donor_user_id = ? OR d.donor_email = ?) AND d.status IN ('paid', 'verified', 'pending')
        GROUP BY c.category_id, c.name
        ORDER BY total DESC
    ");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $by_category[] = $row;
        }
    }
    $stmt->close();
}

// Debug output (remove in production)
error_log("User ID: " . $userId . ", Email: " . $userEmail);
error_log("Monthly Trend: " . json_encode($monthly_trend));
error_log("By Category: " . json_encode($by_category));

// Fetch categories for donation form
$categories = [];
$catResult = $conn->query("SELECT category_id, name FROM categories ORDER BY name");
if ($catResult) {
    while ($catRow = $catResult->fetch_assoc()) {
        $categories[] = $catRow;
    }
}

// Handle AJAX donation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_donate'])) {
    header('Content-Type: application/json');
    try {
        $donor_name = $userName;
        $donor_email = $userEmail;
        $donor_user_id = $userId;
        $amount = floatval($_POST['amount']);
        $category_id = intval($_POST['category_id']);
        $bank = trim($_POST['bank'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $reference_number = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = 'pending';

        if (empty($donor_name) || empty($category_id)) {
            echo json_encode(['success' => false, 'message' => 'Donor name and category are required.']);
            exit;
        }
        if ($amount < 100) {
            echo json_encode(['success' => false, 'message' => 'Amount must be at least Rs. 100.00.']);
            exit;
        }

        // Handle bank slip upload
        $slip_path = null;
        if (isset($_FILES['bank_slip']) && $_FILES['bank_slip']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/slips/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($_FILES['bank_slip']['name'], PATHINFO_EXTENSION);
            $filename = 'slip_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['bank_slip']['tmp_name'], $uploadDir . $filename)) {
                $slip_path = 'uploads/slips/' . $filename;
            }
        }

        $stmt = $conn->prepare("INSERT INTO donations (donor_name, donor_email, donor_user_id, amount, category_id, bank, brand, bank_reference, notes, status, slip_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $created_by = $_SESSION['user_id'];
        $stmt->bind_param("ssiisssssssi", $donor_name, $donor_email, $donor_user_id, $amount, $category_id, $bank, $brand, $reference_number, $notes, $status, $slip_path, $created_by);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Donation submitted for verification!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// Overall monastery transparency data
$total_monastery_donations = 0;
$total_monastery_expenses = 0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as t FROM donations WHERE status IN ('paid', 'verified')");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $total_monastery_donations = $result->fetch_assoc()['t'];

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as t FROM bills");
$stmt->execute();
$result = $stmt->get_result();
if ($result) $total_monastery_expenses = $result->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - Seela Suwa Herath</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            overflow-x: hidden;
        }
        .container-fluid {
            max-width: 100%;
            height: auto;
        }
        .chart-card {
            max-height: 400px;
            overflow: hidden;
        }
        .chart-card canvas {
            max-height: 300px !important;
            width: 100% !important;
        }
        .welcome-card {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container-fluid px-4 py-4">
    <!-- Welcome -->
    <div class="welcome-card animate-fade-in">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div>
                <h2>
                    <?php if ($userRole === 'Donor'): ?>
                        <i class="bi bi-suit-heart me-2"></i>Welcome, <?= htmlspecialchars($userName) ?>!
                    <?php elseif ($userRole === 'Doctor'): ?>
                        <i class="bi bi-heart-pulse me-2"></i>Welcome, Dr. <?= htmlspecialchars($userName) ?>!
                    <?php else: ?>
                        <i class="bi bi-person-heart me-2"></i>Welcome, <?= htmlspecialchars($userName) ?>!
                    <?php endif; ?>
                </h2>
                <p style="margin:0;">
                    <?php if ($userRole === 'Donor'): ?>
                        Thank you for your generous contributions to Seela Suwa Herath Bikshu Gilan Arana.
                    <?php elseif ($userRole === 'Doctor'): ?>
                        View your donation history and support the monastery healthcare mission.
                    <?php else: ?>
                        Support Seela Suwa Herath Bikshu Gilan Arana through your donations.
                    <?php endif; ?>
                </p>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                <div class="welcome-date">
                    <i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4 stagger-children">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #f97316;">
                <div class="stat-icon" style="background:#fff7ed;color:#f97316;"><i class="bi bi-cash-stack"></i></div>
                <div class="stat-info">
                    <div class="stat-label">My Total Donations</div>
                    <div class="stat-value">Rs.<?= number_format($stats['total_donated']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #0284c7;">
                <div class="stat-icon" style="background:#e0f2fe;color:#0284c7;"><i class="bi bi-receipt"></i></div>
                <div class="stat-info">
                    <div class="stat-label">My Donation Count</div>
                    <div class="stat-value"><?= $stats['donation_count'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #7c3aed;">
                <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="bi bi-calendar-month"></i></div>
                <div class="stat-info">
                    <div class="stat-label">This Month</div>
                    <div class="stat-value">Rs.<?= number_format($stats['this_month']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #d97706;">
                <div class="stat-icon" style="background:#fffbeb;color:#d97706;"><i class="bi bi-patch-check"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Verified Donations</div>
                    <div class="stat-value"><?= $stats['verified_count'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="chart-card animate-fade-in" style="height:100%;">
                <div class="chart-header">
                    <h6><i class="bi bi-graph-up me-2"></i>My Donation Trend</h6>
                    <span class="badge-modern badge-neutral">Last 6 months</span>
                </div>
                <?php 
                // Debug output 
                echo "<!-- Monthly trend count: " . count($monthly_trend) . ", sum: " . array_sum(array_column($monthly_trend, 'amount')) . " -->\n";
                ?>
                <canvas id="trendChart" height="160"></canvas>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="chart-card animate-fade-in" style="height:100%;">
                <div class="chart-header">
                    <h6><i class="bi bi-pie-chart me-2"></i>My Donations by Category</h6>
                </div>
                <?php 
                // Debug output 
                echo "<!-- Category count: " . count($by_category) . " -->\n";
                ?>
                <canvas id="categoryChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Donations -->
    <div class="modern-card mb-4 animate-fade-in">
        <div class="card-header-modern">
            <h6><i class="bi bi-clock-history me-2"></i>My Recent Donations</h6>
            <?php if (count($recent_donations) > 0): ?>
                <span class="badge-modern badge-neutral"><?= count($recent_donations) ?> recent</span>
            <?php endif; ?>
        </div>
        <div class="card-body-modern" style="padding:0;">
            <?php if (count($recent_donations) > 0): ?>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_donations as $don): ?>
                        <tr>
                            <td><?= date('M j, Y', strtotime($don['created_at'])) ?></td>
                            <td><span class="badge-modern badge-neutral"><?= htmlspecialchars($don['category_name'] ?? 'N/A') ?></span></td>
                            <td>
                                <?php
                                    $method_icons = ['cash' => 'bi-cash', 'bank' => 'bi-bank', 'card_sandbox' => 'bi-credit-card'];
                                    $icon = $method_icons[$don['method']] ?? 'bi-wallet2';
                                ?>
                                <i class="bi <?= $icon ?> me-1"></i><?= ucfirst(str_replace('_', ' ', $don['method'])) ?>
                            </td>
                            <td style="font-weight:700;color:var(--primary-600);">Rs.<?= number_format($don['amount'], 2) ?></td>
                            <td>
                                <?php
                                    $status_styles = [
                                        'pending'   => 'background:#fef9c3;color:#ca8a04;',
                                        'paid'      => 'background:#dbeafe;color:#2563eb;',
                                        'verified'  => 'background:#dcfce7;color:#16a34a;',
                                        'rejected'  => 'background:#fee2e2;color:#dc2626;',
                                        'failed'    => 'background:#fee2e2;color:#dc2626;',
                                        'cancelled' => 'background:#f1f5f9;color:#64748b;',
                                    ];
                                    $dot_colors = [
                                        'pending'   => '#ca8a04',
                                        'paid'      => '#2563eb',
                                        'verified'  => '#16a34a',
                                        'rejected'  => '#dc2626',
                                        'failed'    => '#dc2626',
                                        'cancelled' => '#64748b',
                                    ];
                                    $st = !empty($don['status']) ? $don['status'] : 'pending';
                                    $bs = $status_styles[$st] ?? 'background:#f1f5f9;color:#64748b;';
                                    $dc = $dot_colors[$st] ?? '#64748b';
                                ?>
                                <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;<?= $bs ?>">
                                    <span style="width:7px;height:7px;border-radius:50%;background:<?= $dc ?>;display:inline-block;"></span>
                                    <?= ucfirst($st) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state" style="padding:48px 16px;">
                    <i class="bi bi-gift" style="font-size:48px;color:var(--primary-400);"></i>
                    <h5 style="font-size:16px;margin-top:16px;">No donations yet</h5>
                    <p style="font-size:13px;color:var(--text-secondary);">Make your first donation to support the monastery</p>
                    <a href="public_donate.php" class="btn btn-sm" style="background:var(--primary-500);color:#fff;padding:8px 20px;border-radius:8px;font-weight:600;margin-top:8px;">
                        <i class="bi bi-heart me-1"></i>Donate Now
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Make Donation Modal -->
<div class="modal fade" id="donateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-heart"></i> Make a Donation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="donateForm" enctype="multipart/form-data">
                <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                    <div id="donateAlert" style="display:none;" class="alert alert-sm mb-3"></div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Amount (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="100" required placeholder="Min Rs.100">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Bank <span class="text-danger">*</span></label>
                            <select name="bank" class="form-select" required>
                                <option value="">-- Select --</option>
                                <option value="Commercial Bank">Commercial Bank</option>
                                <option value="People's Bank">People's Bank</option>
                                <option value="Bank of Ceylon">Bank of Ceylon</option>
                                <option value="Sampath Bank">Sampath Bank</option>
                                <option value="Hatton National Bank">HNB</option>
                                <option value="Seylan Bank">Seylan Bank</option>
                                <option value="Nations Trust Bank">NTB</option>
                                <option value="DFCC Bank">DFCC Bank</option>
                                <option value="Union Bank">Union Bank</option>
                                <option value="Pan Asia Bank">Pan Asia Bank</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-semibold">Branch <span class="text-danger">*</span></label>
                            <select name="brand" class="form-select" required>
                                <option value="">-- Select --</option>
                                <option value="Colombo Main">Colombo Main</option>
                                <option value="Kandy">Kandy</option>
                                <option value="Galle">Galle</option>
                                <option value="Negombo">Negombo</option>
                                <option value="Matara">Matara</option>
                                <option value="Kurunegala">Kurunegala</option>
                                <option value="Anuradhapura">Anuradhapura</option>
                                <option value="Ratnapura">Ratnapura</option>
                                <option value="Batticaloa">Batticaloa</option>
                                <option value="Jaffna">Jaffna</option>
                                <option value="Dehiwala">Dehiwala</option>
                                <option value="Maharagama">Maharagama</option>
                                <option value="Kotte">Kotte</option>
                                <option value="Moratuwa">Moratuwa</option>
                                <option value="Panadura">Panadura</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reference Number <span class="text-danger">*</span></label>
                        <input type="text" name="reference_number" class="form-control" placeholder="Bank ref / transaction ID" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Bank Slip Upload <span class="text-danger">*</span></label>
                        <input type="file" name="bank_slip" class="form-control" accept="image/*,.pdf" required>
                        <small class="text-muted">JPG, PNG, PDF - max 5MB</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Additional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" id="donateSubmitBtn" style="background:var(--primary-500,#f97316);color:#fff;font-weight:600;">
                        <i class="bi bi-heart"></i> Submit Donation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('donateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const btn = document.getElementById('donateSubmitBtn');
    const alertDiv = document.getElementById('donateAlert');
    const formData = new FormData(form);
    formData.append('ajax_donate', '1');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
    alertDiv.style.display = 'none';

    fetch('dashboard.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        alertDiv.style.display = 'block';
        if (data.success) {
            alertDiv.className = 'alert alert-success mb-3';
            alertDiv.textContent = data.message;
            form.reset();
            setTimeout(() => location.reload(), 1500);
        } else {
            alertDiv.className = 'alert alert-danger mb-3';
            alertDiv.textContent = data.message;
        }
    })
    .catch(() => {
        alertDiv.style.display = 'block';
        alertDiv.className = 'alert alert-danger mb-3';
        alertDiv.textContent = 'Network error, please try again.';
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-heart"></i> Submit Donation';
    });
});
</script>

<?php include 'includes/footer.php'; ?>

<script>
// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Add error boundary
    window.addEventListener('error', function(e) {
        console.error('JavaScript error:', e.error);
        return true; // Prevent default browser error handling
    });
    
    // Trend Chart
    const trendCanvas = document.getElementById('trendChart');
    if (trendCanvas) {
        const trendCtx = trendCanvas.getContext('2d');
        
        // Destroy existing chart if it exists
        if (window.trendChart instanceof Chart) {
            window.trendChart.destroy();
        }
        
        const trendLabels = <?= json_encode(array_slice(array_column($monthly_trend, 'month'), 0, 6), JSON_NUMERIC_CHECK) ?>;
        const trendData = <?= json_encode(array_slice(array_column($monthly_trend, 'amount'), 0, 6), JSON_NUMERIC_CHECK) ?>;
        
        console.log('Trend data:', {labels: trendLabels, data: trendData});
        
        // Validate data
        if (!Array.isArray(trendLabels) || !Array.isArray(trendData) || trendLabels.length === 0) {
            console.warn('Invalid trend data');
            return;
        }
        
        try {
            window.trendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Donations (Rs.)',
                        data: trendData,
                        backgroundColor: 'rgba(249, 115, 22, 0.08)',
                        borderColor: '#f97316',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#f97316',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1000
                    },
                    plugins: { 
                        legend: { display: false } 
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: 'rgba(0,0,0,0.04)' }, 
                            ticks: { callback: function(value) { return 'Rs.' + value.toLocaleString(); } }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating trend chart:', error);
        }
    }

    // Category Chart
    const catCanvas = document.getElementById('categoryChart');
    if (catCanvas) {
        const catCtx = catCanvas.getContext('2d');
        
        // Destroy existing chart if it exists
        if (window.categoryChart instanceof Chart) {
            window.categoryChart.destroy();
        }
        
        const catLabels = <?= json_encode(array_slice(array_column($by_category, 'category_name'), 0, 10)) ?>;
        const catData = <?= json_encode(array_slice(array_column($by_category, 'total'), 0, 10), JSON_NUMERIC_CHECK) ?>;
        const catColors = ['#f97316', '#0284c7', '#7c3aed', '#d97706', '#dc2626', '#0891b2'];
        
        console.log('Category data:', {labels: catLabels, data: catData});
        
        // Handle empty data case
        if (!Array.isArray(catLabels) || !Array.isArray(catData) || catLabels.length === 0) {
            // Show empty state chart
            const emptyLabels = ['No Data'];
            const emptyData = [1];
            const emptyColors = ['#e5e7eb'];
            
            try {
                window.categoryChart = new Chart(catCtx, {
                    type: 'doughnut',
                    data: {
                        labels: emptyLabels,
                        datasets: [{
                            data: emptyData,
                            backgroundColor: emptyColors,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { enabled: false }
                        }
                    }
                });
            } catch (error) {
                console.error('Error creating empty category chart:', error);
            }
        } else {
            // Show actual data chart
            try {
                window.categoryChart = new Chart(catCtx, {
                    type: 'doughnut',
                    data: {
                        labels: catLabels,
                        datasets: [{
                            data: catData,
                            backgroundColor: catColors.slice(0, catLabels.length),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 1000
                        },
                        plugins: {
                            legend: { 
                                position: 'bottom', 
                                labels: { 
                                    font: { size: 12, family: 'Inter' }, 
                                    padding: 16, 
                                    boxWidth: 12 
                                } 
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error creating category chart:', error);
            }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
