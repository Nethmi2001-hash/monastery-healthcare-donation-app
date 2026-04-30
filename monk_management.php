<?php
session_start();

// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "monastery_healthcare";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$monkColumns = [];
$colResult = $conn->query("SHOW COLUMNS FROM monks");
if ($colResult) {
    while ($col = $colResult->fetch_assoc()) {
        $monkColumns[$col['Field']] = true;
    }
}

$error = "";
$success = "";

$userRole = $_SESSION['role_name'] ?? 'Admin';
$userName = $_SESSION['username'] ?? '';
$userEmail = $_SESSION['email'] ?? '';
$isDoctor = ($userRole === 'Doctor');
$canManageMonks = !$isDoctor;

$currentDoctorId = null;
if ($isDoctor) {
    if (!empty($userEmail)) {
        $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE status='active' AND email = ? LIMIT 1");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $currentDoctorId = (int)$res->fetch_assoc()['doctor_id'];
        }
        $stmt->close();
    }

    if (!$currentDoctorId) {
        $stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE status='active' AND (full_name = ? OR REPLACE(LOWER(full_name), ' ', '') = REPLACE(LOWER(?), ' ', '') OR LOWER(full_name) LIKE LOWER(?)) ORDER BY CASE WHEN full_name = ? THEN 0 ELSE 1 END, doctor_id ASC LIMIT 1");
        $searchName = "%{$userName}%";
        $stmt->bind_param("ssss", $userName, $userName, $searchName, $userName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $currentDoctorId = (int)$res->fetch_assoc()['doctor_id'];
        }
        $stmt->close();
    }

    if (!$currentDoctorId && empty($error)) {
        $error = "Your account is not linked to an active doctor profile.";
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    if (!$canManageMonks) {
        http_response_code(403);
        $error = "Doctors can only view their own patient records.";
    } else {

    if ($form_name === 'create') {
        $full_name = trim($_POST['full_name']);
        $title_id = !empty($_POST['title_id']) ? intval($_POST['title_id']) : null;
        $ordination_date = !empty($_POST['ordination_date']) ? $_POST['ordination_date'] : null;
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $phone = trim($_POST['phone'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $blood_group = trim($_POST['blood_group'] ?? '');
        $allergies = trim($_POST['allergies'] ?? '');
        $chronic_conditions = trim($_POST['chronic_conditions'] ?? '');
        $current_medications = trim($_POST['current_medications'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($full_name)) {
            $error = "Monk name is required.";
        } else {
            // Keep compatibility with both old and new monk schemas.
            $final_notes = $notes;
            if (empty($monkColumns['current_medications']) && !empty($current_medications)) {
                $final_notes = trim($final_notes . (empty($final_notes) ? '' : "\n") . "Current Medications: " . $current_medications);
            }

            $dob_value = !empty($monkColumns['dob']) ? $birth_date : null;
            $stmt = $conn->prepare("INSERT INTO monks (full_name, title_id, dob, phone, emergency_contact, blood_group, allergies, chronic_conditions, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissssssss", $full_name, $title_id, $dob_value, $phone, $emergency_contact, $blood_group, $allergies, $chronic_conditions, $final_notes, $status);
            
            if ($stmt->execute()) {
                $success = "Monk added successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($form_name === 'update') {
        $monk_id = intval($_POST['monk_id']);
        $full_name = trim($_POST['full_name']);
        $title_id = !empty($_POST['title_id']) ? intval($_POST['title_id']) : null;
        $ordination_date = !empty($_POST['ordination_date']) ? $_POST['ordination_date'] : null;
        $birth_date = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
        $phone = trim($_POST['phone'] ?? '');
        $emergency_contact = trim($_POST['emergency_contact'] ?? '');
        $blood_group = trim($_POST['blood_group'] ?? '');
        $allergies = trim($_POST['allergies'] ?? '');
        $chronic_conditions = trim($_POST['chronic_conditions'] ?? '');
        $current_medications = trim($_POST['current_medications'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'];

        $final_notes = $notes;
        if (empty($monkColumns['current_medications']) && !empty($current_medications)) {
            $final_notes = trim($final_notes . (empty($final_notes) ? '' : "\n") . "Current Medications: " . $current_medications);
        }

        $dob_value = !empty($monkColumns['dob']) ? $birth_date : null;
        $stmt = $conn->prepare("UPDATE monks SET full_name=?, title_id=?, dob=?, phone=?, emergency_contact=?, blood_group=?, allergies=?, chronic_conditions=?, notes=?, status=? WHERE monk_id=?");
        $stmt->bind_param("sissssssssi", $full_name, $title_id, $dob_value, $phone, $emergency_contact, $blood_group, $allergies, $chronic_conditions, $final_notes, $status, $monk_id);
        
        if ($stmt->execute()) {
            $success = "Monk updated successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    if ($form_name === 'delete') {
        $monk_id = intval($_POST['monk_id']);
        $stmt = $conn->prepare("DELETE FROM monks WHERE monk_id=?");
        $stmt->bind_param("i", $monk_id);
        
        if ($stmt->execute()) {
            $success = "Monk deleted successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    }
}

// Get titles for dropdown
$titles = [];
$title_result = $conn->query("SELECT * FROM titles ORDER BY title_name ASC");
if ($title_result) {
    while ($row = $title_result->fetch_assoc()) {
        $titles[] = $row;
    }
}

// Get all monks with title info
$monks = [];
if ($isDoctor && $currentDoctorId) {
    $result = $conn->query("
        SELECT DISTINCT m.*, t.title_name 
        FROM monks m 
        JOIN appointments a ON a.monk_id = m.monk_id
        LEFT JOIN titles t ON m.title_id = t.title_id 
        WHERE a.doctor_id = " . (int)$currentDoctorId . "
        ORDER BY m.full_name ASC
    ");
} elseif ($isDoctor) {
    $result = $conn->query("SELECT m.*, t.title_name FROM monks m LEFT JOIN titles t ON m.title_id = t.title_id WHERE 1 = 0");
} else {
    $result = $conn->query("
        SELECT m.*, t.title_name 
        FROM monks m 
        LEFT JOIN titles t ON m.title_id = t.title_id 
        ORDER BY m.full_name ASC
    ");
}
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!array_key_exists('birth_date', $row)) {
            $row['birth_date'] = $row['dob'] ?? '';
        }
        if (!array_key_exists('ordination_date', $row)) {
            $row['ordination_date'] = '';
        }
        if (!array_key_exists('current_medications', $row)) {
            $row['current_medications'] = '';
        }
        $monks[] = $row;
    }
}

// Get statistics
$stats = [];
if ($isDoctor && $currentDoctorId) {
    $stats['total'] = $conn->query("SELECT COUNT(DISTINCT m.monk_id) as count FROM monks m JOIN appointments a ON a.monk_id = m.monk_id WHERE a.doctor_id = " . (int)$currentDoctorId)->fetch_assoc()['count'];
    $stats['active'] = $conn->query("SELECT COUNT(DISTINCT m.monk_id) as count FROM monks m JOIN appointments a ON a.monk_id = m.monk_id WHERE a.doctor_id = " . (int)$currentDoctorId . " AND m.status='active'")->fetch_assoc()['count'];
    $stats['inactive'] = $conn->query("SELECT COUNT(DISTINCT m.monk_id) as count FROM monks m JOIN appointments a ON a.monk_id = m.monk_id WHERE a.doctor_id = " . (int)$currentDoctorId . " AND m.status='inactive'")->fetch_assoc()['count'];
} elseif ($isDoctor) {
    $stats['total'] = 0;
    $stats['active'] = 0;
    $stats['inactive'] = 0;
} else {
    $stats['total'] = $conn->query("SELECT COUNT(*) as count FROM monks")->fetch_assoc()['count'];
    $stats['active'] = $conn->query("SELECT COUNT(*) as count FROM monks WHERE status='active'")->fetch_assoc()['count'];
    $stats['inactive'] = $conn->query("SELECT COUNT(*) as count FROM monks WHERE status='inactive'")->fetch_assoc()['count'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monk Management - Monastery System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>

<?php include 'navbar.php'; ?>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i>
            <span><?= $error ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i>
            <span><?= $success ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="stat-card" style="--stat-color: var(--primary-500);">
                <div class="stat-icon emerald"><i class="bi bi-people-fill"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Monks</div>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="stat-card" style="--stat-color: #f97316;">
                <div class="stat-icon blue"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Active Monks</div>
                    <div class="stat-value"><?= $stats['active'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="stat-card" style="--stat-color: #64748b;">
                <div class="stat-icon slate"><i class="bi bi-pause-circle-fill"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Inactive Monks</div>
                    <div class="stat-value"><?= $stats['inactive'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monks Table -->
    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <h5><i class="bi bi-person-hearts me-2"></i><?= $isDoctor ? 'My Patients' : 'Monk Records' ?></h5>
            <div style="display:flex;gap:10px;align-items:center;">
                <?php if ($canManageMonks): ?>
                <button class="btn-modern btn-primary-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="bi bi-plus-circle"></i> Add Monk
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Advanced Search Section -->
        <div id="advanced-search" data-type="monks" style="padding:0 24px;"></div>

        <div class="table-responsive-modern">
            <table class="modern-table" id="monks-list">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Ordination</th>
                        <th>Blood Group</th>
                        <th>Medical Info</th>
                        <th>Status</th>
                        <?php if ($canManageMonks): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($monks) > 0): ?>
                        <?php foreach ($monks as $monk): ?>
                            <tr>
                                <td><?= $monk['monk_id'] ?></td>
                                <td>
                                    <div style="font-weight:600;">
                                        <?php if ($monk['title_name']): ?>
                                            <span class="badge-modern badge-primary"><?= htmlspecialchars($monk['title_name']) ?></span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($monk['full_name']) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($monk['phone']): ?>
                                        <div style="font-size:13px;"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($monk['phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($monk['emergency_contact'])): ?>
                                        <div style="font-size:11.5px;color:var(--text-secondary);margin-top:2px;"><i class="bi bi-shield-plus me-1"></i><?= htmlspecialchars($monk['emergency_contact']) ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($monk['phone']) && empty($monk['emergency_contact'])): ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($monk['ordination_date'])): ?>
                                        <?= date('M d, Y', strtotime($monk['ordination_date'])) ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($monk['blood_group']): ?>
                                        <span class="badge-modern badge-danger badge-dot"><?= htmlspecialchars($monk['blood_group']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $has_medical = !empty($monk['allergies']) || !empty($monk['chronic_conditions']) || !empty($monk['current_medications']);
                                    if ($has_medical):
                                        $medical_parts = [];
                                        if (!empty($monk['allergies'])) $medical_parts[] = '<b>Allergies:</b> ' . htmlspecialchars($monk['allergies']);
                                        if (!empty($monk['chronic_conditions'])) $medical_parts[] = '<b>Conditions:</b> ' . htmlspecialchars($monk['chronic_conditions']);
                                        if (!empty($monk['current_medications'])) $medical_parts[] = '<b>Medications:</b> ' . htmlspecialchars($monk['current_medications']);
                                        $medical_html = implode('<br>', $medical_parts);
                                    ?>
                                        <button type="button" class="btn-icon" data-bs-toggle="popover" data-bs-html="true" data-bs-trigger="hover focus" title="Medical Information" data-bs-content="<?= htmlspecialchars($medical_html) ?>">
                                            <i class="bi bi-heart-pulse" style="color:var(--danger);"></i>
                                        </button>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $status_badge = $monk['status'] == 'active' ? 'badge-success' : 'badge-neutral'; ?>
                                    <span class="badge-modern <?= $status_badge ?> badge-dot"><?= ucfirst($monk['status']) ?></span>
                                </td>
                                <?php if ($canManageMonks): ?>
                                <td>
                                    <div class="table-actions" style="display:flex;gap:6px;">
                                        <button class="btn-icon" onclick="editMonk(<?= htmlspecialchars(json_encode($monk)) ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this monk record?')">
                                            <input type="hidden" name="form_name" value="delete">
                                            <input type="hidden" name="monk_id" value="<?= $monk['monk_id'] ?>">
                                            <button type="submit" class="btn-icon danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $canManageMonks ? '8' : '7' ?>" style="text-align:center;padding:48px 20px;">
                                <div style="color:var(--text-secondary);">
                                    <i class="bi bi-person-hearts" style="font-size:36px;display:block;margin-bottom:12px;opacity:0.4;"></i>
                                    <?= $isDoctor ? 'No patient records found for your appointments yet.' : 'No monk records found. Click "Add Monk" to get started.' ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($canManageMonks): ?>
    <!-- Add Monk Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Monk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="form_name" value="create">

                        <h6 style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:16px;">Basic Information</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Title</label>
                                    <select name="title_id" class="form-control-modern form-select-modern">
                                        <option value="">-- No Title --</option>
                                        <?php foreach ($titles as $title): ?>
                                            <option value="<?= $title['title_id'] ?>"><?= htmlspecialchars($title['title_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Full Name <span class="required">*</span></label>
                                    <input type="text" name="full_name" class="form-control-modern" placeholder="Enter monk's full name" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Ordination Date</label>
                                    <input type="date" name="ordination_date" class="form-control-modern">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Birth Date</label>
                                    <input type="date" name="birth_date" class="form-control-modern">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Phone</label>
                                    <input type="text" name="phone" class="form-control-modern" placeholder="+94 77 123 4567">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Emergency Contact</label>
                                    <input type="text" name="emergency_contact" class="form-control-modern" placeholder="Name & Phone">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Blood Group</label>
                                    <select name="blood_group" class="form-control-modern form-select-modern">
                                        <option value="">-- Select --</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Status</label>
                                    <select name="status" class="form-control-modern form-select-modern">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr style="border-color:var(--border-color);margin:24px 0;">
                        <h6 style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:16px;"><i class="bi bi-heart-pulse me-1"></i>Medical History</h6>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Allergies</label>
                            <textarea name="allergies" class="form-control-modern" rows="2" placeholder="e.g., Penicillin, Peanuts, etc."></textarea>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Chronic Conditions</label>
                            <textarea name="chronic_conditions" class="form-control-modern" rows="2" placeholder="e.g., Diabetes, Hypertension, Asthma, etc."></textarea>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Current Medications</label>
                            <textarea name="current_medications" class="form-control-modern" rows="2" placeholder="List current medications and dosages"></textarea>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Additional Notes</label>
                            <textarea name="notes" class="form-control-modern" rows="2" placeholder="Any other important medical or personal information"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modern btn-primary-modern">
                            <i class="bi bi-plus-circle"></i> Add Monk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Monk Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Monk</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="form_name" value="update">
                        <input type="hidden" name="monk_id" id="edit_monk_id">

                        <h6 style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:16px;">Basic Information</h6>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Title</label>
                                    <select name="title_id" id="edit_title_id" class="form-control-modern form-select-modern">
                                        <option value="">-- No Title --</option>
                                        <?php foreach ($titles as $title): ?>
                                            <option value="<?= $title['title_id'] ?>"><?= htmlspecialchars($title['title_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Full Name <span class="required">*</span></label>
                                    <input type="text" name="full_name" id="edit_full_name" class="form-control-modern" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Ordination Date</label>
                                    <input type="date" name="ordination_date" id="edit_ordination_date" class="form-control-modern">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Birth Date</label>
                                    <input type="date" name="birth_date" id="edit_birth_date" class="form-control-modern">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Phone</label>
                                    <input type="text" name="phone" id="edit_phone" class="form-control-modern">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Emergency Contact</label>
                                    <input type="text" name="emergency_contact" id="edit_emergency_contact" class="form-control-modern">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Blood Group</label>
                                    <select name="blood_group" id="edit_blood_group" class="form-control-modern form-select-modern">
                                        <option value="">-- Select --</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Status</label>
                                    <select name="status" id="edit_status" class="form-control-modern form-select-modern">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr style="border-color:var(--border-color);margin:24px 0;">
                        <h6 style="font-size:13px;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:16px;"><i class="bi bi-heart-pulse me-1"></i>Medical History</h6>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Allergies</label>
                            <textarea name="allergies" id="edit_allergies" class="form-control-modern" rows="2"></textarea>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Chronic Conditions</label>
                            <textarea name="chronic_conditions" id="edit_chronic_conditions" class="form-control-modern" rows="2"></textarea>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Current Medications</label>
                            <textarea name="current_medications" id="edit_current_medications" class="form-control-modern" rows="2"></textarea>
                        </div>

                        <div class="form-group-modern">
                            <label class="form-label-modern">Additional Notes</label>
                            <textarea name="notes" id="edit_notes" class="form-control-modern" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modern btn-primary-modern">
                            <i class="bi bi-save"></i> Update Monk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/advanced-search.js"></script>
<script>
    function editMonk(monk) {
        document.getElementById('edit_monk_id').value = monk.monk_id;
        document.getElementById('edit_full_name').value = monk.full_name;
        document.getElementById('edit_title_id').value = monk.title_id || '';
        document.getElementById('edit_ordination_date').value = monk.ordination_date || '';
        document.getElementById('edit_birth_date').value = monk.birth_date || '';
        document.getElementById('edit_phone').value = monk.phone || '';
        document.getElementById('edit_emergency_contact').value = monk.emergency_contact || '';
        document.getElementById('edit_blood_group').value = monk.blood_group || '';
        document.getElementById('edit_allergies').value = monk.allergies || '';
        document.getElementById('edit_chronic_conditions').value = monk.chronic_conditions || '';
        document.getElementById('edit_current_medications').value = monk.current_medications || '';
        document.getElementById('edit_notes').value = monk.notes || '';
        document.getElementById('edit_status').value = monk.status;

        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // Initialize popovers for medical info
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function(el) {
        new bootstrap.Popover(el);
    });

    // Initialize Advanced Search for Monks
    window.addEventListener('load', function() {
        new AdvancedSearch('monks');
    });
</script>
</body>
</html>
