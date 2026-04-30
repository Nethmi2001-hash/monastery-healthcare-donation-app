<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/includes/db_config.php';
$conn = getDBConnection();

$userEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['username'] ?? 'Doctor';
$userId = $_SESSION['user_id'] ?? 0;
$profile_error = '';
$profile_success = '';
$openProfileModal = isset($_GET['edit_profile']) && $_GET['edit_profile'] === '1';

// Find linked doctor profile by email
$doctor = null;
$doctor_id = null;
if (!empty($userEmail)) {
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

// Fallback by doctor name if email match is not found.
if (!$doctor) {
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE status = 'active' AND (full_name = ? OR REPLACE(LOWER(full_name), ' ', '') = REPLACE(LOWER(?), ' ', '') OR LOWER(full_name) LIKE LOWER(?)) ORDER BY CASE WHEN full_name = ? THEN 0 ELSE 1 END, doctor_id ASC LIMIT 1");
    $searchName = "%{$userName}%";
    $stmt->bind_param("ssss", $userName, $userName, $searchName, $userName);
    $stmt->execute();
    $result = $stmt->get_result();
    $doctor = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

// Auto-create doctor profile when no active profile can be linked.
if (!$doctor) {
    $stmt = $conn->prepare("INSERT INTO doctors (full_name, specialization, email, status) VALUES (?, 'General', ?, 'active')");
    $emailForProfile = !empty($userEmail) ? $userEmail : null;
    $stmt->bind_param("ss", $userName, $emailForProfile);
    if ($stmt->execute()) {
        $newDoctorId = (int)$stmt->insert_id;
        $fetch = $conn->prepare("SELECT * FROM doctors WHERE doctor_id = ? LIMIT 1");
        $fetch->bind_param("i", $newDoctorId);
        $fetch->execute();
        $res = $fetch->get_result();
        $doctor = $res ? $res->fetch_assoc() : null;
        $fetch->close();
    }
    $stmt->close();
}

if ($doctor) {
    $doctor_id = $doctor['doctor_id'];
}

// Doctor can update own profile details from dashboard.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name']) && $_POST['form_name'] === 'update_my_profile' && $doctor_id) {
    $full_name = trim($_POST['full_name'] ?? '');
    $specialization = trim($_POST['specialization'] ?? 'General');
    $contact = trim($_POST['contact'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');

    $valid_specs = ['Ayurvedic', 'Western', 'General'];
    if (!in_array($specialization, $valid_specs, true)) {
        $specialization = 'General';
    }

    if ($full_name === '') {
        $profile_error = 'Full name is required.';
    } else {
        $stmt = $conn->prepare("UPDATE doctors SET full_name = ?, specialization = ?, contact = ?, license_number = ?, updated_at = NOW() WHERE doctor_id = ?");
        $stmt->bind_param("ssssi", $full_name, $specialization, $contact, $license_number, $doctor_id);
        if ($stmt->execute()) {
            $profile_success = 'Your profile was updated successfully.';

            $refreshStmt = $conn->prepare("SELECT * FROM doctors WHERE doctor_id = ? LIMIT 1");
            $refreshStmt->bind_param("i", $doctor_id);
            $refreshStmt->execute();
            $refreshResult = $refreshStmt->get_result();
            if ($refreshResult) {
                $doctor = $refreshResult->fetch_assoc() ?: $doctor;
            }
            $refreshStmt->close();
        } else {
            $profile_error = 'Unable to update profile. Please try again.';
        }
        $stmt->close();
    }
}

// Stats
$stats = [
    'today_appointments' => 0,
    'week_appointments' => 0,
    'month_completed' => 0,
    'total_patients' => 0
];

if ($doctor_id) {
    // Today's appointments
    $r = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND app_date = CURRENT_DATE() AND status = 'scheduled'");
    if ($r) $stats['today_appointments'] = $r->fetch_assoc()['c'];

    // This week's appointments
    $r = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND app_date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND status = 'scheduled'");
    if ($r) $stats['week_appointments'] = $r->fetch_assoc()['c'];

    // Completed this month
    $r = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND status = 'completed' AND MONTH(app_date) = MONTH(CURRENT_DATE()) AND YEAR(app_date) = YEAR(CURRENT_DATE())");
    if ($r) $stats['month_completed'] = $r->fetch_assoc()['c'];

    // Total unique patients
    $r = $conn->query("SELECT COUNT(DISTINCT monk_id) as c FROM appointments WHERE doctor_id = $doctor_id");
    if ($r) $stats['total_patients'] = $r->fetch_assoc()['c'];

    // Today's appointment list
    $today_list = [];
    $r = $conn->query("
        SELECT a.*, m.full_name as monk_name, m.blood_group, m.allergies, m.chronic_conditions,
               r.name as room_name
        FROM appointments a
        JOIN monks m ON a.monk_id = m.monk_id
        LEFT JOIN room_slots rs ON a.room_slot_id = rs.room_slot_id
        LEFT JOIN rooms r ON rs.room_id = r.room_id
        WHERE a.doctor_id = $doctor_id AND a.app_date = CURRENT_DATE() AND a.status = 'scheduled'
        ORDER BY a.app_time ASC
    ");
    if ($r) while ($row = $r->fetch_assoc()) $today_list[] = $row;

    // Upcoming appointments (next 7 days, exclude today)
    $upcoming = [];
    $r = $conn->query("
        SELECT a.*, m.full_name as monk_name
        FROM appointments a
        JOIN monks m ON a.monk_id = m.monk_id
        WHERE a.doctor_id = $doctor_id AND a.app_date > CURRENT_DATE() AND a.app_date <= DATE_ADD(CURRENT_DATE(), INTERVAL 7 DAY) AND a.status = 'scheduled'
        ORDER BY a.app_date ASC, a.app_time ASC
        LIMIT 10
    ");
    if ($r) while ($row = $r->fetch_assoc()) $upcoming[] = $row;

    // Recent completed
    $recent_completed = [];
    $r = $conn->query("
        SELECT a.*, m.full_name as monk_name
        FROM appointments a
        JOIN monks m ON a.monk_id = m.monk_id
        WHERE a.doctor_id = $doctor_id AND a.status = 'completed'
        ORDER BY a.app_date DESC
        LIMIT 5
    ");
    if ($r) while ($row = $r->fetch_assoc()) $recent_completed[] = $row;

    // Weekly chart data
    $weekly_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $r = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE doctor_id = $doctor_id AND app_date = '$date'");
        $weekly_data[] = [
            'date' => date('D', strtotime($date)),
            'count' => $r ? $r->fetch_assoc()['c'] : 0
        ];
    }

    // Availability schedule
    $availability = [];
    $r = $conn->query("SELECT * FROM doctor_availability WHERE doctor_id = $doctor_id AND is_active = 1 ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
    if ($r) while ($row = $r->fetch_assoc()) $availability[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Seela Suwa Herath</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include 'navbar.php'; ?>

<?php if (!$doctor): ?>
    <!-- No Doctor Profile Linked -->
    <div class="welcome-card animate-fade-in" style="border-left: 4px solid var(--accent-500);">
        <h2><i class="bi bi-exclamation-triangle me-2"></i>Doctor Profile Not Linked</h2>
        <p>Your account could not be linked to a doctor profile. Please contact the administrator if this message continues.</p>
    </div>
<?php else: ?>

    <?php if ($profile_error): ?>
        <div class="alert alert-danger" style="margin:12px 24px;">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($profile_error) ?>
        </div>
    <?php endif; ?>

    <?php if ($profile_success): ?>
        <div class="alert alert-success" style="margin:12px 24px;">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($profile_success) ?>
        </div>
    <?php endif; ?>

    <!-- Welcome -->
    <div class="welcome-card animate-fade-in">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
            <div>
                <h2><i class="bi bi-heart-pulse me-2"></i>Welcome, <?= htmlspecialchars($doctor['full_name']) ?>!</h2>
                <p style="margin:0;">
                    <span class="badge-modern badge-primary" style="font-size:12px;"><?= htmlspecialchars($doctor['specialization']) ?> Medicine</span>
                    &nbsp; License: <?= htmlspecialchars($doctor['license_number'] ?? 'N/A') ?>
                </p>
            </div>
            <div class="welcome-date">
                <i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?> &bull; <?= date('g:i A') ?>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4 stagger-children">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #dc2626;">
                <div class="stat-icon" style="background:#fef2f2;color:#dc2626;"><i class="bi bi-calendar-day"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Today's Appointments</div>
                    <div class="stat-value"><?= $stats['today_appointments'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #0284c7;">
                <div class="stat-icon" style="background:#e0f2fe;color:#0284c7;"><i class="bi bi-calendar-week"></i></div>
                <div class="stat-info">
                    <div class="stat-label">This Week</div>
                    <div class="stat-value"><?= $stats['week_appointments'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #f97316;">
                <div class="stat-icon" style="background:#fff7ed;color:#f97316;"><i class="bi bi-check2-circle"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Completed (Month)</div>
                    <div class="stat-value"><?= $stats['month_completed'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #7c3aed;">
                <div class="stat-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="bi bi-people"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Total Patients</div>
                    <div class="stat-value"><?= $stats['total_patients'] ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Schedule + Chart -->
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="modern-card animate-fade-in" style="height:100%;">
                <div class="card-header-modern">
                    <h6><i class="bi bi-calendar-event me-2"></i>Today's Schedule</h6>
                    <span class="badge-modern badge-primary"><?= count($today_list) ?> appointments</span>
                </div>
                <div class="card-body-modern" style="padding:0;max-height:420px;overflow:auto;">
                    <?php if (count($today_list) > 0): ?>
                        <div class="table-responsive" style="max-height:420px;overflow:auto;">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Patient</th>
                                        <th>Room</th>
                                        <th>Health Info</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_list as $apt): ?>
                                    <tr>
                                        <td>
                                            <span style="font-weight:700;color:var(--primary-600);">
                                                <i class="bi bi-clock me-1"></i><?= date('g:i A', strtotime($apt['app_time'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($apt['monk_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge-modern badge-neutral"><?= htmlspecialchars($apt['room_name'] ?? 'N/A') ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size:12px;">
                                                <?php if ($apt['blood_group']): ?>
                                                    <span class="badge-modern badge-danger" style="font-size:10px;">🩸 <?= htmlspecialchars($apt['blood_group']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($apt['allergies']): ?>
                                                    <span class="badge-modern badge-warning" style="font-size:10px;">⚠ Allergies</span>
                                                <?php endif; ?>
                                                <?php if ($apt['chronic_conditions']): ?>
                                                    <span class="badge-modern badge-info" style="font-size:10px;">📋 Chronic</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><span class="badge-modern badge-warning badge-dot">Scheduled</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding:48px 16px;">
                            <i class="bi bi-calendar-check" style="font-size:48px;color:var(--primary-400);"></i>
                            <h5 style="font-size:16px;margin-top:16px;">No appointments today</h5>
                            <p style="font-size:13px;color:var(--text-secondary);">Enjoy your free day!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="chart-card animate-fade-in" style="height:420px;">
                <div class="chart-header">
                    <h6><i class="bi bi-graph-up me-2"></i>Weekly Activity</h6>
                    <span class="badge-modern badge-neutral">Last 7 days</span>
                </div>
                <div style="height:340px;">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming + Recent -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="modern-card animate-fade-in">
                <div class="card-header-modern">
                    <h6><i class="bi bi-calendar-range me-2"></i>Upcoming Appointments</h6>
                    <span class="badge-modern badge-info">Next 7 days</span>
                </div>
                <div class="card-body-modern" style="padding:16px;">
                    <?php if (count($upcoming) > 0): ?>
                        <?php foreach ($upcoming as $apt): ?>
                        <div style="padding:12px 16px;border:1px solid var(--border-color);border-radius:var(--border-radius-sm);margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;transition:all 0.2s;">
                            <div>
                                <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($apt['monk_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-secondary);">
                                    <i class="bi bi-calendar3 me-1"></i><?= date('D, M j', strtotime($apt['app_date'])) ?>
                                    &bull; <i class="bi bi-clock me-1"></i><?= date('g:i A', strtotime($apt['app_time'])) ?>
                                </div>
                            </div>
                            <span class="badge-modern badge-warning badge-dot">Scheduled</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding:32px;">
                            <i class="bi bi-calendar-x" style="font-size:32px;color:var(--text-muted);"></i>
                            <p style="margin-top:8px;font-size:13px;color:var(--text-secondary);">No upcoming appointments</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="modern-card animate-fade-in">
                <div class="card-header-modern">
                    <h6><i class="bi bi-check-circle me-2"></i>Recently Completed</h6>
                </div>
                <div class="card-body-modern" style="padding:16px;">
                    <?php if (count($recent_completed) > 0): ?>
                        <?php foreach ($recent_completed as $apt): ?>
                        <div style="padding:12px 16px;border:1px solid var(--border-color);border-radius:var(--border-radius-sm);margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($apt['monk_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-secondary);">
                                    <?= date('M j, Y', strtotime($apt['app_date'])) ?>
                                    <?php if ($apt['notes']): ?>
                                        &bull; <?= htmlspecialchars(substr($apt['notes'], 0, 40)) ?>...
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span class="badge-modern badge-success badge-dot">Completed</span>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding:32px;">
                            <i class="bi bi-clipboard-check" style="font-size:32px;color:var(--text-muted);"></i>
                            <p style="margin-top:8px;font-size:13px;color:var(--text-secondary);">No completed appointments yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Availability Schedule -->
    <?php if (count($availability) > 0): ?>
    <div class="modern-card mb-4 animate-fade-in">
        <div class="card-header-modern">
            <h6><i class="bi bi-clock-history me-2"></i>Your Availability Schedule</h6>
        </div>
        <div class="card-body-modern" style="padding:0;">
            <div class="table-responsive">
                <table class="modern-table">
                    <thead><tr><th>Day</th><th>Start Time</th><th>End Time</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($availability as $slot): ?>
                        <tr>
                            <td style="font-weight:600;"><?= htmlspecialchars($slot['day_of_week']) ?></td>
                            <td><?= date('g:i A', strtotime($slot['start_time'])) ?></td>
                            <td><?= date('g:i A', strtotime($slot['end_time'])) ?></td>
                            <td><span class="badge-modern badge-success badge-dot">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4 stagger-children">
        <div class="col-xl-3 col-md-6">
            <a href="patient_appointments.php" class="quick-action-card">
                <div class="quick-action-icon" style="background:#e0f2fe;color:#0284c7;"><i class="bi bi-calendar2-check"></i></div>
                <span class="quick-action-label">View Appointments</span>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="doctor_availability.php" class="quick-action-card">
                <div class="quick-action-icon" style="background:#fff7ed;color:#f97316;"><i class="bi bi-clock-history"></i></div>
                <span class="quick-action-label">My Availability</span>
            </a>
        </div>
        <div class="col-xl-3 col-md-6">
            <a href="monk_management.php" class="quick-action-card">
                <div class="quick-action-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="bi bi-person-hearts"></i></div>
                <span class="quick-action-label">Patient Records</span>
            </a>
        </div>
    </div>

    <div class="modal fade" id="editDoctorProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i>Update My Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="form_name" value="update_my_profile">

                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" value="<?= htmlspecialchars($doctor['full_name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Specialization</label>
                            <select class="form-select" name="specialization" required>
                                <?php $doc_spec = $doctor['specialization'] ?? 'General'; ?>
                                <option value="General" <?= $doc_spec === 'General' ? 'selected' : '' ?>>General</option>
                                <option value="Ayurvedic" <?= $doc_spec === 'Ayurvedic' ? 'selected' : '' ?>>Ayurvedic</option>
                                <option value="Western" <?= $doc_spec === 'Western' ? 'selected' : '' ?>>Western</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control" name="contact" value="<?= htmlspecialchars($doctor['contact'] ?? '') ?>" placeholder="Phone number">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" value="<?= htmlspecialchars($doctor['license_number'] ?? '') ?>" placeholder="Medical license number">
                        </div>

                        <div class="mb-0">
                            <label class="form-label">Login Email</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($userEmail) ?>" readonly>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- <div class="text-center mb-4" style="color:var(--text-muted);font-size:12px;">
        <i class="bi bi-dot"></i> End of Doctor Dashboard <i class="bi bi-dot"></i>
    </div> -->

<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script>
<?php if ($doctor_id): ?>
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($weekly_data, 'date')) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_column($weekly_data, 'count')) ?>,
            backgroundColor: 'rgba(2, 132, 199, 0.75)',
            borderRadius: 8,
            borderSkipped: false,
            barThickness: 32
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.04)' } },
            x: { grid: { display: false } }
        }
    }
});
<?php endif; ?>
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($openProfileModal && $doctor_id): ?>
<script>
window.addEventListener('load', function () {
    var modalEl = document.getElementById('editDoctorProfileModal');
    if (modalEl) {
        var profileModal = new bootstrap.Modal(modalEl);
        profileModal.show();
    }
});
</script>
<?php endif; ?>
</body>
</html>
<?php $conn->close(); ?>
