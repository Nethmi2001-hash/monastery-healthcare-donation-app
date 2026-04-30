<!DOCTYPE html>
<html>
<head>
    <title>PayHere Payment - Seela Suwa Herath Bikshu Gilan Arana</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- PayHere SDK -->
    <script type="text/javascript" src="https://www.payhere.lk/lib/payhere.js"></script>
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
        .payment-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(245, 124, 0, 0.2);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--monastery-saffron) 0%, var(--monastery-orange) 100%);
            border: none;
        }
    </style>
</head>
<body>

<div class="payment-container">
    <div class="text-center mb-4">
        <h2 style="color: var(--monastery-saffron);">🪷 Make a Donation</h2>
        <p class="text-muted">Seela Suwa Herath Bikshu Gilan Arana</p>
    </div>

    <form id="donationForm">
        <div class="mb-3">
            <label class="form-label">Your Name</label>
            <input type="text" id="donor_name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" id="donor_email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" id="donor_phone" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Donation Amount (Rs.)</label>
            <input type="number" id="amount" class="form-control" min="100" step="0.01" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Purpose (Optional)</label>
            <textarea id="purpose" class="form-control" rows="2"></textarea>
        </div>

        <div class="alert alert-info">
            <small>
                <strong>Sandbox Mode:</strong> This is a test environment. Use test cards:<br>
                • Visa: 4111 1111 1111 1111<br>
                • MasterCard: 5555 5555 5555 4444<br>
                • CVV: Any 3 digits | Expiry: Any future date
            </small>
        </div>

        <button type="button" class="btn btn-primary btn-lg w-100" onclick="payWithPayHere()">
            <i class="bi bi-credit-card"></i> Pay with PayHere
        </button>
    </form>

    <div class="text-center mt-3">
        <a href="donation_management.php" class="text-muted">
            <i class="bi bi-arrow-left"></i> Back to Donation Management
        </a>
    </div>
</div>

<?php
require_once 'includes/payhere_config.php';
?>

<script>
// PayHere Configuration from centralized config
const MERCHANT_ID = "<?= PAYHERE_MERCHANT_ID ?>";
const SANDBOX_MODE = <?= PAYHERE_SANDBOX_MODE ? 'true' : 'false' ?>;
const RETURN_URL = "<?= PAYHERE_RETURN_URL ?>";
const CANCEL_URL = "<?= PAYHERE_CANCEL_URL ?>";
const NOTIFY_URL = "<?= PAYHERE_NOTIFY_URL ?>";

function payWithPayHere() {
    // Get form values
    const donor_name = document.getElementById('donor_name').value.trim();
    const donor_email = document.getElementById('donor_email').value.trim();
    const donor_phone = document.getElementById('donor_phone').value.trim();
    const amount = parseFloat(document.getElementById('amount').value);
    const purpose = document.getElementById('purpose').value.trim();

    // Validate
    if (!donor_name || !donor_email || !donor_phone || !amount) {
        alert('Please fill all required fields');
        return;
    }

    if (amount < 100) {
        alert('Minimum donation amount is Rs. 100');
        return;
    }

    // Generate unique order ID
    const order_id = 'DON' + Date.now();

    // PayHere Payment Object
    const payment = {
        sandbox: SANDBOX_MODE,
        merchant_id: MERCHANT_ID,
        return_url: RETURN_URL,
        cancel_url: CANCEL_URL,
        notify_url: NOTIFY_URL,
        order_id: order_id,
        items: purpose || "General Donation",
        amount: amount.toFixed(2),
        currency: "LKR",
        first_name: donor_name.split(' ')[0],
        last_name: donor_name.split(' ').slice(1).join(' ') || '-',
        email: donor_email,
        phone: donor_phone,
        address: "Monastery",
        city: "Colombo",
        country: "Sri Lanka",
        delivery_address: "N/A",
        delivery_city: "Colombo",
        delivery_country: "Sri Lanka",
        custom_1: "",  // Can store category_id
        custom_2: ""   // Can store additional info
    };

    // Show PayHere payment window
    payhere.startPayment(payment);
}

// PayHere event handlers
payhere.onCompleted = function onCompleted(orderId) {
    console.log("Payment completed. OrderID:", orderId);
    
    // Show success message
    alert('Payment Successful!\n\nThank you for your donation.\n\nOrder ID: ' + orderId + '\n\nA confirmation email will be sent to you.');
    
    // Redirect to donation management with success message
    window.location.href = 'donation_management.php?payment=success&order_id=' + orderId;
};

payhere.onDismissed = function onDismissed() {
    console.log("Payment dismissed");
    alert('Payment was cancelled.');
};

payhere.onError = function onError(error) {
    console.log("Payment Error:", error);
    alert('Payment Error: ' + error);
};
</script>

</body>
</html>
