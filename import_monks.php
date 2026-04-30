<?php
session_start();

// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'includes/db_config.php';
$conn = getDBConnection();

$success = "";
$error = "";
$preview_data = [];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Validate file
    $allowed_extensions = ['xlsx', 'xls', 'csv'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_ext, $allowed_extensions)) {
        $error = "Please upload a valid Excel file (.xlsx, .xls, or .csv)";
    } elseif ($file['size'] > 5000000) { // 5MB limit
        $error = "File size must be less than 5MB";
    } else {
        // Process CSV file (easiest for PHP)
        if ($file_ext == 'csv' || isset($_POST['confirm_import'])) {
            $handle = fopen($file['tmp_name'], 'r');
            $headers = fgetcsv($handle); // Get header row
            
            $imported = 0;
            $errors_found = [];
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                // Skip empty rows
                if (empty(array_filter($row))) continue;
                
                // Map columns (adjust based on your template)
                $data = array_combine($headers, $row);
                
                // Validate required fields
                if (empty($data['Full Name'])) {
                    $errors_found[] = "Row skipped: Missing full name";
                    continue;
                }
                
                // Prepare data
                $full_name = trim($data['Full Name']);
                $title_id = !empty($data['Title ID']) ? intval($data['Title ID']) : 1;
                $ordination_date = !empty($data['Ordination Date']) ? $data['Ordination Date'] : null;
                $birth_date = !empty($data['Birth Date']) ? $data['Birth Date'] : null;
                $phone = trim($data['Phone'] ?? '');
                $emergency_contact = trim($data['Emergency Contact'] ?? '');
                $blood_group = trim($data['Blood Group'] ?? '');
                $allergies = trim($data['Allergies'] ?? '');
                $chronic_conditions = trim($data['Chronic Conditions'] ?? '');
                $current_medications = trim($data['Current Medications'] ?? '');
                $status = 'active';
                
                // Insert into database
                $stmt = $conn->prepare("
                    INSERT INTO monks (
                        full_name, title_id, ordination_date, birth_date, phone, 
                        emergency_contact, blood_group, allergies, chronic_conditions, 
                        current_medications, status, created_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $created_by = $_SESSION['user_id'];
                $stmt->bind_param(
                    "sisssssssssi",
                    $full_name, $title_id, $ordination_date, $birth_date, $phone,
                    $emergency_contact, $blood_group, $allergies, $chronic_conditions,
                    $current_medications, $status, $created_by
                );
                
                if ($stmt->execute()) {
                    $imported++;
                } else {
                    $errors_found[] = "Failed to import: $full_name - " . $stmt->error;
                }
                $stmt->close();
            }
            
            fclose($handle);
            
            $success = "Successfully imported $imported monk(s)!";
            if (count($errors_found) > 0) {
                $error = "Some errors occurred: " . implode(', ', array_slice($errors_found, 0, 5));
            }
        } else {
            // Preview mode
            $handle = fopen($file['tmp_name'], 'r');
            $headers = fgetcsv($handle);
            
            $count = 0;
            while (($row = fgetcsv($handle)) !== FALSE && $count < 5) {
                if (!empty(array_filter($row))) {
                    $preview_data[] = array_combine($headers, $row);
                    $count++;
                }
            }
            fclose($handle);
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Import Monk Data - Excel Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container-fluid mt-4 mb-5 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-file-earmark-excel"></i> Import Monk Data from Excel</h2>
            <p class="text-muted mb-0 mt-1">Bulk upload monk information from Excel spreadsheet</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
            <a href="monk_management.php" class="btn-modern btn-primary-modern ms-3">View Monks</a>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-cloud-upload"></i> Upload Excel File</h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="form-group-modern">
                            <div class="upload-area border border-2 border-dashed rounded-3 p-5 text-center" role="button" onclick="document.getElementById('excel_file').click()">
                                <i class="bi bi-file-earmark-excel display-3 text-success"></i>
                                <h4 class="mt-3">Click to Select Excel File</h4>
                                <p class="text-muted">or drag and drop here</p>
                                <p><small class="text-muted">Supported: .xlsx, .xls, .csv (Max 5MB)</small></p>
                            </div>
                        </div>
                        <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" class="d-none" onchange="this.form.submit()">
                        
                        <?php if (!empty($preview_data)): ?>
                            <input type="hidden" name="confirm_import" value="1">
                            <div class="mt-4">
                                <h5><i class="bi bi-eye"></i> Preview (First 5 rows):</h5>
                                <div class="modern-table-wrapper">
                                    <div class="modern-table-header">
                                        <h6 class="mb-0">Preview Data</h6>
                                    </div>
                                    <div class="table-responsive-modern">
                                        <table class="modern-table">
                                            <thead>
                                                <tr>
                                                    <?php foreach (array_keys($preview_data[0]) as $header): ?>
                                                        <th><?= htmlspecialchars($header) ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($preview_data as $row): ?>
                                                    <tr>
                                                        <?php foreach ($row as $cell): ?>
                                                            <td><?= htmlspecialchars($cell) ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <button type="submit" class="btn-modern btn-primary-modern mt-3">
                                    <i class="bi bi-check-circle"></i> Confirm &amp; Import Data
                                </button>
                                <a href="import_monks.php" class="btn-modern mt-3 ms-2">Cancel</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> How to Import</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-start mb-3">
                        <span class="badge bg-dark rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:28px;height:28px;min-width:28px;">1</span>
                        <div>
                            <strong>Download Template</strong>
                            <p class="text-muted mb-0 small">Get the Excel template with correct columns</p>
                            <a href="monk_import_template.csv" class="btn-modern btn-primary-modern mt-2" download>
                                <i class="bi bi-download"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <div class="d-flex align-items-start mb-3">
                        <span class="badge bg-dark rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:28px;height:28px;min-width:28px;">2</span>
                        <div>
                            <strong>Fill Your Data</strong>
                            <p class="text-muted mb-0 small">Enter monk information in the template</p>
                        </div>
                    </div>

                    <div class="d-flex align-items-start mb-3">
                        <span class="badge bg-dark rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:28px;height:28px;min-width:28px;">3</span>
                        <div>
                            <strong>Upload File</strong>
                            <p class="text-muted mb-0 small">Click upload area and select your file</p>
                        </div>
                    </div>

                    <div class="d-flex align-items-start">
                        <span class="badge bg-dark rounded-circle me-3 d-flex align-items-center justify-content-center" style="width:28px;height:28px;min-width:28px;">4</span>
                        <div>
                            <strong>Review &amp; Import</strong>
                            <p class="text-muted mb-0 small">Preview data and confirm import</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0"><i class="bi bi-list-check"></i> Required Columns</h6>
                </div>
                <div class="card-body">
                    <ul class="small text-muted mb-0">
                        <li><strong>Full Name</strong> (Required)</li>
                        <li>Title ID</li>
                        <li>Ordination Date</li>
                        <li>Birth Date</li>
                        <li>Phone</li>
                        <li>Emergency Contact</li>
                        <li>Blood Group</li>
                        <li>Allergies</li>
                        <li>Chronic Conditions</li>
                        <li>Current Medications</li>
                    </ul>
                </div>
            </div>

            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Tips</h6>
                </div>
                <div class="card-body">
                    <ul class="small text-muted mb-0">
                        <li>Use the template for correct format</li>
                        <li>Date format: YYYY-MM-DD</li>
                        <li>Remove sample data before filling</li>
                        <li>Check for duplicates</li>
                        <li>Preview before importing</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Drag and drop support
const uploadArea = document.querySelector('.upload-area');
const fileInput = document.getElementById('excel_file');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    uploadArea.classList.add('border-primary', 'bg-light');
}

function unhighlight(e) {
    uploadArea.classList.remove('border-primary', 'bg-light');
}

uploadArea.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    fileInput.files = files;
    document.getElementById('uploadForm').submit();
}
</script>

</body>
</html>
