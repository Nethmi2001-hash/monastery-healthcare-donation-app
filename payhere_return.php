<?php
/**
 * PayHere Return URL (Success Page)
 * User is redirected here after successful payment
 */
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful - Seela Suwa Herath Bikshu Gilan Arana</title>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --monastery-saffron: #f57c00;
            --monastery-orange: #ff9800;
        }
        body {
            background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 600px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(245, 124, 0, 0.2);
        }
        .success-icon {
            font-size: 5rem;
            color: #28a745;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="success-container">
    <div class="success-icon">
        <i class="bi bi-check-circle-fill"></i>
    </div>
    
    <h2 style="color: var(--monastery-saffron);">🙏 Theruwan Saranai!</h2>
    <h4 class="mt-3">Payment Successful</h4>
    
    <p class="text-muted mt-4">
        Thank you for your generous donation to<br>
        <strong>Seela Suwa Herath Bikshu Gilan Arana</strong>
    </p>

    <?php if (isset($_GET['order_id'])): ?>
        <div class="alert alert-info mt-4">
            <strong>Order ID:</strong> <?= htmlspecialchars($_GET['order_id']) ?>
        </div>
    <?php endif; ?>

    <p class="mt-3">
        <small class="text-muted">
            A confirmation email has been sent to you.<br>
            Your donation will be used for healthcare services for monks.
        </small>
    </p>

    <div class="mt-4">
        <a href="donation_management.php" class="btn btn-primary me-2">
            <i class="bi bi-list"></i> View Donations
        </a>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="bi bi-house"></i> Go to Dashboard
        </a>
    </div>

    <div class="mt-4">
        <small class="text-muted">🪷 May you be blessed with good health and happiness 🪷</small>
    </div>
</div>

<script>
// Auto redirect after 10 seconds
setTimeout(function() {
    window.location.href = 'donation_management.php';
}, 10000);
</script>

</body>
</html>
