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

// Some databases use doctors.contact while others use doctors.phone.
$doctorPhoneColumn = 'phone';
$colRes = $conn->query("SHOW COLUMNS FROM doctors LIKE 'phone'");
if (!$colRes || $colRes->num_rows === 0) {
    $doctorPhoneColumn = 'contact';
}

$error = "";
$success = "";

$userRole = $_SESSION['role_name'] ?? 'Admin';
$isMonk = ($userRole === 'Monk');
$specialization_options = ['General', 'Ayurvedic', 'Western'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    if ($form_name === 'create') {
        if ($isMonk) {
            http_response_code(403);
            $error = "Monk users cannot add doctors.";
        } else {
        $full_name = trim($_POST['full_name']);
        $specialization = trim($_POST['specialization']);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($full_name) || empty($specialization)) {
            $error = "Doctor name and specialization are required.";
        } elseif (!in_array($specialization, $specialization_options, true)) {
            $error = "Please select a valid specialization.";
        } else {
            $stmt = $conn->prepare("INSERT INTO doctors (full_name, specialization, {$doctorPhoneColumn}, email, license_number, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $full_name, $specialization, $phone, $email, $license_number, $status);
            
            if ($stmt->execute()) {
                $success = "Doctor added successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        }
    }

    if ($form_name === 'update') {
        $doctor_id = intval($_POST['doctor_id']);
        $full_name = trim($_POST['full_name']);
        $specialization = trim($_POST['specialization']);
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $license_number = trim($_POST['license_number'] ?? '');
        $status = $_POST['status'];

        if (!in_array($specialization, $specialization_options, true)) {
            $error = "Please select a valid specialization.";
        } else {
            $stmt = $conn->prepare("UPDATE doctors SET full_name=?, specialization=?, {$doctorPhoneColumn}=?, email=?, license_number=?, status=? WHERE doctor_id=?");
            $stmt->bind_param("ssssssi", $full_name, $specialization, $phone, $email, $license_number, $status, $doctor_id);
            
            if ($stmt->execute()) {
                $success = "Doctor updated successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($form_name === 'delete') {
        $doctor_id = intval($_POST['doctor_id']);
        $stmt = $conn->prepare("DELETE FROM doctors WHERE doctor_id=?");
        $stmt->bind_param("i", $doctor_id);
        
        if ($stmt->execute()) {
            $success = "Doctor deleted successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get all doctors
$doctors = [];
$result = $conn->query("SELECT * FROM doctors ORDER BY full_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if (!array_key_exists('phone', $row)) {
            $row['phone'] = $row['contact'] ?? '';
        }
        $doctors[] = $row;
    }
}

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM doctors")->fetch_assoc()['count'];
$stats['active'] = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status='active'")->fetch_assoc()['count'];
$stats['inactive'] = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status='inactive'")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Management - Seela suwa herath</title>
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

    <!-- Statistics -->
    <div class="row g-4 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-person-badge-fill"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Doctors</div>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon emerald"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Active Doctors</div>
                    <div class="stat-value"><?= $stats['active'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-md-6">
            <div class="stat-card">
                <div class="stat-icon slate"><i class="bi bi-pause-circle-fill"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Inactive Doctors</div>
                    <div class="stat-value"><?= $stats['inactive'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Doctors Table -->
    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <h5><i class="bi bi-person-badge me-2"></i>Doctor Records</h5>
            <?php if (!$isMonk): ?>
            <button class="btn-modern btn-primary-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> Add Doctor
            </button>
            <?php endif; ?>
        </div>
        <div class="table-responsive-modern">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Specialization</th>
                        <th>Contact</th>
                        <th>License</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($doctors) > 0): ?>
                        <?php foreach ($doctors as $doctor): ?>
                            <tr>
                                <td><?= $doctor['doctor_id'] ?></td>
                                <td><div style="font-weight:600;"><?= htmlspecialchars($doctor['full_name']) ?></div></td>
                                <td><span class="badge-modern badge-primary"><?= htmlspecialchars($doctor['specialization']) ?></span></td>
                                <td>
                                    <?php if ($doctor['phone']): ?>
                                        <div style="font-size:13px;"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($doctor['phone']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($doctor['email']): ?>
                                        <div style="font-size:12px;color:var(--text-secondary);margin-top:2px;"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($doctor['email']) ?></div>
                                    <?php endif; ?>
                                    <?php if (empty($doctor['phone']) && empty($doctor['email'])): ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($doctor['license_number']): ?>
                                        <span style="font-size:13px;"><i class="bi bi-award me-1"></i><?= htmlspecialchars($doctor['license_number']) ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge-modern <?= $doctor['status'] == 'active' ? 'badge-success' : 'badge-neutral' ?> badge-dot"><?= ucfirst($doctor['status']) ?></span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <button class="btn-icon" onclick="editDoctor(<?= htmlspecialchars(json_encode($doctor)) ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this doctor?')">
                                            <input type="hidden" name="form_name" value="delete">
                                            <input type="hidden" name="doctor_id" value="<?= $doctor['doctor_id'] ?>">
                                            <button type="submit" class="btn-icon danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:48px 20px;">
                                <div style="color:var(--text-secondary);">
                                    <i class="bi bi-person-badge" style="font-size:36px;display:block;margin-bottom:12px;opacity:0.4;"></i>
                                    No doctors found. Click "Add Doctor" to get started.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!$isMonk): ?>
    <!-- Add Doctor Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Doctor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="form_name" value="create">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Full Name <span class="required">*</span></label>
                                    <input type="text" name="full_name" class="form-control-modern" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Specialization <span class="required">*</span></label>
                                    <select name="specialization" class="form-control-modern form-select-modern" required>
                                        <option value="">-- Select Specialization --</option>
                                        <?php foreach ($specialization_options as $spec): ?>
                                            <option value="<?= htmlspecialchars($spec) ?>"><?= htmlspecialchars($spec) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Phone</label>
                                    <input type="text" name="phone" class="form-control-modern" placeholder="+94 77 123 4567">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Email</label>
                                    <input type="email" name="email" class="form-control-modern" placeholder="doctor@example.com">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">License Number</label>
                                    <input type="text" name="license_number" class="form-control-modern" placeholder="Medical License #">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Status</label>
                                    <select name="status" class="form-control-modern form-select-modern">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-plus-circle"></i> Add Doctor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Doctor Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit Doctor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="form_name" value="update">
                        <input type="hidden" name="doctor_id" id="edit_doctor_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Full Name <span class="required">*</span></label>
                                    <input type="text" name="full_name" id="edit_full_name" class="form-control-modern" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Specialization <span class="required">*</span></label>
                                    <select name="specialization" id="edit_specialization" class="form-control-modern form-select-modern" required>
                                        <option value="">-- Select Specialization --</option>
                                        <?php foreach ($specialization_options as $spec): ?>
                                            <option value="<?= htmlspecialchars($spec) ?>"><?= htmlspecialchars($spec) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Phone</label>
                                    <input type="text" name="phone" id="edit_phone" class="form-control-modern">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Email</label>
                                    <input type="email" name="email" id="edit_email" class="form-control-modern">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">License Number</label>
                                    <input type="text" name="license_number" id="edit_license_number" class="form-control-modern">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">Status</label>
                                    <select name="status" id="edit_status" class="form-control-modern form-select-modern">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-save"></i> Update Doctor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function editDoctor(doctor) {
        document.getElementById('edit_doctor_id').value = doctor.doctor_id;
        document.getElementById('edit_full_name').value = doctor.full_name;
        const specSelect = document.getElementById('edit_specialization');
        const hasOption = Array.from(specSelect.options).some(opt => opt.value === doctor.specialization);
        if (!hasOption && doctor.specialization) {
            const opt = document.createElement('option');
            opt.value = doctor.specialization;
            opt.textContent = doctor.specialization;
            specSelect.appendChild(opt);
        }
        specSelect.value = doctor.specialization || '';
        document.getElementById('edit_phone').value = doctor.phone || '';
        document.getElementById('edit_email').value = doctor.email || '';
        document.getElementById('edit_license_number').value = doctor.license_number || '';
        document.getElementById('edit_status').value = doctor.status;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
</script>
</body>
</html>
