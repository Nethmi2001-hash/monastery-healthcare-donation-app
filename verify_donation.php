<?php
/**
 * Donation Receipt Verification Page
 * Scans QR code to verify donation authenticity
 */
require_once __DIR__ . '/includes/db_config.php';

$con = getDBConnection();

$donation_id = (int)($_GET['id'] ?? 0);
$hash = $_GET['hash'] ?? '';

$donation = null;
$valid = false;

if ($donation_id > 0) {
    $stmt = $con->prepare("SELECT d.*, c.name AS category_name, u.name AS verified_by_name
                           FROM donations d
                           LEFT JOIN categories c ON d.category_id = c.category_id
                           LEFT JOIN users u ON d.verified_by = u.user_id
                           WHERE d.donation_id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();
    
    // Verify hash
    if ($donation && md5($donation_id . $donation['amount']) === $hash) {
        $valid = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Donation - Seela Suwa Herath</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --monastery-saffron: #f57c00;
            --monastery-orange: #ff9800;
        }
        body {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            padding: 50px 0;
        }
        .verify-card {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        .verify-header {
            padding: 30px;
            text-align: center;
            color: white;
        }
        .verify-header.valid {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        .verify-header.invalid {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        }
        .verify-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }
        .detail-row {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }
        .detail-label {
            font-weight: 600;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="verify-card">
        <?php if ($valid && $donation): ?>
            <div class="verify-header valid">
                <div class="verify-icon">✅</div>
                <h2>Verified Donation</h2>
                <p class="mb-0">This donation receipt is authentic</p>
            </div>
            <div class="p-4">
                <div class="detail-row">
                    <span class="detail-label">Receipt ID:</span>
                    <span class="detail-value">#DON-<?= str_pad($donation['donation_id'], 6, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Donor Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($donation['donor_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value" style="font-size: 1.3rem; color: var(--monastery-saffron); font-weight: bold;">
                        Rs. <?= number_format($donation['amount'], 2) ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Category:</span>
                    <span class="detail-value"><?= htmlspecialchars($donation['category_name']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment Method:</span>
                    <span class="detail-value"><?= strtoupper($donation['method']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value"><?= date('F j, Y', strtotime($donation['created_at'])) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value">
                        <span class="badge bg-success"><?= strtoupper($donation['status']) ?></span>
                    </span>
                </div>
                <?php if ($donation['verified_by']): ?>
                <div class="detail-row">
                    <span class="detail-label">Verified By:</span>
                    <span class="detail-value"><?= htmlspecialchars($donation['verified_by_name']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="alert alert-success mt-4">
                    <i class="bi bi-shield-check"></i> <strong>Authentic Receipt</strong><br>
                    This donation has been recorded in our system and is verified as genuine.
                </div>
            </div>
        <?php else: ?>
            <div class="verify-header invalid">
                <div class="verify-icon">❌</div>
                <h2>Invalid Receipt</h2>
                <p class="mb-0">This QR code could not be verified</p>
            </div>
            <div class="p-4">
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <strong>Warning!</strong><br>
                    This receipt could not be verified. It may be:
                    <ul class="mb-0 mt-2">
                        <li>Fraudulent or fake</li>
                        <li>Modified after issuance</li>
                        <li>Not yet processed in our system</li>
                    </ul>
                </div>
                <p class="text-center">
                    <a href="public_donate.php" class="btn btn-primary">Make a Verified Donation</a>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="text-center p-3 border-top">
            <small class="text-muted">
                Seela Suwa Herath Bikshu Gilan Arana<br>
                QR Code Verification System
            </small>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $con->close(); ?>
