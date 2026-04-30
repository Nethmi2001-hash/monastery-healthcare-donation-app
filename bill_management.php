<?php
session_start();

// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "monastery_healthcare";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";
$success = "";

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    if ($form_name === 'create') {
        $description = trim($_POST['description']);
        $amount = floatval($_POST['amount']);
        $category_id = intval($_POST['category_id']);
        $bill_date = $_POST['bill_date'];
        $vendor_name = trim($_POST['vendor_name'] ?? '');
        $invoice_number = trim($_POST['invoice_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'] ?? 'pending';

        if (empty($description) || $amount <= 0 || empty($category_id)) {
            $error = "Description, valid amount, and category are required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO bills (description, amount, category_id, bill_date, vendor_name, invoice_number, notes, status, created_by, paid_by, paid_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $created_by = $_SESSION['user_id'];
            $paid_by = ($status === 'paid') ? $created_by : null;
            $paid_date = ($status === 'paid') ? $bill_date : null;
            $stmt->bind_param("sdisssssiis", $description, $amount, $category_id, $bill_date, $vendor_name, $invoice_number, $notes, $status, $created_by, $paid_by, $paid_date);
            
            if ($stmt->execute()) {
                $success = "Bill/Expense recorded successfully! Bill ID: " . $stmt->insert_id;
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($form_name === 'update') {
        $bill_id = intval($_POST['bill_id']);
        $description = trim($_POST['description']);
        $amount = floatval($_POST['amount']);
        $category_id = intval($_POST['category_id']);
        $bill_date = $_POST['bill_date'];
        $vendor_name = trim($_POST['vendor_name'] ?? '');
        $invoice_number = trim($_POST['invoice_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'];

        $stmt = $conn->prepare("UPDATE bills SET description=?, amount=?, category_id=?, bill_date=?, vendor_name=?, invoice_number=?, notes=?, status=?, paid_by=?, paid_date=? WHERE bill_id=?");
        $paid_by = ($status === 'paid') ? $_SESSION['user_id'] : null;
        $paid_date = ($status === 'paid') ? date('Y-m-d') : null;
        $stmt->bind_param("sdisssssisi", $description, $amount, $category_id, $bill_date, $vendor_name, $invoice_number, $notes, $status, $paid_by, $paid_date, $bill_id);
        
        if ($stmt->execute()) {
            $success = "Bill/Expense updated successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    if ($form_name === 'delete') {
        $bill_id = intval($_POST['bill_id']);
        $stmt = $conn->prepare("DELETE FROM bills WHERE bill_id=?");
        $stmt->bind_param("i", $bill_id);
        
        if ($stmt->execute()) {
            $success = "Bill/Expense deleted successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    if ($form_name === 'approve') {
        $bill_id = intval($_POST['bill_id']);
        $stmt = $conn->prepare("UPDATE bills SET status='paid', paid_by=?, paid_date=CURDATE() WHERE bill_id=?");
        $paid_by = $_SESSION['user_id'];
        $stmt->bind_param("ii", $paid_by, $bill_id);
        
        if ($stmt->execute()) {
            $success = "Bill/Expense marked as paid successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Get categories (only bill type)
$categories = [];
$cat_result = $conn->query("SELECT * FROM categories WHERE type='bill' ORDER BY name ASC");
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Get bills with category info
$bills = [];
$result = $conn->query("
    SELECT b.*, c.name as category_name, u.name as created_by_name 
    FROM bills b 
    LEFT JOIN categories c ON b.category_id = c.category_id 
    LEFT JOIN users u ON b.created_by = u.user_id 
    ORDER BY b.bill_date DESC, b.created_at DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bills[] = $row;
    }
}

// Calculate statistics
$stats = [
    'total_bills' => 0,
    'pending_amount' => 0,
    'paid_amount' => 0,
    'this_month' => 0
];

$result = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM bills");
if ($result) {
    $row = $result->fetch_assoc();
    $stats['total_bills'] = $row['count'];
}

$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM bills WHERE status='pending'");
if ($result) $stats['pending_amount'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM bills WHERE status='paid'");
if ($result) $stats['paid_amount'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM bills WHERE MONTH(bill_date) = MONTH(CURRENT_DATE()) AND YEAR(bill_date) = YEAR(CURRENT_DATE())");
if ($result) $stats['this_month'] = $result->fetch_assoc()['total'];

// Get category-wise expenses for this month
$category_expenses = [];
$result = $conn->query("
    SELECT c.name, COALESCE(SUM(b.amount), 0) as total 
    FROM categories c 
    LEFT JOIN bills b ON c.category_id = b.category_id AND MONTH(b.bill_date) = MONTH(CURRENT_DATE()) AND YEAR(b.bill_date) = YEAR(CURRENT_DATE())
    WHERE c.type = 'bill'
    GROUP BY c.category_id, c.name
    ORDER BY total DESC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $category_expenses[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bills & Expenses - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        .category-chart-wrap {
            width: 280px;
            max-width: 100%;
            height: 280px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h1 class="page-title"><i class="bi bi-receipt"></i> Bills & Expenses Management</h1>
            <p class="page-subtitle">Track monastery expenses and bills</p>
        </div>
        <div class="page-header-actions">
            <button class="btn-modern btn-primary-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> Add Expense
            </button>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon rose">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Total Bills</div>
                    <div class="stat-value"><?= $stats['total_bills'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon amber">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Pending Approval</div>
                    <div class="stat-value">Rs. <?= number_format($stats['pending_amount'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon emerald">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Paid Expenses</div>
                    <div class="stat-value">Rs. <?= number_format($stats['paid_amount'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-calendar-month"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">This Month</div>
                    <div class="stat-value">Rs. <?= number_format($stats['this_month'], 2) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Category-wise Expenses Chart -->
    <div class="modern-table-wrapper mb-4">
        <div class="modern-table-header">
            <h5><i class="bi bi-pie-chart me-2"></i>Category-wise Expenses (This Month)</h5>
        </div>
        <div class="p-4 d-flex justify-content-center">
            <div class="category-chart-wrap">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <h5><i class="bi bi-list-ul me-2"></i>Recent Bills & Expenses</h5>
        </div>
        <div class="table-responsive-modern">
            <?php if (count($bills) > 0): ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Invoice</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($bills as $bill): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($bill['description']) ?></strong><br>
                                <small class="text-muted">
                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($bill['category_name']) ?>
                                    <?php if ($bill['vendor_name']): ?>
                                        &middot; <i class="bi bi-shop"></i> <?= htmlspecialchars($bill['vendor_name']) ?>
                                    <?php endif; ?>
                                </small>
                                <?php if ($bill['notes']): ?>
                                    <br><small class="text-muted"><i class="bi bi-sticky"></i> <?= htmlspecialchars($bill['notes']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong>Rs. <?= number_format($bill['amount'], 2) ?></strong><br>
                                <small class="text-muted"><?= date('M d, Y', strtotime($bill['bill_date'])) ?></small>
                            </td>
                            <td>
                                <?php if ($bill['invoice_number']): ?>
                                    <small><?= htmlspecialchars($bill['invoice_number']) ?></small>
                                <?php else: ?>
                                    <small class="text-muted">—</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_badges = [
                                    'pending' => 'badge-warning',
                                    'paid' => 'badge-success',
                                    'overdue' => 'badge-danger',
                                    'cancelled' => 'badge-neutral'
                                ];
                                $badge_class = $status_badges[$bill['status']] ?? 'badge-neutral';
                                ?>
                                <span class="badge-modern <?= $badge_class ?> badge-dot"><?= ucfirst($bill['status']) ?></span>
                            </td>
                            <td>
                                <div class="d-flex gap-1 align-items-center">
                                    <?php if ($bill['status'] == 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="form_name" value="approve">
                                            <input type="hidden" name="bill_id" value="<?= $bill['bill_id'] ?>">
                                            <button type="submit" class="btn-icon" onclick="return confirm('Mark this expense as paid?')" title="Mark Paid">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button class="btn-icon" onclick="editBill(<?= htmlspecialchars(json_encode($bill)) ?>)" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="form_name" value="delete">
                                        <input type="hidden" name="bill_id" value="<?= $bill['bill_id'] ?>">
                                        <button type="submit" class="btn-icon danger" onclick="return confirm('Delete this expense?')" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; opacity: 0.3;"></i>
                    <p class="mt-3">No bills/expenses recorded yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

<!-- Add Bill Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Bill/Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="form_name" value="create">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Description <span class="required">*</span></label>
                                <input type="text" name="description" class="form-control-modern" placeholder="e.g., Electricity Bill, Medicine Purchase" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Amount (Rs.) <span class="required">*</span></label>
                                <input type="number" name="amount" class="form-control-modern" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Category <span class="required">*</span></label>
                                <select name="category_id" class="form-select-modern" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Bill Date <span class="required">*</span></label>
                                <input type="date" name="bill_date" class="form-control-modern" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Vendor/Supplier</label>
                                <input type="text" name="vendor_name" class="form-control-modern" placeholder="Company/Shop name">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Invoice Number</label>
                                <input type="text" name="invoice_number" class="form-control-modern" placeholder="Invoice/Receipt #">
                            </div>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Status</label>
                        <select name="status" class="form-select-modern">
                            <option value="pending">Pending Approval</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Notes</label>
                        <textarea name="notes" class="form-control-modern" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">
                        <i class="bi bi-save"></i> Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Bill Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Bill/Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="form_name" value="update">
                    <input type="hidden" name="bill_id" id="edit_bill_id">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Description <span class="required">*</span></label>
                                <input type="text" name="description" id="edit_description" class="form-control-modern" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Amount (Rs.) <span class="required">*</span></label>
                                <input type="number" name="amount" id="edit_amount" class="form-control-modern" step="0.01" min="0" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Category <span class="required">*</span></label>
                                <select name="category_id" id="edit_category_id" class="form-select-modern" required>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Bill Date <span class="required">*</span></label>
                                <input type="date" name="bill_date" id="edit_bill_date" class="form-control-modern" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Vendor/Supplier</label>
                                <input type="text" name="vendor_name" id="edit_vendor" class="form-control-modern">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Invoice Number</label>
                                <input type="text" name="invoice_number" id="edit_invoice_number" class="form-control-modern">
                            </div>
                        </div>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Status</label>
                        <select name="status" id="edit_status" class="form-select-modern">
                            <option value="pending">Pending Approval</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Notes</label>
                        <textarea name="notes" id="edit_notes" class="form-control-modern" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">
                        <i class="bi bi-save"></i> Update Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function editBill(bill) {
    document.getElementById('edit_bill_id').value = bill.bill_id;
    document.getElementById('edit_description').value = bill.description;
    document.getElementById('edit_amount').value = bill.amount;
    document.getElementById('edit_category_id').value = bill.category_id;
    document.getElementById('edit_bill_date').value = bill.bill_date;
    document.getElementById('edit_vendor').value = bill.vendor_name || '';
    document.getElementById('edit_invoice_number').value = bill.invoice_number || '';
    document.getElementById('edit_status').value = bill.status;
    document.getElementById('edit_notes').value = bill.notes || '';
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

// Category-wise Expenses Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($category_expenses, 'name')) ?>,
        datasets: [{
            label: 'Expenses (Rs.)',
            data: <?= json_encode(array_column($category_expenses, 'total')) ?>,
            backgroundColor: [
                '#A67C52',
                '#8D6844',
                '#C7A57F',
                '#7A1E1E',
                '#2E7D32'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    boxWidth: 12,
                    padding: 10,
                    font: {
                        size: 11
                    }
                }
            }
        }
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>
