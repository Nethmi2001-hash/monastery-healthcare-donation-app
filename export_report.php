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

// Get parameters
$report_type = $_GET['type'] ?? 'financial';
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date = $_GET['end'] ?? date('Y-m-t');

// Set headers for CSV download
$filename = "monastery_report_{$report_type}_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Export based on report type
if ($report_type == 'financial') {
    // Header
    fputcsv($output, ['Giribawa Seela Suva Herath Bhikkhu Hospital']);
    fputcsv($output, ['Financial Report']);
    fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Donations
    fputcsv($output, ['DONATIONS BY CATEGORY']);
    fputcsv($output, ['Category', 'Amount (Rs.)', 'Percentage']);
    
    $total_donations = 0;
    $donations = [];
    
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
            $donations[] = $row;
            $total_donations += $row['total'];
        }
    }
    
    foreach ($donations as $item) {
        $percentage = ($total_donations > 0) ? ($item['total'] / $total_donations) * 100 : 0;
        fputcsv($output, [
            $item['category'],
            number_format($item['total'], 2),
            number_format($percentage, 2) . '%'
        ]);
    }
    
    fputcsv($output, ['TOTAL DONATIONS', number_format($total_donations, 2), '100%']);
    fputcsv($output, []);
    
    // Expenses
    fputcsv($output, ['EXPENSES BY CATEGORY']);
    fputcsv($output, ['Category', 'Amount (Rs.)', 'Percentage']);
    
    $total_expenses = 0;
    $expenses = [];
    
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
            $expenses[] = $row;
            $total_expenses += $row['total'];
        }
    }
    
    foreach ($expenses as $item) {
        $percentage = ($total_expenses > 0) ? ($item['total'] / $total_expenses) * 100 : 0;
        fputcsv($output, [
            $item['category'],
            number_format($item['total'], 2),
            number_format($percentage, 2) . '%'
        ]);
    }
    
    fputcsv($output, ['TOTAL EXPENSES', number_format($total_expenses, 2), '100%']);
    fputcsv($output, []);
    
    // Summary
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Donations', 'Rs. ' . number_format($total_donations, 2)]);
    fputcsv($output, ['Total Expenses', 'Rs. ' . number_format($total_expenses, 2)]);
    fputcsv($output, ['Net Balance', 'Rs. ' . number_format($total_donations - $total_expenses, 2)]);
    
} elseif ($report_type == 'appointments') {
    // Header
    fputcsv($output, ['Giribawa Seela Suva Herath Bhikkhu Hospital']);
    fputcsv($output, ['Appointment Report']);
    fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Statistics
    fputcsv($output, ['APPOINTMENT STATISTICS']);
    fputcsv($output, ['Status', 'Count']);
    
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled
        FROM appointments
        WHERE app_date BETWEEN '$start_date' AND '$end_date'
    ");
    
    if ($result) {
        $stats = $result->fetch_assoc();
        fputcsv($output, ['Total', $stats['total']]);
        fputcsv($output, ['Completed', $stats['completed']]);
        fputcsv($output, ['Scheduled', $stats['scheduled']]);
        fputcsv($output, ['Cancelled', $stats['cancelled']]);
        fputcsv($output, ['No Show', $stats['no_show']]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['APPOINTMENTS BY DOCTOR']);
    fputcsv($output, ['Doctor Name', 'Total Appointments', 'Completed', 'Completion Rate']);
    
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
            $rate = ($row['count'] > 0) ? ($row['completed'] / $row['count']) * 100 : 0;
            fputcsv($output, [
                $row['doctor'],
                $row['count'],
                $row['completed'],
                number_format($rate, 1) . '%'
            ]);
        }
    }
    
} elseif ($report_type == 'donors') {
    // Header
    fputcsv($output, ['Giribawa Seela Suva Herath Bhikkhu Hospital']);
    fputcsv($output, ['Donor Report']);
    fputcsv($output, ['Period: ' . $start_date . ' to ' . $end_date]);
    fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    fputcsv($output, ['TOP DONORS']);
    fputcsv($output, ['Rank', 'Donor Name', 'Email', 'Phone', 'Number of Donations', 'Total Amount (Rs.)']);
    
    $result = $conn->query("
        SELECT donor_name, donor_email, donor_phone, COUNT(*) as donation_count, SUM(amount) as total_amount
        FROM donations
        WHERE created_at BETWEEN '$start_date' AND '$end_date 23:59:59'
        AND status = 'verified'
        GROUP BY donor_name, donor_email, donor_phone
        ORDER BY total_amount DESC
        LIMIT 50
    ");
    
    if ($result) {
        $rank = 1;
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $rank++,
                $row['donor_name'],
                $row['donor_email'],
                $row['donor_phone'],
                $row['donation_count'],
                number_format($row['total_amount'], 2)
            ]);
        }
    }
}

fclose($output);
$conn->close();
exit();
?>
