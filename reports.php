<?php
session_start();

// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once __DIR__ . '/includes/db_config.php';
$conn = getDBConnection();

// Get date range from request or default to current month
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$report_type = $_GET['report_type'] ?? 'financial';

// Financial Report Data
$donations_by_category = [];
$expenses_by_category = [];
$total_donations = 0;
$total_expenses = 0;

if ($report_type == 'financial') {
    // Donations by category
    $result = $conn->query("
        SELECT c.name as category, SUM(d.amount) as total
        FROM donations d
        JOIN categories c ON d.category_id = c.category_id
        WHERE d.created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        AND d.status = 'verified'
        GROUP BY c.category_id, c.name
        ORDER BY total DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $donations_by_category[] = $row;
            $total_donations += $row['total'];
        }
    }

    // Expenses by category
    $result = $conn->query("
        SELECT c.name as category, SUM(b.amount) as total
        FROM bills b
        JOIN categories c ON b.category_id = c.category_id
        WHERE b.bill_date BETWEEN '$start_date' AND '$end_date'
            AND b.status = 'paid'
        GROUP BY c.category_id, c.name
        ORDER BY total DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $expenses_by_category[] = $row;
            $total_expenses += $row['total'];
        }
    }
}

// Appointment Statistics
$appointment_stats = [
    'total' => 0,
    'completed' => 0,
    'cancelled' => 0,
    'no_show' => 0,
    'by_doctor' => []
];

if ($report_type == 'appointments') {
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show
        FROM appointments
        WHERE app_date BETWEEN '$start_date' AND '$end_date'
    ");
    if ($result) {
        $appointment_stats = array_merge($appointment_stats, $result->fetch_assoc());
    }

    // By doctor
    $result = $conn->query("
        SELECT d.full_name as doctor, COUNT(*) as count,
               SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM appointments a
        JOIN doctors d ON a.doctor_id = d.doctor_id
        WHERE a.app_date BETWEEN '$start_date' AND '$end_date'
        GROUP BY d.doctor_id, d.full_name
        ORDER BY count DESC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $appointment_stats['by_doctor'][] = $row;
        }
    }
}

// Donor Report
$top_donors = [];
if ($report_type == 'donors') {
    $result = $conn->query("
        SELECT donor_name, donor_email, COUNT(*) as donation_count, SUM(amount) as total_amount
        FROM donations
        WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        AND status = 'verified'
        GROUP BY donor_name, donor_email
        ORDER BY total_amount DESC
        LIMIT 20
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $top_donors[] = $row;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<?php include 'navbar.php'; ?>

    <div class="app-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1"><i class="bi bi-graph-up-arrow"></i> Reports &amp; Analytics</h1>
                <p class="text-muted mb-0">Comprehensive financial and operational reports</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn-modern" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Report
                </button>
                <button class="btn-modern btn-primary-modern" onclick="exportToCSV()">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Date Range Selector -->
        <div class="modern-table-wrapper mb-4">
            <div class="modern-table-header">
                <h5 class="mb-0"><i class="bi bi-funnel"></i> Filter Options</h5>
            </div>
            <div class="p-3">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="bi bi-calendar-event"></i> Start Date</label>
                        <input type="date" name="start_date" class="form-control-modern" value="<?= htmlspecialchars($start_date) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold"><i class="bi bi-calendar-check"></i> End Date</label>
                        <input type="date" name="end_date" class="form-control-modern" value="<?= htmlspecialchars($end_date) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold"><i class="bi bi-file-earmark-bar-graph"></i> Report Type</label>
                        <select name="report_type" class="form-control-modern">
                            <option value="financial" <?= $report_type == 'financial' ? 'selected' : '' ?>>Financial Summary</option>
                            <option value="appointments" <?= $report_type == 'appointments' ? 'selected' : '' ?>>Appointment Statistics</option>
                            <option value="donors" <?= $report_type == 'donors' ? 'selected' : '' ?>>Donor Report</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn-modern btn-primary-modern w-100">
                            <i class="bi bi-search"></i> Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Financial Report -->
        <?php if ($report_type == 'financial'): ?>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon emerald"><i class="bi bi-cash-coin"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Total Donations</span>
                            <span class="stat-value">Rs. <?= number_format($total_donations, 2) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon rose"><i class="bi bi-receipt"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Total Expenses</span>
                            <span class="stat-value">Rs. <?= number_format($total_expenses, 2) ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-icon <?= ($total_donations - $total_expenses) >= 0 ? 'blue' : 'amber' ?>">
                            <i class="bi bi-<?= ($total_donations - $total_expenses) >= 0 ? 'graph-up' : 'graph-down' ?>"></i>
                        </div>
                        <div class="stat-info">
                            <span class="stat-label">Net Balance <?= ($total_donations - $total_expenses) >= 0 ? '(Surplus)' : '(Deficit)' ?></span>
                            <span class="stat-value">Rs. <?= number_format($total_donations - $total_expenses, 2) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Donations by Category -->
                <div class="col-md-6">
                    <div class="modern-table-wrapper">
                        <div class="modern-table-header">
                            <h5 class="mb-0"><i class="bi bi-cash-coin"></i> Donations by Category</h5>
                        </div>
                        <?php if (count($donations_by_category) > 0): ?>
                            <div class="p-3">
                                <canvas id="donationsChart" height="250"></canvas>
                            </div>
                            <div class="table-responsive-modern">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($donations_by_category as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['category']) ?></td>
                                                <td class="text-end">Rs. <?= number_format($item['total'], 2) ?></td>
                                                <td class="text-end"><?= number_format(($item['total'] / $total_donations) * 100, 1) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">No donation data for selected period</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Expenses by Category -->
                <div class="col-md-6">
                    <div class="modern-table-wrapper">
                        <div class="modern-table-header">
                            <h5 class="mb-0"><i class="bi bi-receipt"></i> Expenses by Category</h5>
                        </div>
                        <?php if (count($expenses_by_category) > 0): ?>
                            <div class="p-3">
                                <canvas id="expensesChart" height="250"></canvas>
                            </div>
                            <div class="table-responsive-modern">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="text-end">Amount</th>
                                            <th class="text-end">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expenses_by_category as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['category']) ?></td>
                                                <td class="text-end">Rs. <?= number_format($item['total'], 2) ?></td>
                                                <td class="text-end"><?= number_format(($item['total'] / $total_expenses) * 100, 1) ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">No expense data for selected period</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Appointment Report -->
        <?php if ($report_type == 'appointments'): ?>
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="bi bi-calendar-check"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Total Appointments</span>
                            <span class="stat-value"><?= $appointment_stats['total'] ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon emerald"><i class="bi bi-check-circle"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Completed</span>
                            <span class="stat-value"><?= $appointment_stats['completed'] ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon amber"><i class="bi bi-x-circle"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">Cancelled</span>
                            <span class="stat-value"><?= $appointment_stats['cancelled'] ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon rose"><i class="bi bi-exclamation-triangle"></i></div>
                        <div class="stat-info">
                            <span class="stat-label">No Show</span>
                            <span class="stat-value"><?= $appointment_stats['no_show'] ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modern-table-wrapper">
                <div class="modern-table-header">
                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Appointments by Doctor</h5>
                </div>
                <?php if (count($appointment_stats['by_doctor']) > 0): ?>
                    <div class="table-responsive-modern">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Doctor Name</th>
                                    <th class="text-center">Total Appointments</th>
                                    <th class="text-center">Completed</th>
                                    <th class="text-center">Completion Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointment_stats['by_doctor'] as $doctor): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($doctor['doctor']) ?></td>
                                        <td class="text-center"><?= $doctor['count'] ?></td>
                                        <td class="text-center"><?= $doctor['completed'] ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $rate = ($doctor['count'] > 0) ? ($doctor['completed'] / $doctor['count']) * 100 : 0;
                                            ?>
                                            <div class="progress" role="progressbar">
                                                <div class="progress-bar bg-success" style="width: <?= $rate ?>%">
                                                    <?= number_format($rate, 0) ?>%
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No appointment data for selected period</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Donor Report -->
        <?php if ($report_type == 'donors'): ?>
            <div class="modern-table-wrapper">
                <div class="modern-table-header">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Top Donors</h5>
                </div>
                <?php if (count($top_donors) > 0): ?>
                    <div class="table-responsive-modern">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Donor Name</th>
                                    <th>Email</th>
                                    <th class="text-center">Donations</th>
                                    <th class="text-end">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_donors as $index => $donor): ?>
                                    <tr>
                                        <td>
                                            <?php if ($index < 3): ?>
                                                <span class="badge-modern amber">#<?= $index + 1 ?></span>
                                            <?php else: ?>
                                                #<?= $index + 1 ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($donor['donor_name']) ?></td>
                                        <td><?= htmlspecialchars($donor['donor_email']) ?></td>
                                        <td class="text-center"><?= $donor['donation_count'] ?></td>
                                        <td class="text-end"><strong>Rs. <?= number_format($donor['total_amount'], 2) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No donor data for selected period</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Donations Chart
<?php if ($report_type == 'financial' && count($donations_by_category) > 0): ?>
const donationsCtx = document.getElementById('donationsChart').getContext('2d');
new Chart(donationsCtx, {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($donations_by_category, 'category')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($donations_by_category, 'total')) ?>,
            backgroundColor: [
                '#f97316',
                '#ea580c',
                '#fb923c',
                '#c2410c',
                '#fdba74',
                '#9a3412'
            ],
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 15
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 13,
                        weight: '600'
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                cornerRadius: 8,
                titleFont: {
                    size: 14,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 13
                }
            }
        }
    }
});
<?php endif; ?>

// Expenses Chart
<?php if ($report_type == 'financial' && count($expenses_by_category) > 0): ?>
const expensesCtx = document.getElementById('expensesChart').getContext('2d');
new Chart(expensesCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($expenses_by_category, 'category')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($expenses_by_category, 'total')) ?>,
            backgroundColor: [
                '#fb923c',
                '#f97316',
                '#ea580c',
                '#fdba74',
                '#c2410c',
                '#9a3412'
            ],
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 15
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 13,
                        weight: '600'
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                cornerRadius: 8,
                titleFont: {
                    size: 14,
                    weight: 'bold'
                },
                bodyFont: {
                    size: 13
                }
            }
        }
    }
});
<?php endif; ?>

// Export to CSV
function exportToCSV() {
    const reportType = '<?= $report_type ?>';
    const startDate = '<?= $start_date ?>';
    const endDate = '<?= $end_date ?>';
    
    window.location.href = `export_report.php?type=${reportType}&start=${startDate}&end=${endDate}`;
}

// Print styles
window.onbeforeprint = function() {
    document.body.style.background = 'white';
};
</script>

</body>
</html>
