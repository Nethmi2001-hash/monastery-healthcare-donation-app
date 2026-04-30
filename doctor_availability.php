<?php
session_start();

if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$dbusername = "root";
$db_password = "";
$dbname = "monastery_healthcare";

$con = new mysqli($servername, $dbusername, $db_password, $dbname);
if ($con->connect_error) die("Connection failed: " . $con->connect_error);

$error = "";
$success = "";

$userRole = $_SESSION['role_name'] ?? 'Admin';
$userName = $_SESSION['username'] ?? '';
$userEmail = $_SESSION['email'] ?? '';
$isDoctor = ($userRole === 'Doctor');
$canManageAllDoctors = ($userRole === 'Admin');

$currentDoctorId = null;
if ($isDoctor) {
    if (!empty($userEmail)) {
        $stmt = $con->prepare("SELECT doctor_id FROM doctors WHERE status='active' AND email = ? LIMIT 1");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $currentDoctorId = (int)$res->fetch_assoc()['doctor_id'];
        }
        $stmt->close();
    }

    if (!$currentDoctorId) {
        $stmt = $con->prepare("SELECT doctor_id FROM doctors WHERE status='active' AND (full_name = ? OR REPLACE(LOWER(full_name), ' ', '') = REPLACE(LOWER(?), ' ', '') OR LOWER(full_name) LIKE LOWER(?)) ORDER BY CASE WHEN full_name = ? THEN 0 ELSE 1 END, doctor_id ASC LIMIT 1");
        $searchName = "%{$userName}%";
        $stmt->bind_param("ssss", $userName, $userName, $searchName, $userName);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $currentDoctorId = (int)$res->fetch_assoc()['doctor_id'];
        }
        $stmt->close();
    }
}

$days_map = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Handle CREATE/UPDATE/DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    if ($form_name === 'create') {
        $doctor_id = $isDoctor ? (int)$currentDoctorId : intval($_POST['doctor_id']);
        $day_of_week = intval($_POST['day_of_week']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];

        if ($doctor_id <= 0) {
            $error = "Your doctor profile is not linked. Please contact admin.";
        } else {
            $stmt = $con->prepare("INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("iiss", $doctor_id, $day_of_week, $start_time, $end_time);
            if ($stmt->execute()) {
                $success = "Availability added successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($form_name === 'update') {
        $avail_id = intval($_POST['avail_id']);
        $doctor_id = $isDoctor ? (int)$currentDoctorId : intval($_POST['doctor_id']);
        $day_of_week = intval($_POST['day_of_week']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($doctor_id <= 0) {
            $error = "Your doctor profile is not linked. Please contact admin.";
        } elseif ($isDoctor) {
            $stmt = $con->prepare("UPDATE doctor_availability SET doctor_id=?, day_of_week=?, start_time=?, end_time=?, is_active=? WHERE avail_id=? AND doctor_id=?");
            $stmt->bind_param("iissiii", $doctor_id, $day_of_week, $start_time, $end_time, $is_active, $avail_id, $doctor_id);
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 0) {
                    $error = "You can update only your own availability records.";
                } else {
                    $success = "Availability updated successfully!";
                }
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $stmt = $con->prepare("UPDATE doctor_availability SET doctor_id=?, day_of_week=?, start_time=?, end_time=?, is_active=? WHERE avail_id=?");
            $stmt->bind_param("iissii", $doctor_id, $day_of_week, $start_time, $end_time, $is_active, $avail_id);
            if ($stmt->execute()) {
                $success = "Availability updated successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($form_name === 'delete') {
        $avail_id = intval($_POST['avail_id']);

        if ($isDoctor && (int)$currentDoctorId <= 0) {
            $error = "Your doctor profile is not linked. Please contact admin.";
        } elseif ($isDoctor) {
            $stmt = $con->prepare("DELETE FROM doctor_availability WHERE avail_id=? AND doctor_id=?");
            $stmt->bind_param("ii", $avail_id, $currentDoctorId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 0) {
                    $error = "You can delete only your own availability records.";
                } else {
                    $success = "Availability deleted successfully!";
                }
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $stmt = $con->prepare("DELETE FROM doctor_availability WHERE avail_id=?");
            $stmt->bind_param("i", $avail_id);
            if ($stmt->execute()) {
                $success = "Availability deleted successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all availabilities
$avail_query = "SELECT da.*, d.full_name, d.specialization 
                FROM doctor_availability da 
                JOIN doctors d ON da.doctor_id = d.doctor_id";
if ($isDoctor) {
    if ($currentDoctorId) {
        $avail_query .= " WHERE da.doctor_id = " . (int)$currentDoctorId;
    } else {
        $avail_query .= " WHERE 1 = 0";
        if (!$error) {
            $error = "Your account is not linked to an active doctor profile.";
        }
    }
}
$avail_query .= " ORDER BY d.full_name, da.day_of_week, da.start_time";
$avail_res = $con->query($avail_query);

// Fetch doctors for dropdown
$doctors_query = "SELECT doctor_id, full_name, specialization FROM doctors WHERE status='active'";
if ($isDoctor && $currentDoctorId) {
    $doctors_query .= " AND doctor_id = " . (int)$currentDoctorId;
}
$doctors_query .= " ORDER BY full_name ASC";
$doctors_res = $con->query($doctors_query);
$doctors = [];
while ($d = $doctors_res->fetch_assoc()) {
    $doctors[] = $d;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Availability - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<?php include 'navbar.php'; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-calendar-week"></i> <?= $isDoctor ? 'My Availability' : 'Doctor Availability Management' ?></h2>
            <p class="text-muted mb-0"><?= $isDoctor ? 'Manage your own weekly schedule' : 'Manage weekly schedules for doctors' ?></p>
        </div>
        <div class="mt-2 mt-md-0">
            <button class="btn-modern btn-primary-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> Add New Schedule
            </button>
        </div>
    </div>

    <?php if($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Availability Cards -->
    <div class="row">
        <?php 
        $current_doctor = null;
        while($row = $avail_res->fetch_assoc()): 
            if ($current_doctor !== $row['doctor_id']) {
                if ($current_doctor !== null) {
                    echo '</tbody></table></div></div></div>';
                }
                $current_doctor = $row['doctor_id'];
        ?>
            <div class="col-md-6 mb-4">
                <div class="modern-table-wrapper">
                    <div class="modern-table-header">
                        <div>
                            <h6 class="mb-1"><i class="bi bi-person-badge"></i> <?= htmlspecialchars($row['full_name']) ?></h6>
                            <span class="badge-modern badge-primary"><?= htmlspecialchars($row['specialization']) ?></span>
                        </div>
                    </div>
                    <div class="table-responsive-modern">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
        <?php } ?>
                                <tr>
                                    <td>
                                        <span class="badge-modern badge-primary">
                                            <?= $days_map[$row['day_of_week']] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <i class="bi bi-clock"></i> 
                                        <?= date('g:i A', strtotime($row['start_time'])) ?> - 
                                        <?= date('g:i A', strtotime($row['end_time'])) ?>
                                    </td>
                                    <td>
                                        <?php if($row['is_active']): ?>
                                            <span class="badge-modern badge-success badge-dot">Active</span>
                                        <?php else: ?>
                                            <span class="badge-modern badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['avail_id'] ?>" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn-icon danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['avail_id'] ?>" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?= $row['avail_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Availability</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="form_name" value="update">
                                            <input type="hidden" name="avail_id" value="<?= $row['avail_id'] ?>">
                                            
                                            <div class="form-group-modern">
                                                <label class="form-label-modern">Doctor</label>
                                                <?php if ($isDoctor): ?>
                                                    <input type="hidden" name="doctor_id" value="<?= (int)$row['doctor_id'] ?>">
                                                    <input type="text" class="form-control-modern" value="<?= htmlspecialchars($row['full_name']) ?> - <?= htmlspecialchars($row['specialization']) ?>" readonly>
                                                <?php else: ?>
                                                    <select name="doctor_id" class="form-select-modern" required>
                                                        <?php foreach($doctors as $doc): ?>
                                                            <option value="<?= $doc['doctor_id'] ?>" <?= $doc['doctor_id'] == $row['doctor_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($doc['full_name']) ?> - <?= $doc['specialization'] ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                            </div>

                                            <div class="form-group-modern">
                                                <label class="form-label-modern">Day of Week</label>
                                                <select name="day_of_week" class="form-select-modern" required>
                                                    <?php foreach($days_map as $idx => $day_name): ?>
                                                        <option value="<?= $idx ?>" <?= $idx == $row['day_of_week'] ? 'selected' : '' ?>><?= $day_name ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group-modern">
                                                        <label class="form-label-modern">Start Time</label>
                                                        <input type="time" name="start_time" class="form-control-modern" value="<?= $row['start_time'] ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group-modern">
                                                        <label class="form-label-modern">End Time</label>
                                                        <input type="time" name="end_time" class="form-control-modern" value="<?= $row['end_time'] ?>" required>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="form-check mt-2">
                                                <input type="checkbox" name="is_active" class="form-check-input" id="active<?= $row['avail_id'] ?>" <?= $row['is_active'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="active<?= $row['avail_id'] ?>">Active</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn-modern btn-primary-modern">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteModal<?= $row['avail_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="bi bi-trash"></i> Confirm Delete</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete this availability?</p>
                                            <p><strong><?= htmlspecialchars($row['full_name']) ?></strong> - 
                                            <?= $days_map[$row['day_of_week']] ?> 
                                            (<?= date('g:i A', strtotime($row['start_time'])) ?> - <?= date('g:i A', strtotime($row['end_time'])) ?>)</p>
                                            <input type="hidden" name="form_name" value="delete">
                                            <input type="hidden" name="avail_id" value="<?= $row['avail_id'] ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn-modern btn-primary-modern">Yes, Delete</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

        <?php endwhile; ?>
        <?php if ($current_doctor !== null): ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($avail_res->num_rows == 0): ?>
        <div class="alert-modern alert-info-modern">
            <i class="bi bi-info-circle"></i> No availability schedules found. Click "Add New Schedule" to create one.
        </div>
    <?php endif; ?>

<!-- Add New Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Availability</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="form_name" value="create">
                    
                    <div class="form-group-modern">
                        <label class="form-label-modern">Doctor</label>
                        <?php if ($isDoctor): ?>
                            <input type="hidden" name="doctor_id" value="<?= (int)$currentDoctorId ?>">
                            <input type="text" class="form-control-modern" value="<?= isset($doctors[0]) ? htmlspecialchars($doctors[0]['full_name'] . ' - ' . $doctors[0]['specialization']) : 'Not linked' ?>" readonly>
                        <?php else: ?>
                            <select name="doctor_id" class="form-select-modern" required>
                                <option value="">-- Select Doctor --</option>
                                <?php foreach($doctors as $doc): ?>
                                    <option value="<?= $doc['doctor_id'] ?>">
                                        <?= htmlspecialchars($doc['full_name']) ?> - <?= $doc['specialization'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Day of Week</label>
                        <select name="day_of_week" class="form-select-modern" required>
                            <option value="">-- Select Day --</option>
                            <?php foreach($days_map as $idx => $day_name): ?>
                                <option value="<?= $idx ?>"><?= $day_name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Start Time</label>
                                <input type="time" name="start_time" class="form-control-modern" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">End Time</label>
                                <input type="time" name="end_time" class="form-control-modern" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert-modern alert-info-modern">
                        <i class="bi bi-info-circle"></i> <small>Times are in 24-hour format. Schedule will be set as active by default.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">Add Availability</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
