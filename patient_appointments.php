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

// Appointment requests are used for monk -> admin/doctor assignment flow.
$con->query("CREATE TABLE IF NOT EXISTS appointment_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    monk_id INT NOT NULL,
    preferred_doctor_id INT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME NULL,
    request_notes TEXT,
    status ENUM('pending','assigned','rejected') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    linked_app_id INT NULL,
    FOREIGN KEY (monk_id) REFERENCES monks(monk_id) ON DELETE CASCADE,
    FOREIGN KEY (preferred_doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (linked_app_id) REFERENCES appointments(app_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_preferred_date (preferred_date),
    INDEX idx_monk (monk_id),
    INDEX idx_preferred_doctor (preferred_doctor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Backward-compatible migration in case appointment_requests existed before preferred_doctor_id was added.
$doctorColRes = $con->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_requests' AND COLUMN_NAME = 'preferred_doctor_id'");
if ($doctorColRes && (int)$doctorColRes->fetch_assoc()['c'] === 0) {
    $con->query("ALTER TABLE appointment_requests ADD COLUMN preferred_doctor_id INT NULL AFTER monk_id");
    $con->query("ALTER TABLE appointment_requests ADD INDEX idx_preferred_doctor (preferred_doctor_id)");
    $fkRes = $con->query("SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_requests' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_appointment_requests_preferred_doctor'");
    if ($fkRes && (int)$fkRes->fetch_assoc()['c'] === 0) {
        $con->query("ALTER TABLE appointment_requests ADD CONSTRAINT fk_appointment_requests_preferred_doctor FOREIGN KEY (preferred_doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL");
    }
}

// Backward-compatible migration: preferred_time is optional now.
$timeNullableRes = $con->query("SELECT IS_NULLABLE AS v FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_requests' AND COLUMN_NAME = 'preferred_time' LIMIT 1");
if ($timeNullableRes) {
    $row = $timeNullableRes->fetch_assoc();
    if ($row && strtoupper($row['v']) === 'NO') {
        $con->query("ALTER TABLE appointment_requests MODIFY preferred_time TIME NULL");
    }
}

$error = "";
$success = "";

$userRole = $_SESSION['role_name'] ?? 'Admin';
$userName = $_SESSION['username'] ?? '';
$userEmail = $_SESSION['email'] ?? '';
$isAdmin = ($userRole === 'Admin');
$canManageAppointments = $isAdmin;
$canCreateAppointments = false;
$isMonk = ($userRole === 'Monk');
$isDoctor = ($userRole === 'Doctor');
$canViewIncomingRequests = $isAdmin || $isDoctor;

$currentDoctorId = null;
if ($isDoctor) {
    if (!empty($userEmail)) {
        $stmt = $con->prepare("SELECT doctor_id FROM doctors WHERE status='active' AND email = ? LIMIT 1");
        $stmt->bind_param("s", $userEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $currentDoctorId = (int)$result->fetch_assoc()['doctor_id'];
        }
        $stmt->close();
    }

    if (!$currentDoctorId) {
        $stmt = $con->prepare("SELECT doctor_id FROM doctors WHERE status='active' AND (full_name = ? OR REPLACE(LOWER(full_name), ' ', '') = REPLACE(LOWER(?), ' ', '') OR LOWER(full_name) LIKE LOWER(?)) ORDER BY CASE WHEN full_name = ? THEN 0 ELSE 1 END, doctor_id ASC LIMIT 1");
        $searchName = "%{$userName}%";
        $stmt->bind_param("ssss", $userName, $userName, $searchName, $userName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $currentDoctorId = (int)$result->fetch_assoc()['doctor_id'];
        }
        $stmt->close();
    }
}

$currentMonkId = null;
if ($isMonk) {
    $stmt = $con->prepare("SELECT monk_id FROM monks WHERE status = 'active' AND (full_name = ? OR REPLACE(LOWER(full_name), ' ', '') = REPLACE(LOWER(?), ' ', '') OR LOWER(full_name) LIKE LOWER(?)) ORDER BY CASE WHEN full_name = ? THEN 0 ELSE 1 END, monk_id ASC LIMIT 1");
    $searchName = "%{$userName}%";
    $stmt->bind_param("ssss", $userName, $userName, $searchName, $userName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $currentMonkId = (int)$result->fetch_assoc()['monk_id'];
    }
    $stmt->close();

    // If there is no matching monk profile, create one automatically using the login name.
    if (!$currentMonkId) {
        $stmt = $con->prepare("INSERT INTO monks (full_name, status, notes) VALUES (?, 'active', ?)");
        $autoNote = 'Auto-created from monk user login for appointment flow';
        $stmt->bind_param("ss", $userName, $autoNote);
        if ($stmt->execute()) {
            $currentMonkId = (int)$stmt->insert_id;
        }
        $stmt->close();
    }
}

$status_colors = [
    'scheduled' => 'primary',
    'completed' => 'success',
    'cancelled' => 'danger',
    'no-show' => 'secondary'
];

// Handle appointment request + CRUD actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    if ($form_name === 'submit_request') {
        if (!$isMonk) {
            http_response_code(403);
            $error = "You are not authorized to submit appointment requests.";
        } else {
            $preferred_doctor_id = intval($_POST['preferred_doctor_id'] ?? 0);
            $preferred_date = $_POST['preferred_date'] ?? '';
            $request_notes = trim($_POST['request_notes'] ?? '');
            $created_by = $_SESSION['user_id'] ?? null;
            $requestMonkId = $currentMonkId;

            if ($requestMonkId <= 0 || $preferred_doctor_id <= 0 || empty($preferred_date)) {
                $error = "Monk profile, preferred doctor, and date are required.";
            } else {
                $stmt = $con->prepare("INSERT INTO appointment_requests (monk_id, preferred_doctor_id, preferred_date, preferred_time, request_notes, status, created_by) VALUES (?, ?, ?, NULL, ?, 'pending', ?)");
                $stmt->bind_param("iissi", $requestMonkId, $preferred_doctor_id, $preferred_date, $request_notes, $created_by);
                if ($stmt->execute()) {
                    $success = "Appointment request submitted. Admin will assign room and confirm schedule.";
                } else {
                    $error = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    } elseif (!$canManageAppointments) {
        http_response_code(403);
        $error = "You are not authorized to modify appointments.";
    } else {

    if ($form_name === 'create') {
        if (!$canCreateAppointments) {
            http_response_code(403);
            $error = "Only doctors can create appointments directly.";
        } else {
        $monk_id = intval($_POST['monk_id']);
        $doctor_id = $isDoctor ? (int)$currentDoctorId : intval($_POST['doctor_id']);
        $room_slot_id = !empty($_POST['room_slot_id']) ? intval($_POST['room_slot_id']) : null;
        $app_date = $_POST['app_date'];
        $app_time = $_POST['app_time'];
        $notes = $_POST['notes'] ?? '';
        $created_by = $_SESSION['user_id'] ?? null;

        if ($doctor_id <= 0) {
            $error = "Your doctor profile is not linked. Please contact admin.";
        } else {

        if ($room_slot_id) {
            $stmt = $con->prepare("INSERT INTO appointments (monk_id, doctor_id, room_slot_id, app_date, app_time, status, notes, created_by) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?)");
            $stmt->bind_param("iiisssi", $monk_id, $doctor_id, $room_slot_id, $app_date, $app_time, $notes, $created_by);
        } else {
            $stmt = $con->prepare("INSERT INTO appointments (monk_id, doctor_id, app_date, app_time, status, notes, created_by) VALUES (?, ?, ?, ?, 'scheduled', ?, ?)");
            $stmt->bind_param("iisssi", $monk_id, $doctor_id, $app_date, $app_time, $notes, $created_by);
        }
        
        if ($stmt->execute()) {
            $success = "Appointment created successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
        }
        }
    }

    if ($form_name === 'update') {
        $app_id = intval($_POST['app_id']);
        $monk_id = intval($_POST['monk_id']);
        $doctor_id = $isDoctor ? (int)$currentDoctorId : intval($_POST['doctor_id']);
        $room_slot_id = !empty($_POST['room_slot_id']) ? intval($_POST['room_slot_id']) : null;
        $app_date = $_POST['app_date'];
        $app_time = $_POST['app_time'];
        $status = $_POST['status'];
        $notes = $_POST['notes'] ?? '';

        if ($doctor_id <= 0) {
            $error = "Your doctor profile is not linked. Please contact admin.";
        } elseif ($room_slot_id) {
            if ($isDoctor) {
                $stmt = $con->prepare("UPDATE appointments SET monk_id=?, doctor_id=?, room_slot_id=?, app_date=?, app_time=?, status=?, notes=? WHERE app_id=? AND doctor_id=?");
                $stmt->bind_param("iiissssii", $monk_id, $doctor_id, $room_slot_id, $app_date, $app_time, $status, $notes, $app_id, $doctor_id);
            } else {
                $stmt = $con->prepare("UPDATE appointments SET monk_id=?, doctor_id=?, room_slot_id=?, app_date=?, app_time=?, status=?, notes=? WHERE app_id=?");
                $stmt->bind_param("iiissssi", $monk_id, $doctor_id, $room_slot_id, $app_date, $app_time, $status, $notes, $app_id);
            }
        } else {
            if ($isDoctor) {
                $stmt = $con->prepare("UPDATE appointments SET monk_id=?, doctor_id=?, room_slot_id=NULL, app_date=?, app_time=?, status=?, notes=? WHERE app_id=? AND doctor_id=?");
                $stmt->bind_param("iissssii", $monk_id, $doctor_id, $app_date, $app_time, $status, $notes, $app_id, $doctor_id);
            } else {
                $stmt = $con->prepare("UPDATE appointments SET monk_id=?, doctor_id=?, room_slot_id=NULL, app_date=?, app_time=?, status=?, notes=? WHERE app_id=?");
                $stmt->bind_param("iissssi", $monk_id, $doctor_id, $app_date, $app_time, $status, $notes, $app_id);
            }
        }

        if (empty($error)) {
            if ($stmt->execute()) {
                if ($isDoctor && $stmt->affected_rows === 0) {
                    $error = "You can update only your own appointments.";
                } else {
                    $success = "Appointment updated successfully!";
                }
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($form_name === 'delete') {
        $app_id = intval($_POST['app_id']);
        if ($isDoctor && (int)$currentDoctorId <= 0) {
            $error = "Your doctor profile is not linked. Please contact admin.";
        } elseif ($isDoctor) {
            $stmt = $con->prepare("DELETE FROM appointments WHERE app_id=? AND doctor_id=?");
            $stmt->bind_param("ii", $app_id, $currentDoctorId);
        } else {
            $stmt = $con->prepare("DELETE FROM appointments WHERE app_id=?");
            $stmt->bind_param("i", $app_id);
        }

        if (empty($error)) {
        if ($stmt->execute()) {
            if ($isDoctor && $stmt->affected_rows === 0) {
                $error = "You can delete only your own appointments.";
            } else {
                $success = "Appointment deleted successfully!";
            }
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
        }
    }

    if ($form_name === 'assign_request') {
        $request_id = intval($_POST['request_id']);
        $room_slot_id = !empty($_POST['room_slot_id']) ? intval($_POST['room_slot_id']) : null;
        $app_date = $_POST['app_date'];
        $admin_note = trim($_POST['admin_note'] ?? '');
        $created_by = $_SESSION['user_id'] ?? null;

        $con->begin_transaction();
        try {
            $stmt = $con->prepare("SELECT monk_id, preferred_doctor_id, request_notes, preferred_time FROM appointment_requests WHERE request_id = ? AND status = 'pending' LIMIT 1");
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $reqResult = $stmt->get_result();
            $requestRow = $reqResult ? $reqResult->fetch_assoc() : null;
            $stmt->close();

            if (!$requestRow) {
                throw new Exception("Request was already processed or not found.");
            }

            $monk_id = (int)$requestRow['monk_id'];
            $doctor_id = (int)$requestRow['preferred_doctor_id'];
            if ($doctor_id <= 0) {
                throw new Exception("Requested doctor is missing for this request.");
            }

            $request_notes = trim($requestRow['request_notes'] ?? '');
            $app_time = !empty($requestRow['preferred_time']) ? $requestRow['preferred_time'] : '09:00:00';
            $combined_notes = trim("Monk Request: " . $request_notes . "\nAdmin Note: " . $admin_note);

            if ($room_slot_id) {
                $stmt = $con->prepare("INSERT INTO appointments (monk_id, doctor_id, room_slot_id, app_date, app_time, status, notes, created_by) VALUES (?, ?, ?, ?, ?, 'scheduled', ?, ?)");
                $stmt->bind_param("iiisssi", $monk_id, $doctor_id, $room_slot_id, $app_date, $app_time, $combined_notes, $created_by);
            } else {
                $stmt = $con->prepare("INSERT INTO appointments (monk_id, doctor_id, app_date, app_time, status, notes, created_by) VALUES (?, ?, ?, ?, 'scheduled', ?, ?)");
                $stmt->bind_param("iisssi", $monk_id, $doctor_id, $app_date, $app_time, $combined_notes, $created_by);
            }

            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $new_app_id = $stmt->insert_id;
            $stmt->close();

            $stmt = $con->prepare("UPDATE appointment_requests SET status='assigned', reviewed_by=?, reviewed_at=NOW(), linked_app_id=? WHERE request_id=? AND status='pending'");
            $reviewed_by = $_SESSION['user_id'] ?? null;
            $stmt->bind_param("iii", $reviewed_by, $new_app_id, $request_id);
            if (!$stmt->execute() || $stmt->affected_rows === 0) {
                throw new Exception("Request was already processed.");
            }
            $stmt->close();

            $con->commit();
            $success = "Request assigned successfully. Appointment created.";
        } catch (Exception $e) {
            $con->rollback();
            $error = "Error assigning request: " . $e->getMessage();
        }
    }

    if ($form_name === 'reject_request') {
        $request_id = intval($_POST['request_id']);
        $stmt = $con->prepare("UPDATE appointment_requests SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE request_id=? AND status='pending'");
        $reviewed_by = $_SESSION['user_id'] ?? null;
        $stmt->bind_param("ii", $reviewed_by, $request_id);
        if ($stmt->execute()) {
            $success = "Appointment request rejected.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    }
}

// Fetch appointments with all details
$appointments_query = "
    SELECT 
        a.*,
        m.full_name AS monk_name,
        d.full_name AS doctor_name,
        d.specialization,
        r.name AS room_name,
        u.name AS created_by_name
    FROM appointments a
    JOIN monks m ON a.monk_id = m.monk_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    LEFT JOIN room_slots rs ON a.room_slot_id = rs.room_slot_id
    LEFT JOIN rooms r ON rs.room_id = r.room_id
    LEFT JOIN users u ON a.created_by = u.user_id
";

if ($isMonk) {
    if ($currentMonkId) {
        $appointments_query .= " WHERE a.monk_id = " . (int)$currentMonkId;
    } else {
        $appointments_query .= " WHERE 1 = 0";
        if (!$error) {
            $error = "Your account is not linked to a monk profile by name. Please ask admin to align monk profile name with your login name.";
        }
    }
} elseif ($isDoctor) {
    if ($currentDoctorId) {
        $appointments_query .= " WHERE a.doctor_id = " . (int)$currentDoctorId;
    } else {
        $appointments_query .= " WHERE 1 = 0";
        if (!$error) {
            $error = "Your account is not linked to a doctor profile by name/email. Please ask admin to align your doctor profile.";
        }
    }
}

$appointments_query .= " ORDER BY a.app_date DESC, a.app_time DESC";
$appointments_res = $con->query($appointments_query);

$pending_requests = [];
if ($canViewIncomingRequests) {
    $pending_where = "ar.status = 'pending'";
    if ($isDoctor) {
        $pending_where .= " AND ar.preferred_doctor_id = " . (int)$currentDoctorId;
    }
    $req_res = $con->query("SELECT ar.*, m.full_name AS monk_name, d.full_name AS preferred_doctor_name, d.specialization AS preferred_doctor_specialization, u.name AS requested_by_name
        FROM appointment_requests ar
        JOIN monks m ON ar.monk_id = m.monk_id
        LEFT JOIN doctors d ON ar.preferred_doctor_id = d.doctor_id
        LEFT JOIN users u ON ar.created_by = u.user_id
        WHERE " . $pending_where . "
        ORDER BY ar.created_at ASC");
    if ($req_res) {
        while ($row = $req_res->fetch_assoc()) {
            $pending_requests[] = $row;
        }
    }
}

$my_requests = [];
if ($isMonk && $currentMonkId) {
    $stmt = $con->prepare("SELECT ar.*, d.full_name AS preferred_doctor_name, u.name AS reviewed_by_name
        FROM appointment_requests ar
        LEFT JOIN doctors d ON ar.preferred_doctor_id = d.doctor_id
        LEFT JOIN users u ON ar.reviewed_by = u.user_id
        WHERE ar.monk_id = ?
        ORDER BY ar.created_at DESC
        LIMIT 10");
    $stmt->bind_param("i", $currentMonkId);
    $stmt->execute();
    $req_res = $stmt->get_result();
    if ($req_res) {
        while ($row = $req_res->fetch_assoc()) {
            $my_requests[] = $row;
        }
    }
    $stmt->close();
}

// Fetch monks for dropdown
$monks_res = $con->query("SELECT monk_id, full_name FROM monks WHERE status='active' ORDER BY full_name ASC");
$monks = [];
while ($m = $monks_res->fetch_assoc()) {
    $monks[] = $m;
}

// Fetch doctors for dropdown
if ($isDoctor && $currentDoctorId) {
    $doctors_res = $con->query("SELECT doctor_id, full_name, specialization FROM doctors WHERE status='active' AND doctor_id = " . (int)$currentDoctorId . " ORDER BY full_name ASC");
} else {
    $doctors_res = $con->query("SELECT doctor_id, full_name, specialization FROM doctors WHERE status='active' ORDER BY full_name ASC");
}
$doctors = [];
while ($d = $doctors_res->fetch_assoc()) {
    $doctors[] = $d;
}

// Fetch room slots for dropdown
$rooms_res = $con->query("
    SELECT rs.room_slot_id, r.name, rs.day_of_week, rs.start_time, rs.end_time
    FROM room_slots rs
    JOIN rooms r ON rs.room_id = r.room_id
    WHERE rs.is_active = 1
    ORDER BY r.name, rs.day_of_week, rs.start_time
");
$room_slots = [];
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
while ($rs = $rooms_res->fetch_assoc()) {
    $room_slots[] = [
        'id' => $rs['room_slot_id'],
        'label' => $rs['name'] . ' - ' . $days[$rs['day_of_week']] . ' ' . 
                   date('g:i A', strtotime($rs['start_time'])) . '-' . 
                   date('g:i A', strtotime($rs['end_time']))
    ];
}

// Compute stats
$total_count = $appointments_res->num_rows;
$all_appointments = [];
$stat_scheduled = 0;
$stat_completed = 0;
$stat_cancelled = 0;
while ($row = $appointments_res->fetch_assoc()) {
    $all_appointments[] = $row;
    if ($row['status'] === 'scheduled') $stat_scheduled++;
    elseif ($row['status'] === 'completed') $stat_completed++;
    elseif ($row['status'] === 'cancelled') $stat_cancelled++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<?php include 'navbar.php'; ?>

    <?php if($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($canViewIncomingRequests): ?>
    <div class="modern-card mb-4">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <h6><i class="bi bi-inbox me-2"></i>Incoming Appointment Requests</h6>
            <span class="badge-modern badge-warning badge-dot"><?= count($pending_requests) ?> Pending</span>
        </div>
        <div class="card-body-modern" style="padding:16px;">
            <div class="table-responsive-modern">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Monk</th>
                            <th>Preferred Doctor</th>
                            <th>Preferred Date</th>
                            <th>Request Notes</th>
                            <th>Requested By</th>
                            <?php if ($isAdmin): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($pending_requests) === 0): ?>
                            <tr>
                                <td colspan="<?= $isAdmin ? '6' : '5' ?>" class="text-center py-4 text-muted">
                                    <i class="bi bi-info-circle"></i> No incoming appointment requests.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($pending_requests as $req): ?>
                            <tr>
                                <td><?= htmlspecialchars($req['monk_name']) ?></td>
                                <td>
                                    <?php if (!empty($req['preferred_doctor_name'])): ?>
                                        <strong><?= htmlspecialchars($req['preferred_doctor_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($req['preferred_doctor_specialization'] ?? '') ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not selected</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= date('M d, Y', strtotime($req['preferred_date'])) ?></strong>
                                </td>
                                <td><?= $req['request_notes'] ? htmlspecialchars($req['request_notes']) : '<span class="text-muted">-</span>' ?></td>
                                <td><?= $req['requested_by_name'] ? htmlspecialchars($req['requested_by_name']) : '<span class="text-muted">System</span>' ?></td>
                                <?php if ($isAdmin): ?>
                                <td>
                                    <div class="d-flex gap-1">
                                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#assignRequestModal<?= $req['request_id'] ?>" title="Assign">
                                            <i class="bi bi-person-check"></i>
                                        </button>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="form_name" value="reject_request">
                                            <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">
                                            <button type="submit" class="btn-icon danger" onclick="return confirm('Reject this appointment request?')" title="Reject">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>

                            <?php if ($isAdmin): ?>
                            <div class="modal fade" id="assignRequestModal<?= $req['request_id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="bi bi-person-check me-2"></i>Assign Appointment</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="form_name" value="assign_request">
                                                <input type="hidden" name="request_id" value="<?= $req['request_id'] ?>">

                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Monk</label>
                                                    <input type="text" class="form-control-modern" value="<?= htmlspecialchars($req['monk_name']) ?>" readonly>
                                                </div>

                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Requested Doctor</label>
                                                    <input type="text" class="form-control-modern" value="<?= htmlspecialchars(($req['preferred_doctor_name'] ?? 'Not selected') . ((empty($req['preferred_doctor_specialization']) ? '' : ' - ' . $req['preferred_doctor_specialization']))) ?>" readonly>
                                                </div>

                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Room Slot (Optional)</label>
                                                    <select name="room_slot_id" class="form-select-modern">
                                                        <option value="">-- No Room --</option>
                                                        <?php foreach($room_slots as $slot): ?>
                                                            <option value="<?= $slot['id'] ?>"><?= htmlspecialchars($slot['label']) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="form-group-modern">
                                                            <label class="form-label-modern">Appointment Date</label>
                                                            <input type="date" name="app_date" class="form-control-modern" value="<?= htmlspecialchars($req['preferred_date']) ?>" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Admin Note (Optional)</label>
                                                    <textarea name="admin_note" class="form-control-modern" rows="2" placeholder="Add note for this assignment..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn-modern btn-primary-modern">Assign & Create Appointment</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isMonk): ?>
    <div class="modern-card mb-4">
        <div class="card-header-modern d-flex justify-content-between align-items-center">
            <h6><i class="bi bi-send me-2"></i>Appointment Requests</h6>
            <button class="btn-modern btn-primary-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#requestModal">
                <i class="bi bi-plus-circle"></i> Request Appointment
            </button>
        </div>
        <div class="card-body-modern" style="padding:16px;">
            <?php if (!$currentMonkId): ?>
                <div class="alert-modern alert-warning-modern mb-3">
                    <i class="bi bi-exclamation-triangle"></i> Your account is not linked to a monk profile by name. Ask admin to align names.
                </div>
            <?php endif; ?>

            <?php if (count($my_requests) === 0): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size:2rem;opacity:.35;"></i>
                    <p class="mt-2 mb-0">No appointment requests yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive-modern">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Preferred Date</th>
                                <th>Preferred Doctor</th>
                                <th>Notes</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_requests as $req): ?>
                                <?php
                                    $req_badge = 'badge-warning';
                                    if ($req['status'] === 'assigned') $req_badge = 'badge-success';
                                    if ($req['status'] === 'rejected') $req_badge = 'badge-danger';
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= date('M d, Y', strtotime($req['preferred_date'])) ?></strong>
                                    </td>
                                    <td><?= $req['preferred_doctor_name'] ? htmlspecialchars($req['preferred_doctor_name']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><?= $req['request_notes'] ? htmlspecialchars($req['request_notes']) : '<span class="text-muted">-</span>' ?></td>
                                    <td><span class="badge-modern <?= $req_badge ?> badge-dot"><?= ucfirst($req['status']) ?></span></td>
                                    <td><?= $req['reviewed_by_name'] ? htmlspecialchars($req['reviewed_by_name']) : '<span class="text-muted">-</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Total Appointments</span>
                    <span class="stat-value"><?= $total_count ?></span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon amber"><i class="bi bi-clock-history"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Scheduled</span>
                    <span class="stat-value"><?= $stat_scheduled ?></span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon emerald"><i class="bi bi-check-circle"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Completed</span>
                    <span class="stat-value"><?= $stat_completed ?></span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon rose"><i class="bi bi-x-circle"></i></div>
                <div class="stat-info">
                    <span class="stat-label">Cancelled</span>
                    <span class="stat-value"><?= $stat_cancelled ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Search Section -->
    <div id="advanced-search" data-type="appointments" class="mb-4"></div>

    <!-- Appointments Table -->
    <div id="appointments-list">
        <div class="modern-table-wrapper">
            <div class="modern-table-header">
                <h5><i class="bi bi-calendar-check"></i> <?= $canManageAppointments ? 'Appointment Management' : 'My Appointments' ?></h5>
                <?php if ($canCreateAppointments): ?>
                    <button class="btn-modern btn-primary-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="bi bi-plus-circle"></i> New Appointment
                    </button>
                <?php elseif ($isMonk): ?>
                    <button class="btn-modern btn-primary-modern" data-bs-toggle="modal" data-bs-target="#requestModal">
                        <i class="bi bi-send"></i> Request Appointment
                    </button>
                <?php endif; ?>
            </div>
            <div class="table-responsive-modern">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Date &amp; Time</th>
                            <th>Monk</th>
                            <th>Doctor</th>
                            <th>Specialization</th>
                            <th>Room</th>
                            <th>Notes</th>
                            <th>Status</th>
                            <th>Created By</th>
                            <?php if ($canManageAppointments): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_appointments) == 0): ?>
                            <tr>
                                <td colspan="<?= $canManageAppointments ? '9' : '8' ?>" class="text-center py-4 text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    <?= $canManageAppointments ? 'No appointments found. Click "New Appointment" to create one.' : 'No appointments found for your profile.' ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach($all_appointments as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= date('M d, Y', strtotime($row['app_date'])) ?></strong><br>
                                    <small class="text-muted"><?= date('g:i A', strtotime($row['app_time'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($row['monk_name']) ?></td>
                                <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                                <td><?= htmlspecialchars($row['specialization']) ?></td>
                                <td><?= $row['room_name'] ? htmlspecialchars($row['room_name']) : '<span class="text-muted">—</span>' ?></td>
                                <td><?= $row['notes'] ? htmlspecialchars($row['notes']) : '<span class="text-muted">—</span>' ?></td>
                                <td>
                                    <?php
                                        $badge_class = 'badge-neutral';
                                        if ($row['status'] === 'scheduled') $badge_class = 'badge-primary';
                                        elseif ($row['status'] === 'completed') $badge_class = 'badge-success';
                                        elseif ($row['status'] === 'cancelled') $badge_class = 'badge-danger';
                                        elseif ($row['status'] === 'no-show') $badge_class = 'badge-warning';
                                    ?>
                                    <span class="badge-modern <?= $badge_class ?> badge-dot"><?= ucfirst($row['status']) ?></span>
                                </td>
                                <td><?= $row['created_by_name'] ? htmlspecialchars($row['created_by_name']) : '<span class="text-muted">—</span>' ?></td>
                                <?php if ($canManageAppointments): ?>
                                <td>
                                    <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['app_id'] ?>" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button class="btn-icon danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['app_id'] ?>" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                                <?php endif; ?>
                            </tr>

                            <?php if ($canManageAppointments): ?>
                            <!-- Edit Modal -->
                            <div class="modal fade" id="editModal<?= $row['app_id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Appointment</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="form_name" value="update">
                                                <input type="hidden" name="app_id" value="<?= $row['app_id'] ?>">
                                                
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Monk</label>
                                                    <select name="monk_id" class="form-select-modern" required>
                                                        <?php foreach($monks as $monk): ?>
                                                            <option value="<?= $monk['monk_id'] ?>" <?= $monk['monk_id'] == $row['monk_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($monk['full_name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Doctor</label>
                                                    <?php if ($isDoctor): ?>
                                                        <input type="hidden" name="doctor_id" value="<?= (int)$currentDoctorId ?>">
                                                        <input type="text" class="form-control-modern" value="<?= htmlspecialchars($row['doctor_name']) ?>" readonly>
                                                    <?php else: ?>
                                                        <select name="doctor_id" class="form-select-modern" required>
                                                            <?php foreach($doctors as $doctor): ?>
                                                                <option value="<?= $doctor['doctor_id'] ?>" <?= $doctor['doctor_id'] == $row['doctor_id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($doctor['full_name']) ?> - <?= $doctor['specialization'] ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Room Slot (Optional)</label>
                                                    <select name="room_slot_id" class="form-select-modern">
                                                        <option value="">-- No Room --</option>
                                                        <?php foreach($room_slots as $slot): ?>
                                                            <option value="<?= $slot['id'] ?>" <?= $slot['id'] == $row['room_slot_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($slot['label']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group-modern">
                                                            <label class="form-label-modern">Date</label>
                                                            <input type="date" name="app_date" class="form-control-modern" value="<?= $row['app_date'] ?>" required>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-group-modern">
                                                            <label class="form-label-modern">Time</label>
                                                            <input type="time" name="app_time" class="form-control-modern" value="<?= $row['app_time'] ?>" required>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Status</label>
                                                    <select name="status" class="form-select-modern" required>
                                                        <option value="scheduled" <?= $row['status'] == 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                                        <option value="completed" <?= $row['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        <option value="cancelled" <?= $row['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                        <option value="no-show" <?= $row['status'] == 'no-show' ? 'selected' : '' ?>>No-Show</option>
                                                    </select>
                                                </div>

                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Notes</label>
                                                    <textarea name="notes" class="form-control-modern" rows="3"><?= htmlspecialchars($row['notes']) ?></textarea>
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
                            <div class="modal fade" id="deleteModal<?= $row['app_id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <div class="modal-header">
                                                <h5 class="modal-title"><i class="bi bi-trash"></i> Confirm Delete</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this appointment?</p>
                                                <ul class="list-unstyled">
                                                    <li><strong>Monk:</strong> <?= htmlspecialchars($row['monk_name']) ?></li>
                                                    <li><strong>Doctor:</strong> <?= htmlspecialchars($row['doctor_name']) ?></li>
                                                    <li><strong>Date:</strong> <?= date('M d, Y', strtotime($row['app_date'])) ?> at <?= date('g:i A', strtotime($row['app_time'])) ?></li>
                                                </ul>
                                                <input type="hidden" name="form_name" value="delete">
                                                <input type="hidden" name="app_id" value="<?= $row['app_id'] ?>">
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn-modern" style="background:#ef4444;color:#fff;">Yes, Delete</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- END appointments-list -->

<?php if ($canCreateAppointments): ?>
<!-- Add New Appointment Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> New Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="form_name" value="create">
                    
                    <div class="form-group-modern">
                        <label class="form-label-modern">Monk</label>
                        <select name="monk_id" class="form-select-modern" required>
                            <option value="">-- Select Monk --</option>
                            <?php foreach($monks as $monk): ?>
                                <option value="<?= $monk['monk_id'] ?>">
                                    <?= htmlspecialchars($monk['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Doctor</label>
                        <?php if ($isDoctor): ?>
                            <input type="hidden" name="doctor_id" value="<?= (int)$currentDoctorId ?>">
                            <input type="text" class="form-control-modern" value="<?= isset($doctors[0]) ? htmlspecialchars($doctors[0]['full_name'] . ' - ' . $doctors[0]['specialization']) : 'Not linked' ?>" readonly>
                        <?php else: ?>
                            <select name="doctor_id" class="form-select-modern" required>
                                <option value="">-- Select Doctor --</option>
                                <?php foreach($doctors as $doctor): ?>
                                    <option value="<?= $doctor['doctor_id'] ?>">
                                        <?= htmlspecialchars($doctor['full_name']) ?> - <?= $doctor['specialization'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Room Slot (Optional)</label>
                        <select name="room_slot_id" class="form-select-modern">
                            <option value="">-- No Room --</option>
                            <?php foreach($room_slots as $slot): ?>
                                <option value="<?= $slot['id'] ?>">
                                    <?= htmlspecialchars($slot['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Date</label>
                                <input type="date" name="app_date" class="form-control-modern" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Time</label>
                                <input type="time" name="app_time" class="form-control-modern" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Notes (Optional)</label>
                        <textarea name="notes" class="form-control-modern" rows="3" placeholder="Any special instructions or notes..."></textarea>
                    </div>

                    <div class="alert-modern alert-success-modern">
                        <i class="bi bi-info-circle"></i> <small>Appointment will be created with "Scheduled" status.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">Create Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($isMonk): ?>
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-send"></i> Request Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="form_name" value="submit_request">

                    <div class="form-group-modern">
                        <label class="form-label-modern">Preferred Doctor</label>
                        <select name="preferred_doctor_id" class="form-select-modern" required>
                            <option value="">-- Select Doctor --</option>
                            <?php foreach($doctors as $doctor): ?>
                                <option value="<?= $doctor['doctor_id'] ?>">
                                    <?= htmlspecialchars($doctor['full_name']) ?> - <?= htmlspecialchars($doctor['specialization']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Preferred Date</label>
                                <input type="date" name="preferred_date" class="form-control-modern" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Reason / Notes</label>
                        <textarea name="request_notes" class="form-control-modern" rows="3" placeholder="Describe your medical need or request details..."></textarea>
                    </div>

                    <div class="alert-modern alert-success-modern mb-0">
                        <i class="bi bi-info-circle"></i> Your request will appear in Admin dashboard and appointments queue for assignment.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<!-- Advanced Search System -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/advanced-search.js"></script>
<script>
// Initialize Advanced Search for Appointments
window.addEventListener('load', function() {
    new AdvancedSearch('appointments');
});
</script>

</body>
</html>
