<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Route to role-specific dashboards
$userRole = $_SESSION['role_name'] ?? 'Admin';
switch ($userRole) {
    case 'Doctor':
        include __DIR__ . '/dashboard_doctor.php';
        exit();
    case 'Donor':
        include __DIR__ . '/dashboard_donor.php';
        exit();
    case 'Monk':
        include __DIR__ . '/dashboard_monk.php';
        exit();
}

// Admin Dashboard (default)
// Database connection
require_once __DIR__ . '/includes/db_config.php';
$conn = getDBConnection();

// Ensure appointment requests table exists for monk -> admin scheduling flow.
$conn->query("CREATE TABLE IF NOT EXISTS appointment_requests (
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

// Schema compatibility updates for older databases.
$doctorColRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_requests' AND COLUMN_NAME = 'preferred_doctor_id'");
if ($doctorColRes && (int)$doctorColRes->fetch_assoc()['c'] === 0) {
    $conn->query("ALTER TABLE appointment_requests ADD COLUMN preferred_doctor_id INT NULL AFTER monk_id");
    $conn->query("ALTER TABLE appointment_requests ADD INDEX idx_preferred_doctor (preferred_doctor_id)");
    $fkRes = $conn->query("SELECT COUNT(*) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_requests' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_appointment_requests_preferred_doctor' ");
    if ($fkRes && (int)$fkRes->fetch_assoc()['c'] === 0) {
        $conn->query("ALTER TABLE appointment_requests ADD CONSTRAINT fk_appointment_requests_preferred_doctor FOREIGN KEY (preferred_doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL");
    }
}

$timeNullableRes = $conn->query("SELECT IS_NULLABLE AS v FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointment_requests' AND COLUMN_NAME = 'preferred_time' LIMIT 1");
if ($timeNullableRes) {
    $row = $timeNullableRes->fetch_assoc();
    if ($row && strtoupper($row['v']) === 'NO') {
        $conn->query("ALTER TABLE appointment_requests MODIFY preferred_time TIME NULL");
    }
}

// Get dashboard statistics
$stats = [
    'total_monks' => 0,
    'total_doctors' => 0,
    'total_appointments' => 0,
    'total_donations' => 0,
    'pending_appointments' => 0,
    'incoming_requests' => 0,
    'completed_appointments' => 0,
    'monthly_donation_amount' => 0
];

// Get monks count
$result = $conn->query("SELECT COUNT(*) as count FROM monks WHERE status = 'active'");
if ($result) $stats['total_monks'] = $result->fetch_assoc()['count'];

// Get doctors count
$result = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'active'");
if ($result) $stats['total_doctors'] = $result->fetch_assoc()['count'];

// Get appointments count (this month)
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE MONTH(app_date) = MONTH(CURRENT_DATE()) AND YEAR(app_date) = YEAR(CURRENT_DATE())");
if ($result) $stats['total_appointments'] = $result->fetch_assoc()['count'];

// Get pending appointments
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'");
if ($result) $stats['pending_appointments'] = $result->fetch_assoc()['count'];

// Get incoming appointment requests from monks
$result = $conn->query("SELECT COUNT(*) as count FROM appointment_requests WHERE status = 'pending'");
if ($result) $stats['incoming_requests'] = $result->fetch_assoc()['count'];

$incoming_requests_preview = [];
$result = $conn->query("SELECT ar.request_id, ar.preferred_date, ar.preferred_time, m.full_name AS monk_name, d.full_name AS doctor_name
    FROM appointment_requests ar
    JOIN monks m ON ar.monk_id = m.monk_id
    LEFT JOIN doctors d ON ar.preferred_doctor_id = d.doctor_id
    WHERE ar.status = 'pending'
    ORDER BY ar.created_at ASC
    LIMIT 5");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $incoming_requests_preview[] = $row;
    }
}

// Get completed appointments
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'completed' AND MONTH(app_date) = MONTH(CURRENT_DATE())");
if ($result) $stats['completed_appointments'] = $result->fetch_assoc()['count'];

// Get monthly donations amount
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
if ($result) $stats['monthly_donation_amount'] = $result->fetch_assoc()['total'];

// Get today's appointments
$today_appointments = [];
$result = $conn->query("
    SELECT a.*, m.full_name as monk_name, d.full_name as doctor_name, a.app_time
    FROM appointments a
    JOIN monks m ON a.monk_id = m.monk_id
    JOIN doctors d ON a.doctor_id = d.doctor_id
    WHERE a.app_date = CURRENT_DATE() AND a.status = 'scheduled'
    ORDER BY a.app_time ASC
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $today_appointments[] = $row;
    }
}

// Get weekly appointment data for chart
$weekly_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE app_date = '$date'");
    $count = $result ? $result->fetch_assoc()['count'] : 0;
    $weekly_data[] = [
        'date' => date('D', strtotime($date)),
        'count' => $count
    ];
}

// Get monthly donation vs expenses data
$monthly_financial = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $donations = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetch_assoc()['total'];
    $bills = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM bills WHERE DATE_FORMAT(bill_date, '%Y-%m') = '$month'")->fetch_assoc()['total'];
    $monthly_financial[] = [
        'month' => date('M', strtotime($month)),
        'donations' => $donations,
        'expenses' => $bills
    ];
}

// Get alerts and notifications
$alerts = [];

// Check for pending appointments (scheduled for today or past dates)
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled' AND app_date <= CURRENT_DATE()");
if ($result) {
    $pending_count = $result->fetch_assoc()['count'];
    if ($pending_count > 0) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'bi-calendar-x',
            'message' => "You have $pending_count pending appointment(s) that need attention",
            'link' => 'patient_appointments.php'
        ];
    }
}

// Check for incoming monk requests that need assignment
if ($stats['incoming_requests'] > 0) {
    $alerts[] = [
        'type' => 'warning',
        'icon' => 'bi-inbox',
        'message' => "You have {$stats['incoming_requests']} incoming appointment request(s) to assign",
        'link' => 'patient_appointments.php'
    ];
}

// Check for appointments scheduled for tomorrow
$result = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled' AND app_date = DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY)");
if ($result) {
    $tomorrow_count = $result->fetch_assoc()['count'];
    if ($tomorrow_count > 0) {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'bi-calendar-event',
            'message' => "$tomorrow_count appointment(s) scheduled for tomorrow",
            'link' => 'patient_appointments.php'
        ];
    }
}

// Check for inactive doctors
$result = $conn->query("SELECT COUNT(*) as count FROM doctors WHERE status = 'inactive'");
if ($result) {
    $inactive_doctors = $result->fetch_assoc()['count'];
    if ($inactive_doctors > 0) {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'bi-person-x',
            'message' => "$inactive_doctors doctor(s) currently inactive",
            'link' => 'table.php'
        ];
    }
}

// Positive feedback if no critical issues
if (count($alerts) == 0) {
    $alerts[] = [
        'type' => 'success',
        'icon' => 'bi-check-circle',
        'message' => 'All systems running smoothly. No pending issues.',
        'link' => '#'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include 'navbar.php'; ?>

    <!-- Welcome Card -->
    <div class="welcome-card animate-fade-in">
        <h2><i class="bi bi-hand-thumbs-up me-2"></i>Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</h2>
        <p>Here's what's happening at Seela Suwa Herath Bikshu Gilan Arana today.</p>
        <div class="welcome-date">
            <i class="bi bi-calendar3"></i> <?= date('l, F j, Y') ?> &bull; <?= date('g:i A') ?>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="row g-4 mb-4 stagger-children">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: var(--primary-500);">
                <div class="stat-icon emerald"><i class="bi bi-people-fill"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Active Monks</div>
                    <div class="stat-value"><?= $stats['total_monks'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #0284c7;">
                <div class="stat-icon blue"><i class="bi bi-hospital"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Active Doctors</div>
                    <div class="stat-value"><?= $stats['total_doctors'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: #7c3aed;">
                <div class="stat-icon purple"><i class="bi bi-calendar2-check"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Monthly Appointments</div>
                    <div class="stat-value"><?= $stats['total_appointments'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card" style="--stat-color: var(--accent-500);">
                <div class="stat-icon amber"><i class="bi bi-cash-coin"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Monthly Donations</div>
                    <div class="stat-value">Rs.<?= number_format($stats['monthly_donation_amount']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($stats['incoming_requests'] > 0): ?>
    <div class="modern-card mb-4 animate-fade-in">
        <div class="card-body-modern" style="padding:16px 24px;">
            <a href="patient_appointments.php" class="alert-modern alert-warning-modern" style="text-decoration:none;display:flex;align-items:center;gap:12px;">
                <i class="bi bi-inbox"></i>
                <span><?= $stats['incoming_requests'] ?> incoming monk appointment request(s) waiting for doctor/room assignment</span>
                <i class="bi bi-chevron-right ms-auto" style="font-size:12px;opacity:0.6;"></i>
            </a>

            <?php if (!empty($incoming_requests_preview)): ?>
            <div style="margin-top:12px;display:grid;gap:8px;">
                <?php foreach ($incoming_requests_preview as $req): ?>
                <div style="padding:10px 12px;border:1px solid var(--border-color);border-radius:10px;background:var(--bg-card);display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <div>
                        <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($req['monk_name']) ?></div>
                        <div style="font-size:12px;color:var(--text-secondary);">
                            Preferred: <?= date('M d, Y', strtotime($req['preferred_date'])) ?>
                            <?php if (!empty($req['preferred_time'])): ?> at <?= date('g:i A', strtotime($req['preferred_time'])) ?><?php endif; ?>
                            <?php if (!empty($req['doctor_name'])): ?> &bull; Dr. <?= htmlspecialchars($req['doctor_name']) ?><?php endif; ?>
                        </div>
                    </div>
                    <a href="patient_appointments.php" class="btn-modern btn-outline-modern" style="padding:6px 12px;font-size:12px;">Open</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alerts -->
    <?php if (!empty($alerts)): ?>
    <div class="modern-card mb-4 animate-fade-in">
        <div class="card-header-modern">
            <h6><i class="bi bi-bell me-2"></i>Alerts & Notifications</h6>
        </div>
        <div class="card-body-modern" style="padding:16px 24px;">
            <?php foreach ($alerts as $alert): ?>
                <a href="<?= $alert['link'] ?>" class="alert-modern alert-<?= $alert['type'] ?>-modern" style="text-decoration:none;display:flex;align-items:center;gap:12px;">
                    <i class="bi <?= $alert['icon'] ?>"></i>
                    <span><?= $alert['message'] ?></span>
                    <i class="bi bi-chevron-right ms-auto" style="font-size:12px;opacity:0.5;"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="chart-card animate-fade-in">
                <div class="chart-header">
                    <h6><i class="bi bi-graph-up me-2"></i>Weekly Appointment Trend</h6>
                    <span class="badge-modern badge-neutral">Last 7 days</span>
                </div>
                <canvas id="weeklyChart" height="85"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="modern-card animate-fade-in" style="height:100%;">
                <div class="card-header-modern">
                    <h6><i class="bi bi-calendar-event me-2"></i>Today's Schedule</h6>
                    <span class="badge-modern badge-primary"><?= count($today_appointments) ?></span>
                </div>
                <div class="card-body-modern" style="padding:16px;">
                    <?php if (count($today_appointments) > 0): ?>
                        <?php foreach ($today_appointments as $apt): ?>
                            <div style="padding:12px;border:1px solid var(--border-color);border-radius:var(--border-radius-sm);margin-bottom:10px;transition:all 0.2s;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                    <span style="font-weight:700;color:var(--primary-600);font-size:13px;">
                                        <i class="bi bi-clock me-1"></i><?= date('g:i A', strtotime($apt['app_time'])) ?>
                                    </span>
                                    <span class="badge-modern badge-warning badge-dot">Scheduled</span>
                                </div>
                                <div style="font-weight:600;font-size:13.5px;"><?= htmlspecialchars($apt['monk_name']) ?></div>
                                <div style="font-size:12px;color:var(--text-secondary);">with Dr. <?= htmlspecialchars($apt['doctor_name']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" style="padding:32px 16px;">
                            <i class="bi bi-calendar-x" style="font-size:36px;"></i>
                            <h5 style="font-size:14px;margin-top:12px;">No appointments today</h5>
                            <p style="font-size:12px;">Schedule from the appointments page</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Chart -->
    <div class="chart-card mb-4 animate-fade-in">
        <div class="chart-header">
            <h6><i class="bi bi-bar-chart-line me-2"></i>Financial Overview</h6>
            <span class="badge-modern badge-neutral">Last 6 months</span>
        </div>
        <canvas id="financialChart" height="55"></canvas>
    </div>

    <!-- Quick Actions -->
    <div class="row g-3 mb-4 stagger-children">
        <div class="col-xl-2 col-md-4 col-6">
            <a href="patient_appointments.php" class="quick-action-card">
                <div class="quick-action-icon" style="background:var(--primary-100);color:var(--primary-700);"><i class="bi bi-inbox"></i></div>
                <span class="quick-action-label">Incoming Requests</span>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="donation_management.php" class="quick-action-card">
                <div class="quick-action-icon" style="background:var(--accent-100);color:var(--accent-700);"><i class="bi bi-cash-coin"></i></div>
                <span class="quick-action-label">Add Donation</span>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="bill_management.php" class="quick-action-card">
                <div class="quick-action-icon" style="background:#ffe4e6;color:#be123c;"><i class="bi bi-receipt-cutoff"></i></div>
                <span class="quick-action-label">Record Expense</span>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="monk_management.php" class="quick-action-card">
                <div class="quick-action-icon" style="background:#f3e8ff;color:#7c3aed;"><i class="bi bi-person-hearts"></i></div>
                <span class="quick-action-label">Manage Monks</span>
            </a>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="reports.php" class="quick-action-card">
                <div class="quick-action-icon" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-graph-up-arrow"></i></div>
                <span class="quick-action-label">View Reports</span>
            </a>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

<script>
// Weekly Chart
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($weekly_data, 'date')) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_column($weekly_data, 'count')) ?>,
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
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,0.04)' } },
            x: { grid: { display: false } }
        }
    }
});

// Financial Chart
const financialCtx = document.getElementById('financialChart').getContext('2d');
new Chart(financialCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthly_financial, 'month')) ?>,
        datasets: [{
            label: 'Donations',
            data: <?= json_encode(array_column($monthly_financial, 'donations')) ?>,
            backgroundColor: '#f97316',
            borderRadius: 6,
            borderSkipped: false
        }, {
            label: 'Expenses',
            data: <?= json_encode(array_column($monthly_financial, 'expenses')) ?>,
            backgroundColor: '#f59e0b',
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12, font: { size: 12, family: 'Inter' }, padding: 20 } }
        },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
