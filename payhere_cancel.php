<?php
/**
 * PayHere Cancel URL
 * User is redirected here if they cancel the payment
 */
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Cancelled - Seela Suwa Herath Bikshu Gilan Arana</title>
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
        .cancel-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 600px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(245, 124, 0, 0.2);
        }
        .cancel-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="cancel-container">
    <div class="cancel-icon">
        <i class="bi bi-x-circle-fill"></i>
    </div>
    
    <h2 style="color: var(--monastery-dark);">Payment Cancelled</h2>
    
    <p class="text-muted mt-4">
        Your payment was cancelled.<br>
        No charges have been made to your account.
    </p>

    <div class="alert alert-info mt-4">
        <small>
            If you experienced any issues or would like to try again,<br>
            please contact us or use alternative payment methods (Bank Transfer, Cash).
        </small>
    </div>

    <div class="mt-4">
        <a href="payhere_checkout.php" class="btn btn-primary me-2">
            <i class="bi bi-arrow-repeat"></i> Try Again
        </a>
        <a href="donation_management.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to Donations
        </a>
    </div>

    <div class="mt-4">
        <small class="text-muted">🪷 We appreciate your support 🪷</small>
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
