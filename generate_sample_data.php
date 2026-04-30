<?php
/**
 * Sample Data Generator for Testing Reports
 * Run this ONCE to populate database with test data
 * URL: http://localhost/test/generate_sample_data.php
 */

session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "monastery_healthcare";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>🎯 Generating Sample Data for Testing...</h2>";
echo "<div style='font-family: Arial; padding: 20px; background: #f0f0f0;'>";

// Check if data already exists
$check = $conn->query("SELECT COUNT(*) as count FROM donations WHERE donor_name LIKE 'Sample%'");
$existing = $check->fetch_assoc()['count'];

if ($existing > 0) {
    echo "<p style='color: orange;'>⚠️ Sample data already exists ($existing records). Skipping...</p>";
    echo "<p><a href='reports.php' style='background: #f57c00; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Reports</a></p>";
    echo "</div>";
    exit();
}

echo "<h3>Creating Sample Donations...</h3>";

// Sample donor names
$donors = [
    ['name' => 'Sample Donor - Sunil Perera', 'email' => 'sunil@example.com', 'phone' => '0771234567'],
    ['name' => 'Sample Donor - Kamala Silva', 'email' => 'kamala@example.com', 'phone' => '0772345678'],
    ['name' => 'Sample Donor - Nimal Fernando', 'email' => 'nimal@example.com', 'phone' => '0773456789'],
    ['name' => 'Sample Donor - Priya Jayawardena', 'email' => 'priya@example.com', 'phone' => '0774567890'],
    ['name' => 'Sample Donor - Ruwan Gunasekara', 'email' => 'ruwan@example.com', 'phone' => '0775678901'],
    ['name' => 'Sample Donor - Sanduni Wijesinghe', 'email' => 'sanduni@example.com', 'phone' => '0776789012'],
    ['name' => 'Sample Donor - Ashan Ranatunga', 'email' => 'ashan@example.com', 'phone' => '0777890123'],
    ['name' => 'Sample Donor - Dilini Amarasinghe', 'email' => 'dilini@example.com', 'phone' => '0778901234'],
    ['name' => 'Sample Donor - Kasun Bandara', 'email' => 'kasun@example.com', 'phone' => '0779012345'],
    ['name' => 'Sample Donor - Thilini De Silva', 'email' => 'thilini@example.com', 'phone' => '0770123456']
];

$payment_methods = ['cash', 'bank_transfer', 'payhere'];
$amounts = [500, 1000, 2500, 5000, 10000, 15000, 25000, 50000];

// Get donation categories
$cat_result = $conn->query("SELECT category_id FROM categories WHERE type='donation' LIMIT 1");
$category_id = 1;
if ($cat_result && $cat_result->num_rows > 0) {
    $category_id = $cat_result->fetch_assoc()['category_id'];
}

$donation_count = 0;
$total_donations = 0;

// Generate donations for last 6 months
for ($month = 5; $month >= 0; $month--) {
    $donations_this_month = rand(3, 8);
    
    for ($i = 0; $i < $donations_this_month; $i++) {
        $donor = $donors[array_rand($donors)];
        $amount = $amounts[array_rand($amounts)];
        $method = $payment_methods[array_rand($payment_methods)];
        
        // Random date in the month
        $date = date('Y-m-d H:i:s', strtotime("-$month months -" . rand(1, 28) . " days"));
        
        $stmt = $conn->prepare("INSERT INTO donations (donor_name, donor_email, donor_phone, amount, category_id, payment_method, reference_number, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'verified', 1, ?)");
        
        $ref = 'REF' . rand(100000, 999999);
        $stmt->bind_param("sssdiiss", $donor['name'], $donor['email'], $donor['phone'], $amount, $category_id, $method, $ref, $date);
        
        if ($stmt->execute()) {
            $donation_count++;
            $total_donations += $amount;
        }
        $stmt->close();
    }
}

echo "<p style='color: green;'>✅ Created $donation_count sample donations (Total: Rs. " . number_format($total_donations, 2) . ")</p>";

// Generate sample expenses/bills
echo "<h3>Creating Sample Expenses...</h3>";

$bill_categories = $conn->query("SELECT category_id, name FROM categories WHERE type='bill' LIMIT 5");
$bill_cats = [];
if ($bill_categories) {
    while ($row = $bill_categories->fetch_assoc()) {
        $bill_cats[] = $row;
    }
}

if (count($bill_cats) == 0) {
    // Create sample bill category if none exist
    $conn->query("INSERT INTO categories (name, type) VALUES ('Medical Supplies', 'bill')");
    $bill_cats[] = ['category_id' => $conn->insert_id, 'name' => 'Medical Supplies'];
}

$bill_descriptions = [
    'Electricity Bill',
    'Water Bill',
    'Medical Supplies Purchase',
    'Food Supplies',
    'Maintenance Services',
    'Cleaning Supplies',
    'Office Stationery',
    'Vehicle Fuel'
];

$vendors = ['ABC Suppliers', 'XYZ Company', 'Local Mart', 'Medical Store', 'Utility Board'];

$bill_count = 0;
$total_bills = 0;

// Generate bills for last 6 months
for ($month = 5; $month >= 0; $month--) {
    $bills_this_month = rand(2, 6);
    
    for ($i = 0; $i < $bills_this_month; $i++) {
        $description = $bill_descriptions[array_rand($bill_descriptions)];
        $amount = rand(2000, 30000);
        $bill_cat = $bill_cats[array_rand($bill_cats)];
        $vendor_name = $vendors[array_rand($vendors)];
        
        $bill_date = date('Y-m-d', strtotime("-$month months -" . rand(1, 28) . " days"));
        
        $stmt = $conn->prepare("INSERT INTO bills (description, amount, category_id, bill_date, vendor_name, invoice_number, status, created_by, paid_by, paid_date) VALUES (?, ?, ?, ?, ?, ?, 'paid', 1, 1, ?)");
        
        $invoice = 'INV' . rand(1000, 9999);
        $stmt->bind_param("sdissss", $description, $amount, $bill_cat['category_id'], $bill_date, $vendor_name, $invoice, $bill_date);
        
        if ($stmt->execute()) {
            $bill_count++;
            $total_bills += $amount;
        }
        $stmt->close();
    }
}

echo "<p style='color: green;'>✅ Created $bill_count sample bills/expenses (Total: Rs. " . number_format($total_bills, 2) . ")</p>";

// Generate sample appointments
echo "<h3>Creating Sample Appointments...</h3>";

$doctors = $conn->query("SELECT doctor_id, full_name FROM doctors WHERE status='active' LIMIT 3");
$doctor_list = [];
if ($doctors) {
    while ($row = $doctors->fetch_assoc()) {
        $doctor_list[] = $row;
    }
}

$monks = $conn->query("SELECT monk_id, full_name FROM monks WHERE status='active' LIMIT 5");
$monk_list = [];
if ($monks) {
    while ($row = $monks->fetch_assoc()) {
        $monk_list[] = $row;
    }
}

$statuses = ['completed', 'completed', 'completed', 'cancelled', 'no_show'];
$appointment_count = 0;

if (count($doctor_list) > 0 && count($monk_list) > 0) {
    for ($month = 3; $month >= 0; $month--) {
        $appointments_this_month = rand(5, 12);
        
        for ($i = 0; $i < $appointments_this_month; $i++) {
            $doctor = $doctor_list[array_rand($doctor_list)];
            $monk = $monk_list[array_rand($monk_list)];
            $status = $statuses[array_rand($statuses)];
            
            $app_date = date('Y-m-d', strtotime("-$month months -" . rand(1, 28) . " days"));
            $app_time = sprintf("%02d:00:00", rand(8, 16));
            
            $stmt = $conn->prepare("INSERT INTO appointments (monk_id, doctor_id, app_date, app_time, reason, status, created_by) VALUES (?, ?, ?, ?, 'Regular Checkup', ?, 1)");
            $stmt->bind_param("iisss", $monk['monk_id'], $doctor['doctor_id'], $app_date, $app_time, $status);
            
            if ($stmt->execute()) {
                $appointment_count++;
            }
            $stmt->close();
        }
    }
    
    echo "<p style='color: green;'>✅ Created $appointment_count sample appointments</p>";
} else {
    echo "<p style='color: orange;'>⚠️ No doctors or monks found. Please add some first.</p>";
}

echo "<hr>";
echo "<h3 style='color: #28a745;'>✅ Sample Data Generation Complete!</h3>";

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h4>📊 Summary:</h4>";
echo "<ul>";
echo "<li><strong>Donations:</strong> $donation_count records (Rs. " . number_format($total_donations, 2) . ")</li>";
echo "<li><strong>Expenses:</strong> $bill_count records (Rs. " . number_format($total_bills, 2) . ")</li>";
echo "<li><strong>Net Balance:</strong> Rs. " . number_format($total_donations - $total_bills, 2) . "</li>";
echo "<li><strong>Appointments:</strong> $appointment_count records</li>";
echo "</ul>";
echo "</div>";

echo "<div style='margin: 20px 0;'>";
echo "<h4>🎯 What to Do Next:</h4>";
echo "<ol>";
echo "<li>Go to <strong>Reports</strong> to see beautiful charts with this data</li>";
echo "<li>Try <strong>CSV Export</strong> to download the data</li>";
echo "<li>Test <strong>Date Range</strong> filters</li>";
echo "<li>View all 3 report types</li>";
echo "</ol>";
echo "</div>";

echo "<div style='margin: 30px 0;'>";
echo "<a href='reports.php' style='background: linear-gradient(135deg, #f57c00, #ff9800); color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-size: 18px; display: inline-block; margin-right: 10px;'>📊 View Reports Now</a>";
echo "<a href='dashboard.php' style='background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 15px 30px; text-decoration: none; border-radius: 10px; font-size: 18px; display: inline-block;'>🏠 Go to Dashboard</a>";
echo "</div>";

echo "<hr>";
echo "<p style='color: #666;'><small>Note: This sample data is marked with 'Sample' prefix and can be deleted later from the database if needed.</small></p>";

echo "</div>";

$conn->close();
?>
