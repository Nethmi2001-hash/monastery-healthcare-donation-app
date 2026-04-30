<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include 'navbar.php';

// Database connection
require_once __DIR__ . '/includes/db_config.php';
$conn = getDBConnection();

// Get dashboard statistics
$stats = [
    'total_monks' => 0,
    'total_doctors' => 0,
    'total_appointments' => 0,
    'total_donations' => 0,
    'pending_appointments' => 0,
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
<html>
<head>
    <title>Dashboard - Giribawa Seela Suva Herath Bhikkhu Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/premium-theme.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --monastery-saffron: #f57c00;
            --monastery-orange: #ff9800;
            --monastery-light: #ffa726;
            --monastery-dark: #e65100;
            --monastery-pale: #fff3e0;
        }
        body {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .welcome-card {
            background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%);
            color: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(245, 124, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        .welcome-card::before {
            content: "🪷";
            position: absolute;
            font-size: 120px;
            opacity: 0.1;
            right: -20px;
            top: -20px;
            transform: rotate(-15deg);
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
            border-left: 4px solid var(--monastery-saffron);
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(245, 124, 0, 0.2);
        }
        .stat-card .icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--monastery-dark);
            margin: 10px 0;
        }
        .stat-card p {
            color: #666;
            margin: 0;
            font-size: 1rem;
        }
        .quick-actions .btn {
            background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%);
            border: none;
            color: white;
            padding: 15px 25px;
            border-radius: 12px;
            transition: all 0.3s;
            font-weight: 500;
        }
        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 124, 0, 0.3);
        }
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .chart-card h5 {
            color: var(--monastery-dark);
            border-left: 4px solid var(--monastery-saffron);
            padding-left: 15px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        .upcoming-appointments {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        .appointment-item {
            padding: 15px;
            border-left: 3px solid var(--monastery-orange);
            background: var(--monastery-pale);
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }
        .appointment-item:hover {
            border-left-width: 5px;
            transform: translateX(5px);
        }
        .lotus-divider {
            text-align: center;
            color: var(--monastery-orange);
            font-size: 24px;
            margin: 20px 0;
        }
        .greeting-time {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .alert-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .alert-item {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }
        .alert-item:hover {
            transform: translateX(5px);
        }
        .alert-item.warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .alert-item.info {
            background: #cfe2ff;
            border-left: 4px solid #0d6efd;
        }
        .alert-item.success {
            background: #d1e7dd;
            border-left: 4px solid #198754;
        }
        .alert-item i {
            font-size: 1.5rem;
            margin-right: 15px;
        }
        .alert-item.warning i { color: #ffc107; }
        .alert-item.info i { color: #0d6efd; }
        .alert-item.success i { color: #198754; }
    </style>
</head>
<body>

<div class="container-fluid mt-4 mb-5 px-4">
    <!-- Welcome Card with Lotus -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="greeting-time">
                    <i class="bi bi-sun"></i> <?= date('l, F j, Y') ?> • <?= date('g:i A') ?>
                </div>
                <h1 class="mb-2">🙏 Ayubowan, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
                <p class="mb-0" style="font-size: 1.1rem;">Welcome to Giribawa Seela Suva Herath Bhikkhu Hospital</p>
                <p class="mb-0 mt-2 opacity-75">
                    <i class="bi bi-award"></i> Role: <?= htmlspecialchars($_SESSION['role_name']) ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div style="font-size: 4rem; opacity: 0.3;">☸️</div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card-premium">
                <div class="icon">
                    <i class="bi bi-people"></i>
                </div>
                <h3><?= $stats['total_monks'] ?></h3>
                <p>Active Monks</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-premium">
                <div class="icon">
                    <i class="bi bi-person-badge"></i>
                </div>
                <h3><?= $stats['total_doctors'] ?></h3>
                <p>Active Doctors</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-premium">
                <div class="icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <h3><?= $stats['total_appointments'] ?></h3>
                <p>Appointments (This Month)</p>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card-premium">
                <div class="icon">
                    <i class="bi bi-clock-history"></i>
                </div>
                <h3><?= $stats['pending_appointments'] ?></h3>
                <p>Pending Appointments</p>
            </div>
        </div>
    </div>

    <div class="lotus-divider">🪷 • 🪷 • 🪷</div>

    <!-- Alerts and Notifications -->
    <div class="row g-4 mb-4">
        <div class="col-md-12">
            <div class="glass-card">
                <h5 style="color: var(--monastery-dark); font-weight: 600; margin-bottom: 20px;">
                    <i class="bi bi-bell"></i> Alerts & Notifications
                </h5>
                <?php foreach ($alerts as $alert): ?>
                    <a href="<?= $alert['link'] ?>" class="alert-item <?= $alert['type'] ?>" style="text-decoration: none; color: inherit;">
                        <i class="<?= $alert['icon'] ?>"></i>
                        <span><?= $alert['message'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="glass-card">
                <h5><i class="bi bi-bar-chart"></i> Weekly Appointment Trend</h5>
                <canvas id="weeklyChart" height="80"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="glass-card">
                <h5 style="color: var(--monastery-dark); font-weight: 600; margin-bottom: 20px;">
                    <i class="bi bi-calendar-day"></i> Today's Appointments
                </h5>
                <?php if (count($today_appointments) > 0): ?>
                    <?php foreach ($today_appointments as $apt): ?>
                        <div class="appointment-item">
                            <div class="d-flex justify-content-between">
                                <strong><?= date('g:i A', strtotime($apt['app_time'])) ?></strong>
                                <span class="badge" style="background: var(--monastery-saffron);">Scheduled</span>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">Monk:</small><br>
                                <strong><?= htmlspecialchars($apt['monk_name']) ?></strong>
                            </div>
                            <div class="mt-1">
                                <small class="text-muted">Doctor:</small><br>
                                <?= htmlspecialchars($apt['doctor_name']) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-2">No appointments scheduled for today</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Financial Chart -->
    <div class="row g-4">
        <div class="col-md-12">
            <div class="glass-card">
                <h5><i class="bi bi-graph-up"></i> Monthly Donations vs Expenses (Last 6 Months)</h5>
                <canvas id="financialChart" height="60"></canvas>
            </div>
        </div>
    </div>

    <div class="lotus-divider">🪷 • 🪷 • 🪷</div>

    <!-- Quick Actions -->
    <div class="quick-actions text-center mb-4">
        <h5 style="color: var(--monastery-dark); margin-bottom: 20px; font-weight: 600;">Quick Actions</h5>
        <div class="row g-3 justify-content-center">
            <div class="col-auto">
                <a href="patient_appointments.php" class="btn-premium btn-primary-premium">
                    <i class="bi bi-calendar-plus"></i> New Appointment
                </a>
            </div>
            <div class="col-auto">
                <a href="donation_management.php" class="btn-premium btn-success-premium">
                    <i class="bi bi-cash-coin"></i> Add Donation
                </a>
            </div>
            <div class="col-auto">
                <a href="bill_management.php" class="btn-premium btn-warning-premium">
                    <i class="bi bi-receipt"></i> Add Expense
                </a>
            </div>
            <div class="col-auto">
                <a href="doctor_availability.php" class="btn-premium btn-info-premium">
                    <i class="bi bi-clock"></i> Doctor Schedule
                </a>
            </div>
            <div class="col-auto">
                <a href="category_management.php" class="btn-premium btn-secondary-premium">
                    <i class="bi bi-tag"></i> Categories
                </a>
            </div>
            <div class="col-auto">
                <a href="room_management.php" class="btn-premium btn-primary-premium">
                    <i class="bi bi-door-open"></i> Room Management
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Weekly Appointments Chart
const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($weekly_data, 'date')) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_column($weekly_data, 'count')) ?>,
            backgroundColor: 'rgba(245, 124, 0, 0.2)',
            borderColor: '#f57c00',
            borderWidth: 3,
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
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
            backgroundColor: '#4caf50',
            borderRadius: 8
        }, {
            label: 'Expenses',
            data: <?= json_encode(array_column($monthly_financial, 'expenses')) ?>,
            backgroundColor: '#f57c00',
            borderRadius: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Initialize Browser Notifications
const monasteryNotifications = new MonasteryNotifications();

// Request permission on page load
window.addEventListener('load', function() {
    monasteryNotifications.requestPermission();
    
    // Start polling for new activities every 30 seconds
    monasteryNotifications.startPolling(30);
});
</script>

<!-- Browser Notifications System -->
<script src="assets/js/notifications.js"></script>

</body>
</html>
<?php $conn->close(); ?>
