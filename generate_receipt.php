<?php
/**
 * PDF Receipt Generator for Donations
 * Uses FPDF library (lightweight alternative to TCPDF)
 * Download FPDF from: http://www.fpdf.org/en/download.php
 * Or use this embedded minimal version
 */

session_start();

// Check if donation_id is provided
if (!isset($_GET['id'])) {
    die("Donation ID is required");
}

$donation_id = intval($_GET['id']);

// Database connection
require_once __DIR__ . '/includes/db_config.php';
require_once __DIR__ . '/includes/qrcode_helper.php';

$conn = getDBConnection();

// Get donation details
$stmt = $conn->prepare("
    SELECT d.*, c.name as category_name 
    FROM donations d 
    LEFT JOIN categories c ON d.category_id = c.category_id 
    WHERE d.donation_id = ?
");
$stmt->bind_param("i", $donation_id);
$stmt->execute();
$result = $stmt->get_result();
$donation = $result->fetch_assoc();

if (!$donation) {
    die("Donation not found");
}

$stmt->close();

// Keep connection open for QR code generation
// Set global $con for qrcode_helper.php compatibility
$GLOBALS['con'] = $conn;

// Check if FPDF library exists
$fpdf_path = __DIR__ . '/fpdf/fpdf.php';
if (!file_exists($fpdf_path)) {
    // Use HTML fallback if FPDF not installed
    generateHTMLReceipt($donation);
    exit;
}

require($fpdf_path);

// Generate PDF Receipt
class PDF extends FPDF
{
    function Header()
    {
        // Monastery Logo/Header
        $this->SetFillColor(245, 124, 0); // Saffron color
        $this->Rect(0, 0, 210, 30, 'F');
        
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 15, 'Seela Suwa Herath Bikshu Gilan Arana', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 8, 'Healthcare & Donation Management System', 0, 1, 'C');
        $this->Ln(10);
        
        $this->SetTextColor(0, 0, 0);
    }
    
    function Footer()
    {
        $this->SetY(-25);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'Thank you for your generous donation!', 0, 1, 'C');
        $this->Cell(0, 5, 'May you be blessed with good health and happiness', 0, 1, 'C');
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, 'Generated on ' . date('Y-m-d H:i:s'), 0, 0, 'C');
    }
}

// Create PDF
$pdf = new PDF();
$pdf->AddPage();

// Receipt Title
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetTextColor(245, 124, 0);
$pdf->Cell(0, 10, 'DONATION RECEIPT', 0, 1, 'C');
$pdf->Ln(5);

// Receipt Number
$pdf->SetFont('Arial', '', 12);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 8, 'Receipt No: DON-' . str_pad($donation['donation_id'], 6, '0', STR_PAD_LEFT), 0, 1, 'C');
$pdf->Cell(0, 8, 'Date: ' . date('F d, Y', strtotime($donation['created_at'])), 0, 1, 'C');
$pdf->Ln(10);

// Donor Information
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetFillColor(255, 243, 224);
$pdf->Cell(0, 10, 'Donor Information', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 8, 'Name:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, $donation['donor_name'], 0, 1);

if ($donation['donor_email']) {
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(50, 8, 'Email:', 0, 0);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $donation['donor_email'], 0, 1);
}

if ($donation['donor_phone']) {
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(50, 8, 'Phone:', 0, 0);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $donation['donor_phone'], 0, 1);
}
$pdf->Ln(5);

// Donation Details
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetFillColor(255, 243, 224);
$pdf->Cell(0, 10, 'Donation Details', 0, 1, 'L', true);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 8, 'Category:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, $donation['category_name'], 0, 1);

$payment_method = $donation['payment_method'] ?? 'bank_transfer';
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 8, 'Payment Method:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, strtoupper(str_replace('_', ' ', $payment_method)), 0, 1);

$reference_number = $donation['reference_number'] ?? '';
if ($reference_number) {
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(50, 8, 'Reference:', 0, 0);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, $reference_number, 0, 1);
}

$pdf->SetFont('Arial', '', 12);
$pdf->Cell(50, 8, 'Status:', 0, 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(40, 167, 69);
$pdf->Cell(0, 8, strtoupper($donation['status']), 0, 1);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(5);

// Amount Box
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetFillColor(245, 124, 0);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 12, 'TOTAL AMOUNT', 0, 1, 'C', true);

$pdf->SetFont('Arial', 'B', 24);
$pdf->SetTextColor(245, 124, 0);
$pdf->Cell(0, 15, 'Rs. ' . number_format($donation['amount'], 2), 0, 1, 'C');
$pdf->Ln(10);

// Notes
if ($donation['notes']) {
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'Notes:', 0, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->MultiCell(0, 6, $donation['notes']);
    $pdf->Ln(5);
}

// QR Code for verification - download image first for FPDF compatibility
$qr_code_url = generateDonationQR($donation['donation_id']);
if ($qr_code_url) {
    // Download QR image to a temp file so FPDF can read it
    $qr_image_data = @file_get_contents($qr_code_url);
    if ($qr_image_data) {
        $qr_temp_file = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
        file_put_contents($qr_temp_file, $qr_image_data);
        
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, 'Scan to Verify Receipt:', 0, 1, 'C');
        
        // Add QR code image from temp file
        $pdf->Image($qr_temp_file, 75, $pdf->GetY() + 5, 60, 60, 'PNG');
        $pdf->Ln(65);
        
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->SetTextColor(128, 128, 128);
        $pdf->Cell(0, 5, 'Scan this QR code with any smartphone to verify this receipt online', 0, 1, 'C');
        
        // Clean up temp file
        @unlink($qr_temp_file);
    }
}

// Tax Deduction Notice
$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 10);
$pdf->SetTextColor(128, 128, 128);
$pdf->MultiCell(0, 5, 'This receipt may be used for tax deduction purposes. Please consult your tax advisor for eligibility. All donations are used for healthcare services and monastery maintenance.');

// Output PDF
$pdf->Output('D', 'Donation_Receipt_' . $donation['donation_id'] . '.pdf');

// HTML Fallback function
function generateHTMLReceipt($donation) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Donation Receipt</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            @media print {
                .no-print { display: none; }
            }
            .receipt-container {
                max-width: 800px;
                margin: 30px auto;
                padding: 40px;
                border: 2px solid #f57c00;
                border-radius: 10px;
                background: white;
            }
            .header {
                background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%);
                color: white;
                padding: 30px;
                text-align: center;
                border-radius: 10px;
                margin-bottom: 30px;
            }
            .amount-box {
                background: linear-gradient(135deg, #f57c00 0%, #ff9800 100%);
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 10px;
                margin: 20px 0;
            }
            .section-title {
                background: #fff3e0;
                padding: 10px;
                margin: 20px 0 10px 0;
                font-weight: bold;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="receipt-container">
                <div class="header">
                    <h2>🪷 Seela Suwa Herath Bikshu Gilan Arana</h2>
                    <p>Healthcare & Donation Management System</p>
                </div>
                
                <h3 class="text-center" style="color: #f57c00;">DONATION RECEIPT</h3>
                <p class="text-center">
                    <strong>Receipt No:</strong> DON-<?= str_pad($donation['donation_id'], 6, '0', STR_PAD_LEFT) ?><br>
                    <strong>Date:</strong> <?= date('F d, Y', strtotime($donation['created_at'])) ?>
                </p>
                
                <div class="section-title">Donor Information</div>
                <p>
                    <strong>Name:</strong> <?= htmlspecialchars($donation['donor_name']) ?><br>
                    <?php if ($donation['donor_email']): ?>
                        <strong>Email:</strong> <?= htmlspecialchars($donation['donor_email']) ?><br>
                    <?php endif; ?>
                    <?php if ($donation['donor_phone']): ?>
                        <strong>Phone:</strong> <?= htmlspecialchars($donation['donor_phone']) ?>
                    <?php endif; ?>
                </p>
                
                <div class="section-title">Donation Details</div>
                <p>
                    <strong>Category:</strong> <?= htmlspecialchars($donation['category_name']) ?><br>
                    <strong>Payment Method:</strong> <?= strtoupper(str_replace('_', ' ', $donation['payment_method'] ?? 'bank_transfer')) ?><br>
                    <?php if (!empty($donation['reference_number'])): ?>
                        <strong>Reference:</strong> <?= htmlspecialchars($donation['reference_number']) ?><br>
                    <?php endif; ?>
                    <strong>Status:</strong> <span class="badge bg-success"><?= strtoupper($donation['status']) ?></span>
                </p>
                
                <div class="amount-box">
                    <h4>TOTAL AMOUNT</h4>
                    <h2>Rs. <?= number_format($donation['amount'], 2) ?></h2>
                </div>
                
                <?php if ($donation['notes']): ?>
                    <div class="section-title">Notes</div>
                    <p><?= htmlspecialchars($donation['notes']) ?></p>
                <?php endif; ?>
                
                <div class="text-center text-muted mt-4">
                    <small>
                        <em>Thank you for your generous donation!</em><br>
                        May you be blessed with good health and happiness 🪷<br><br>
                        This receipt may be used for tax deduction purposes.<br>
                        Generated on <?= date('Y-m-d H:i:s') ?>
                    </small>
                </div>
                
                <div class="text-center mt-4 no-print">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="bi bi-printer"></i> Print Receipt
                    </button>
                    <a href="donation_management.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Donations
                    </a>
                </div>
            </div>
        </div>
        
        <div class="alert alert-warning no-print m-4">
            <strong>Note:</strong> FPDF library is not installed. 
            To generate PDF receipts, download FPDF from 
            <a href="http://www.fpdf.org" target="_blank">http://www.fpdf.org</a> 
            and extract to <code>/fpdf/</code> folder.
        </div>
    </body>
    </html>
    <?php
}
?>
