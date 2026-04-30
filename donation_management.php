<?php
session_start();

// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/includes/db_config.php';
$conn = getDBConnection();

// Handle AJAX verify request
if (isset($_POST['ajax_verify']) && isset($_POST['donation_id'])) {
    header('Content-Type: application/json');
    $donation_id = intval($_POST['donation_id']);
    
    // Get donation details before verification
    $donation_query = $conn->prepare("
        SELECT d.*, c.name as category_name 
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        WHERE d.donation_id = ?
    ");
    $donation_query->bind_param("i", $donation_id);
    $donation_query->execute();
    $donation_result = $donation_query->get_result();
    $donation_data = $donation_result->fetch_assoc();
    $donation_query->close();
    
    $stmt = $conn->prepare("UPDATE donations SET status='verified', verified_by=?, verified_at=NOW() WHERE donation_id=?");
    $verified_by = $_SESSION['user_id'];
    $stmt->bind_param("ii", $verified_by, $donation_id);
    
    if ($stmt->execute()) {
        $msg = "Donation verified successfully!";
        if (!empty($donation_data['donor_email'])) {
            require_once __DIR__ . '/includes/email_helper.php';
            if (sendDonationThankYou($donation_data)) {
                $msg .= " Thank you email sent!";
            }
        }
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// Handle AJAX reject request
if (isset($_POST['ajax_reject']) && isset($_POST['donation_id'])) {
    header('Content-Type: application/json');
    $donation_id = intval($_POST['donation_id']);
    
    $stmt = $conn->prepare("UPDATE donations SET status='rejected', verified_by=?, verified_at=NOW() WHERE donation_id=?");
    $rejected_by = $_SESSION['user_id'];
    $stmt->bind_param("ii", $rejected_by, $donation_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Donation rejected.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit();
}

$error = "";
$success = "";

// Get user info
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['username'] ?? 'User';
$userEmail = $_SESSION['email'] ?? '';
$userRole = $_SESSION['role_name'] ?? 'Admin';
$isAdmin = ($userRole === 'Admin');
$isDonor = ($userRole === 'Donor');
$isDoctor = ($userRole === 'Doctor');

// Handle file upload for bank slip
function handleBankSlipUpload() {
    if (!isset($_FILES['bank_slip']) || $_FILES['bank_slip']['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $uploadDir = 'uploads/bank_slips/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['bank_slip'];
    $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $filePath = $uploadDir . $fileName;
    
    // Check file type (images only)
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Only image files are allowed for bank slips.');
    }
    
    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size must be less than 5MB.');
    }
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return $filePath;
    }
    
    throw new Exception('Failed to upload bank slip.');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    if ($form_name === 'create') {
        try {
            // For donors, auto-fill their information
            if ($isDonor) {
                $donor_name = $userName;
                $donor_email = $userEmail;
                $donor_user_id = $userId;
            } else {
                $donor_name = trim($_POST['donor_name']);
                $donor_email = trim($_POST['donor_email']);
                $donor_user_id = null;
            }
            
            $amount = floatval($_POST['amount']);
            $category_id = intval($_POST['category_id']);
            $bank = trim($_POST['bank'] ?? '');
            $brand = trim($_POST['brand'] ?? '');
            $reference_number = trim($_POST['reference_number'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $status = $isDonor ? 'pending' : ($_POST['status'] ?? 'pending');
            
            // Handle bank slip upload
            $slip_path = null;
            try {
                $slip_path = handleBankSlipUpload();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }

            if (empty($donor_name) || empty($category_id)) {
                $error = "Donor name and category are required.";
            } elseif ($amount < 100 || !is_numeric($amount)) {
                $error = "Amount must be at least Rs. 100.00.";
            } elseif (!$error) { // Only proceed if no upload error occurred
                $stmt = $conn->prepare("INSERT INTO donations (donor_name, donor_email, donor_user_id, amount, category_id, bank, brand, bank_reference, notes, status, slip_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $created_by = $_SESSION['user_id'];
                $stmt->bind_param("ssiisssssssi", $donor_name, $donor_email, $donor_user_id, $amount, $category_id, $bank, $brand, $reference_number, $notes, $status, $slip_path, $created_by);
                
                if ($stmt->execute()) {
                    if ($isDonor) {
                        $success = "Your donation has been submitted for verification!";
                        if ($slip_path) {
                            $success .= " Bank slip uploaded successfully.";
                        }
                    } else {
                        $success = "Donation recorded successfully!";
                    }
                } else {
                    $error = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $error = "Error processing donation: " . $e->getMessage();
        }
    }

    if ($form_name === 'update') {
        $donation_id = intval($_POST['donation_id']);
        $donor_name = trim($_POST['donor_name']);
        $donor_email = trim($_POST['donor_email']);
        $donor_phone = trim($_POST['donor_phone']);
        $amount = floatval($_POST['amount']);
        $category_id = intval($_POST['category_id']);
        $payment_method = $_POST['payment_method'];
        $reference_number = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'];

        $stmt = $conn->prepare("UPDATE donations SET donor_name=?, donor_email=?, donor_phone=?, amount=?, category_id=?, payment_method=?, reference_number=?, notes=?, status=? WHERE donation_id=?");
        $stmt->bind_param("sssdissssi", $donor_name, $donor_email, $donor_phone, $amount, $category_id, $payment_method, $reference_number, $notes, $status, $donation_id);
        
        if ($stmt->execute()) {
            $success = "Donation updated successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    if ($form_name === 'delete') {
        $donation_id = intval($_POST['donation_id']);
        $stmt = $conn->prepare("DELETE FROM donations WHERE donation_id=?");
        $stmt->bind_param("i", $donation_id);
        
        if ($stmt->execute()) {
            $success = "Donation deleted successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    if ($form_name === 'verify') {
        $donation_id = intval($_POST['donation_id']);
        
        // Get donation details before verification
        $donation_query = $conn->prepare("
            SELECT d.*, c.name as category_name 
            FROM donations d 
            LEFT JOIN categories c ON d.category_id = c.category_id 
            WHERE d.donation_id = ?
        ");
        $donation_query->bind_param("i", $donation_id);
        $donation_query->execute();
        $donation_result = $donation_query->get_result();
        $donation_data = $donation_result->fetch_assoc();
        $donation_query->close();
        
        // Verify donation
        $stmt = $conn->prepare("UPDATE donations SET status='verified', verified_by=?, verified_at=NOW() WHERE donation_id=?");
        $verified_by = $_SESSION['user_id'];
        $stmt->bind_param("ii", $verified_by, $donation_id);
        
        if ($stmt->execute()) {
            $success = "Donation verified successfully!";
            
            // Send thank you email if email is provided
            if (!empty($donation_data['donor_email'])) {
                require_once __DIR__ . '/includes/email_helper.php';
                if (sendDonationThankYou($donation_data)) {
                    $success .= " Thank you email sent to donor!";
                }
            }
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get categories (only donation type)
$categories = [];
$cat_result = $conn->query("SELECT * FROM categories WHERE type='donation' ORDER BY name ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get donations with category info - filter by user role
$donations = [];
if ($isDonor || $isDoctor) {
    // For donors and doctors, show only their own donations
    $stmt = $conn->prepare("
        SELECT d.*, c.name as category_name, u.name as created_by_name 
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        LEFT JOIN users u ON d.created_by = u.user_id 
        WHERE (d.donor_user_id = ? OR d.donor_email = ?)
        ORDER BY d.created_at DESC
    ");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }
    }
    $stmt->close();
} else {
    // For admins, show all donations
    $result = $conn->query("
        SELECT d.*, c.name as category_name, u.name as created_by_name 
        FROM donations d 
        LEFT JOIN categories c ON d.category_id = c.category_id 
        LEFT JOIN users u ON d.created_by = u.user_id 
        ORDER BY d.created_at DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }
    }
}

// Calculate statistics - role specific
$stats = [
    'total_donations' => 0,
    'pending_amount' => 0,
    'verified_amount' => 0,
    'this_month' => 0
];

if ($isDonor || $isDoctor) {
    // For donors/doctors, show only their own statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM donations WHERE (donor_user_id = ? OR donor_email = ?)");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_donations'] = $row['count'];
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE (donor_user_id = ? OR donor_email = ?) AND status='pending'");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $stats['pending_amount'] = $result->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE (donor_user_id = ? OR donor_email = ?) AND status='verified'");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $stats['verified_amount'] = $result->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE (donor_user_id = ? OR donor_email = ?) AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->bind_param("is", $userId, $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $stats['this_month'] = $result->fetch_assoc()['total'];
    $stmt->close();
} else {
    // For admins, show all statistics
    $result = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM donations");
    if ($result) {
        $row = $result->fetch_assoc();
        $stats['total_donations'] = $row['count'];
    }

    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE status='pending'");
    if ($result) $stats['pending_amount'] = $result->fetch_assoc()['total'];

    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE status='verified'");
    if ($result) $stats['verified_amount'] = $result->fetch_assoc()['total'];

    $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    if ($result) $stats['this_month'] = $result->fetch_assoc()['total'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Donation Management - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .badge-success { background: #dcfce7 !important; color: #16a34a !important; }
        .badge-success::before { background: #16a34a !important; }
        .badge-warning { background: #fef9c3 !important; color: #ca8a04 !important; }
        .badge-warning::before { background: #ca8a04 !important; }
        .badge-danger { background: #fee2e2 !important; color: #dc2626 !important; }
        .badge-danger::before { background: #dc2626 !important; }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <?php if ($isDonor || $isDoctor): ?>
                <h1 class="page-title"><i class="bi bi-heart"></i> My Donations</h1>
                <p class="page-subtitle">View and manage your donation history</p>
            <?php else: ?>
                <h1 class="page-title"><i class="bi bi-cash-coin"></i> Donation Management</h1>
                <p class="page-subtitle">Track and manage monastery donations</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon emerald">
                    <i class="bi bi-gift"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label"><?= ($isDonor || $isDoctor) ? 'My Total Donations' : 'Total Donations' ?></div>
                    <div class="stat-value"><?= $stats['total_donations'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon amber">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Pending Verification</div>
                    <div class="stat-value">Rs. <?= number_format($stats['pending_amount'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Verified Donations</div>
                    <div class="stat-value">Rs. <?= number_format($stats['verified_amount'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">This Month</div>
                    <div class="stat-value">Rs. <?= number_format($stats['this_month'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Donations Table -->
    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <h5><i class="bi bi-list-ul"></i> <?= ($isDonor || $isDoctor) ? 'My Recent Donations' : 'Recent Donations' ?></h5>
        </div>

        <!-- Advanced Search Section -->
        <?php if ($isAdmin): ?>
            <div id="advanced-search" data-type="donations" class="px-3 pt-3"></div>
        <?php endif; ?>

        <div class="table-responsive-modern">
            <div id="donations-list">
            <?php if (count($donations) > 0): ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Donor</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($donations as $donation): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($donation['donor_name']) ?></strong>
                                <?php if ($donation['donor_email']): ?>
                                    <br><small class="text-muted"><i class="bi bi-envelope"></i> <?= htmlspecialchars($donation['donor_email']) ?></small>
                                <?php endif; ?>
                                <?php if ($donation['bank'] || $donation['brand']): ?>
                                    <br><small class="text-muted">
                                        <?php if ($donation['bank']): ?>
                                            <i class="bi bi-bank"></i> <?= htmlspecialchars($donation['bank']) ?>
                                        <?php endif; ?>
                                        <?php if ($donation['bank'] && $donation['brand']): echo ' • '; endif; ?>
                                        <?php if ($donation['brand']): ?>
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($donation['brand']) ?>
                                        <?php endif; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>Rs. <?= number_format($donation['amount'], 2) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($donation['category_name']) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($donation['slip_path'])): ?>
                                    <a href="#" class="btn-modern btn-primary-modern btn-sm-modern" onclick="viewSlip('<?= htmlspecialchars($donation['slip_path']) ?>', '<?= htmlspecialchars($donation['donor_name']) ?>')" title="View Uploaded Bank Slip">
                                        <i class="bi bi-image"></i> View Slip
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-dash-circle"></i> No Slip</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_styles = [
                                    'pending'  => 'background:#fef9c3;color:#ca8a04;',
                                    'verified' => 'background:#dcfce7;color:#16a34a;',
                                    'rejected' => 'background:#fee2e2;color:#dc2626;',
                                    'paid'     => 'background:#dbeafe;color:#2563eb;',
                                ];
                                $dot_colors = [
                                    'pending'  => '#ca8a04',
                                    'verified' => '#16a34a',
                                    'rejected' => '#dc2626',
                                    'paid'     => '#2563eb',
                                ];
                                $st = !empty($donation['status']) ? $donation['status'] : 'pending';
                                $badge_style = $status_styles[$st] ?? 'background:#f1f5f9;color:#64748b;';
                                $dot_color = $dot_colors[$st] ?? '#64748b';
                                ?>
                                <span data-status-badge="true" style="display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;<?= $badge_style ?>">
                                    <span style="width:7px;height:7px;border-radius:50%;background:<?= $dot_color ?>;display:inline-block;"></span>
                                    <?= ucfirst($st) ?>
                                </span>
                            </td>
                            <td>
                                <?= date('M d, Y', strtotime($donation['created_at'])) ?>
                                <?php if ($donation['notes']): ?>
                                    <br><small class="text-muted"><i class="bi bi-sticky"></i> <?= htmlspecialchars($donation['notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    <?php if ($st === 'pending'): ?>
                                        <button type="button" class="btn-modern btn-success-modern btn-sm-modern verify-btn" data-id="<?= $donation['donation_id'] ?>" title="Verify">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                        <button type="button" class="btn-modern btn-danger-modern btn-sm-modern reject-btn" data-id="<?= $donation['donation_id'] ?>" title="Reject">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($st === 'verified'): ?>
                                        <a href="generate_receipt.php?id=<?= $donation['donation_id'] ?>" target="_blank" class="btn-modern btn-primary-modern btn-sm-modern" title="Download Receipt">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">No donations recorded yet</p>
                </div>
            <?php endif; ?>
            </div><!-- END donations-list -->
        </div>
    </div>

<!-- Add Donation Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <?php if ($isDonor || $isDoctor): ?>
                    <h5 class="modal-title"><i class="bi bi-heart"></i> Make a Donation</h5>
                <?php else: ?>
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Record New Donation</h5>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                    <input type="hidden" name="form_name" value="create">
                    
                    <div class="form-group-modern">
                        <label class="form-label-modern">Donor Name <span class="required">*</span></label>
                        <?php if ($isDonor || $isDoctor): ?>
                            <input type="text" name="donor_name" class="form-control-modern" 
                                   value="<?= htmlspecialchars($userName) ?>" 
                                   readonly required>
                        <?php else: ?>
                            <input type="text" name="donor_name" class="form-control-modern" required>
                        <?php endif; ?>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Donor Email</label>
                        <?php if ($isDonor || $isDoctor): ?>
                            <input type="email" name="donor_email" class="form-control-modern" 
                                   value="<?= htmlspecialchars($userEmail) ?>" 
                                   readonly>
                        <?php else: ?>
                            <input type="email" name="donor_email" class="form-control-modern">
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Amount (Rs.) <span class="required">*</span></label>
                                <input type="number" name="amount" class="form-control-modern" step="0.01" min="100" required 
                                       oninput="validateAmount(this)" placeholder="Min Rs.100">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Category <span class="required">*</span></label>
                                <select name="category_id" class="form-select-modern" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Bank <span class="required">*</span></label>
                                <select name="bank" class="form-select-modern" required>
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
                        </div>
                        <div class="col-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Branch</label>
                                <select name="brand" class="form-select-modern">
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
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Reference Number</label>
                        <input type="text" name="reference_number" class="form-control-modern" placeholder="Bank ref / transaction ID">
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Bank Slip Upload</label>
                        <input type="file" name="bank_slip" class="form-control-modern" accept="image/*,.pdf">
                        <small class="text-muted">JPG, PNG, PDF - max 5MB</small>
                    </div>

                    <?php if ($isAdmin): ?>
                        <div class="form-group-modern">
                            <label class="form-label-modern">Status</label>
                            <select name="status" class="form-select-modern">
                                <option value="pending">Pending Verification</option>
                                <option value="verified">Verified</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="status" value="pending">
                    <?php endif; ?>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Notes</label>
                        <textarea name="notes" class="form-control-modern" rows="2" placeholder="Additional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">
                        <?php if ($isDonor || $isDoctor): ?>
                            <i class="bi bi-heart"></i> Submit Donation
                        <?php else: ?>
                            <i class="bi bi-save"></i> Save Donation
                        <?php endif; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Donation Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Donation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="form_name" value="update">
                    <input type="hidden" name="donation_id" id="edit_donation_id">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Donor Name <span class="required">*</span></label>
                                <input type="text" name="donor_name" id="edit_donor_name" class="form-control-modern" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Donor Email</label>
                                <input type="email" name="donor_email" id="edit_donor_email" class="form-control-modern">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Donor Phone</label>
                                <input type="text" name="donor_phone" id="edit_donor_phone" class="form-control-modern">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Amount (Rs.) <span class="required">*</span></label>
                                <input type="number" name="amount" id="edit_amount" class="form-control-modern" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Category <span class="required">*</span></label>
                                <select name="category_id" id="edit_category_id" class="form-select-modern" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Payment Method <span class="required">*</span></label>
                                <select name="payment_method" id="edit_payment_method" class="form-select-modern" required>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="card">Card</option>
                                    <option value="payhere">PayHere (Online)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Reference Number</label>
                                <input type="text" name="reference_number" id="edit_reference_number" class="form-control-modern">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Status</label>
                                <select name="status" id="edit_status" class="form-select-modern">
                                    <option value="pending">Pending Verification</option>
                                    <option value="verified">Verified</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control-modern" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">
                        <i class="bi bi-save"></i> Update Donation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PayHere Payment Modal -->
<div class="modal fade" id="payhereModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-credit-card"></i> PayHere Online Payment (Sandbox)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert-modern alert-warning-modern mb-3">
                    <i class="bi bi-info-circle"></i>
                    <span><strong>Test Mode:</strong> This is PayHere SANDBOX for testing. No real money will be charged.</span>
                </div>

                <form id="payhereForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Your Name <span class="required">*</span></label>
                                <input type="text" id="ph_donor_name" class="form-control-modern" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Your Email <span class="required">*</span></label>
                                <input type="email" id="ph_donor_email" class="form-control-modern" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Phone Number <span class="required">*</span></label>
                                <input type="text" id="ph_donor_phone" class="form-control-modern" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Donation Amount (Rs.) <span class="required">*</span></label>
                                <input type="number" id="ph_amount" class="form-control-modern" step="0.01" min="100" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Donation Category <span class="required">*</span></label>
                        <select id="ph_category" class="form-select-modern" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Message/Purpose (Optional)</label>
                        <textarea id="ph_notes" class="form-control-modern" rows="2"></textarea>
                    </div>

                    <div class="alert-modern alert-warning-modern mb-3">
                        <i class="bi bi-credit-card"></i>
                        <div>
                            <strong>Sandbox Test Cards:</strong><br>
                            <small>
                                &bull; Visa: 4111 1111 1111 1111 (CVV: any 3 digits, Expiry: any future date)<br>
                                &bull; MasterCard: 5555 5555 5555 4444<br>
                                &bull; Any name can be used
                            </small>
                        </div>
                    </div>

                    <button type="button" class="btn-modern btn-primary-modern btn-lg-modern w-100" onclick="initiatePayHere()">
                        <i class="bi bi-credit-card"></i> Proceed to Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Bank Slip Viewer Modal -->
<div class="modal fade" id="slipViewerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;">
            <div class="modal-header" style="background:linear-gradient(135deg,#c2410c,#f97316);color:#fff;border:none;">
                <h5 class="modal-title"><i class="bi bi-image"></i> <span id="slipModalTitle">Bank Slip</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4" style="background:#f8fafc;">
                <img id="slipImage" src="" alt="Bank Slip" style="max-width:100%;max-height:70vh;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.12);cursor:zoom-in;" onclick="window.open(this.src,'_blank')">
                <p class="text-muted mt-3 mb-0"><small><i class="bi bi-zoom-in"></i> Click image to open full size in new tab</small></p>
            </div>
            <div class="modal-footer" style="border:none;justify-content:center;">
                <a id="slipDownloadLink" href="#" download class="btn-modern btn-primary-modern btn-sm-modern">
                    <i class="bi bi-download"></i> Download Slip
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// AJAX Verify
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.verify-btn');
    if (!btn) return;
    if (!confirm('Verify this donation?')) return;
    
    const donationId = btn.dataset.id;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('ajax_verify', '1');
    formData.append('donation_id', donationId);
    
    fetch('donation_management.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = btn.closest('tr');
            const badge = row.querySelector('[data-status-badge]');
            if (badge) {
                badge.style.background = '#dcfce7';
                badge.style.color = '#16a34a';
                badge.innerHTML = '<span style="width:7px;height:7px;border-radius:50%;background:#16a34a;display:inline-block;"></span> Verified';
            }
            // Replace action buttons with PDF receipt link
            const actionDiv = btn.closest('.d-flex');
            actionDiv.innerHTML = '<a href="generate_receipt.php?id=' + donationId + '" target="_blank" class="btn-modern btn-primary-modern btn-sm-modern" title="Download Receipt"><i class="bi bi-file-earmark-pdf"></i></a>';
        } else {
            alert(data.message || 'Verification failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i>';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle"></i>';
    });
});

// AJAX Reject
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.reject-btn');
    if (!btn) return;
    if (!confirm('Reject this donation?')) return;
    
    const donationId = btn.dataset.id;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    
    const formData = new FormData();
    formData.append('ajax_reject', '1');
    formData.append('donation_id', donationId);
    
    fetch('donation_management.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const row = btn.closest('tr');
            const badge = row.querySelector('[data-status-badge]');
            if (badge) {
                badge.style.background = '#fee2e2';
                badge.style.color = '#dc2626';
                badge.innerHTML = '<span style="width:7px;height:7px;border-radius:50%;background:#dc2626;display:inline-block;"></span> Rejected';
            }
            const actionDiv = btn.closest('.d-flex');
            actionDiv.innerHTML = '<span class="text-muted" style="font-size:12px;">Rejected</span>';
        } else {
            alert(data.message || 'Rejection failed');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-x-circle"></i>';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-x-circle"></i>';
    });
});

function editDonation(donation) {
    document.getElementById('edit_donation_id').value = donation.donation_id;
    document.getElementById('edit_donor_name').value = donation.donor_name;
    document.getElementById('edit_donor_email').value = donation.donor_email || '';
    document.getElementById('edit_donor_phone').value = donation.donor_phone || '';
    document.getElementById('edit_amount').value = donation.amount;
    document.getElementById('edit_category_id').value = donation.category_id;
    document.getElementById('edit_payment_method').value = donation.payment_method;
    document.getElementById('edit_reference_number').value = donation.reference_number || '';
    document.getElementById('edit_status').value = donation.status;
    document.getElementById('edit_notes').value = donation.notes || '';
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function initiatePayHere() {
    // Get form values
    const donor_name = document.getElementById('ph_donor_name').value;
    const donor_email = document.getElementById('ph_donor_email').value;
    const donor_phone = document.getElementById('ph_donor_phone').value;
    const amount = document.getElementById('ph_amount').value;
    const category_id = document.getElementById('ph_category').value;
    const notes = document.getElementById('ph_notes').value;

    // Validate
    if (!donor_name || !donor_email || !donor_phone || !amount || !category_id) {
        alert('Please fill all required fields');
        return;
    }

    if (amount < 100) {
        alert('Minimum donation amount is Rs. 100');
        return;
    }

    // TODO: In production, implement PayHere SDK integration
    // For now, show placeholder message
    alert('PayHere Integration:\n\n' +
          'This will redirect to PayHere payment gateway.\n\n' +
          'Donor: ' + donor_name + '\n' +
          'Amount: Rs. ' + amount + '\n' +
          'Email: ' + donor_email + '\n\n' +
          'SANDBOX MODE - No real payment will be processed.\n\n' +
          'To complete integration:\n' +
          '1. Get PayHere Merchant ID (sandbox)\n' +
          '2. Include PayHere JS SDK\n' +
          '3. Create payment object\n' +
          '4. Handle success/error callbacks\n' +
          '5. Save to database on success');

    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('payhereModal')).hide();
}

// Initialize Advanced Search for Donations
window.addEventListener('load', function() {
    new AdvancedSearch('donations');
    
    // Check if we should auto-open donation modal
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('action') === 'donate') {
        openDonationModal();
        // Remove the URL parameter without reloading the page
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});

function viewSlip(slipPath, donorName) {
    document.getElementById('slipImage').src = slipPath;
    document.getElementById('slipDownloadLink').href = slipPath;
    document.getElementById('slipModalTitle').textContent = 'Bank Slip - ' + donorName;
    new bootstrap.Modal(document.getElementById('slipViewerModal')).show();
}

function openDonationModal() {
    new bootstrap.Modal(document.getElementById('addModal')).show();
}

function validateAmount(input) {
    // Remove any negative values
    if (parseFloat(input.value) < 0) {
        input.value = '';
        input.style.borderColor = '#e74c3c';
        showErrorMessage('Amount cannot be negative');
        return false;
    } else if (parseFloat(input.value) < 100) {
        input.style.borderColor = '#f39c12';
        showErrorMessage('Amount must be at least Rs. 100.00');
        return false;
    } else {
        input.style.borderColor = '#27ae60';
        clearErrorMessage();
        return true;
    }
}

function showErrorMessage(message) {
    // Remove existing error message
    clearErrorMessage();
    
    // Create and show error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'text-danger small mt-1';
    errorDiv.id = 'amount-error';
    errorDiv.textContent = message;
    
    const amountInput = document.querySelector('input[name="amount"]');
    amountInput.parentNode.appendChild(errorDiv);
}

function clearErrorMessage() {
    const existingError = document.getElementById('amount-error');
    if (existingError) {
        existingError.remove();
    }
}

// Form submission validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const amountInput = form.querySelector('input[name="amount"]');
            if (amountInput) {
                const amount = parseFloat(amountInput.value);
                if (amount < 100 || isNaN(amount)) {
                    e.preventDefault();
                    amountInput.focus();
                    validateAmount(amountInput);
                    alert('Please enter a valid amount of at least Rs. 100.00');
                    return false;
                }
            }
        });
    });
});

// Global function for sidebar access
window.openDonationModal = openDonationModal;
</script>

<!-- Advanced Search System -->
<script src="assets/js/advanced-search.js"></script>

</body>
</html>
<?php $conn->close(); ?>
