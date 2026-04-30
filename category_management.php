<?php
session_start();
// Access control
if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Database connection
$servername  = "localhost";
$db_username = "root";
$db_password = "";
$dbname      = "monastery_healthcare";

$con = new mysqli($servername, $db_username, $db_password, $dbname);
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

$success = "";
$error   = "";

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['form_name'])) {

    // ADD CATEGORY
    if ($_POST['form_name'] === "create" && !empty($_POST['name'])) {
        $name = trim($_POST['name']);

        // Check for duplicates
        $check = $con->prepare("SELECT category_id FROM categories WHERE LOWER(name) = LOWER(?)");
        $check->bind_param("s", $name);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Category '$name' already exists!";
        } else {
            $stmt = $con->prepare("INSERT INTO categories (name, type, description) VALUES (?, 'donation', ?)");
            $description = $_POST['description'] ?? '';
            $stmt->bind_param("ss", $name, $description);
            if ($stmt->execute()) {
                $success = "Category added successfully!";
            } else {
                $error = "Error adding category: " . $con->error;
            }
            $stmt->close();
        }
        $check->close();
    }

    // Update category
    if ($_POST['form_name'] === "update" && !empty($_POST['id']) && !empty($_POST['name'])) {
        $id   = intval($_POST['id']);
        $name = trim($_POST['name']);
		
        // Check for duplicates 
        $check = $con->prepare("SELECT category_id FROM categories WHERE LOWER(name) = LOWER(?) AND category_id != ?");
        $check->bind_param("si", $name, $id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Category '$name' already exists!";
        } else {
            $stmt = $con->prepare("UPDATE categories SET name=?, description=? WHERE category_id=?");
            $description = $_POST['description'] ?? '';
            $stmt->bind_param("ssi", $name, $description, $id);
            if ($stmt->execute()) {
                $success = "Category updated successfully!";
            } else {
                $error = "Error updating category: " . $con->error;
            }
            $stmt->close();
        }
        $check->close();
    }


    // Delete category
    if ($_POST['form_name'] === "delete" && !empty($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $con->prepare("DELETE FROM categories WHERE category_id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Category deleted successfully!";
        } else {
            $error = "Error deleting category: " . $con->error;
        }
        $stmt->close();
    }
}

// Fetch categories
$result = $con->query("SELECT * FROM categories ORDER BY category_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - Seela suwa herath</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>

<?php include 'navbar.php'; ?>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i>
            <span><?= $error ?></span>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i>
            <span><?= $success ?></span>
        </div>
    <?php endif; ?>

    <!-- Categories Table -->
    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <h5><i class="bi bi-tag me-2"></i>Categories</h5>
            <button class="btn-modern btn-primary-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> Add Category
            </button>
        </div>
        <div class="table-responsive-modern">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['category_id'] ?></td>
                        <td><div style="font-weight:600;"><i class="bi bi-tag-fill me-1" style="color:var(--primary-500);"></i><?= htmlspecialchars($row['name']) ?></div></td>
                        <td>
                            <?php if ($row['type'] == 'donation'): ?>
                                <span class="badge-modern badge-success badge-dot">Donation</span>
                            <?php else: ?>
                                <span class="badge-modern badge-warning badge-dot">Bill</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['description'] ?? '') ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['category_id'] ?>" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['category_id'] ?>" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?= $row['category_id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="post">
                            <div class="modal-header">
                              <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Category</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <input type="hidden" name="form_name" value="update">
                              <input type="hidden" name="id" value="<?= $row['category_id'] ?>">
                              <div class="form-group-modern">
                                  <label class="form-label-modern">Category Name</label>
                                  <input type="text" name="name" class="form-control-modern" value="<?= htmlspecialchars($row['name']) ?>" required>
                              </div>
                              <div class="form-group-modern">
                                  <label class="form-label-modern">Description</label>
                                  <input type="text" name="description" class="form-control-modern" value="<?= htmlspecialchars($row['description'] ?? '') ?>">
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-save"></i> Save Changes</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>

                    <!-- Delete Modal -->
                    <div class="modal fade" id="deleteModal<?= $row['category_id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Confirm Delete</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            Are you sure you want to delete category <strong><?= htmlspecialchars($row['name']) ?></strong>?<br>
                            <small style="color:var(--text-secondary);">This action cannot be undone.</small>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $row['category_id'] ?>">
                                <input type="hidden" name="form_name" value="delete">
                                <button type="submit" class="btn-modern btn-danger-modern"><i class="bi bi-trash"></i> Delete</button>
                            </form>
                          </div>
                        </div>
                      </div>
                    </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="form_name" value="create">
                        <div class="form-group-modern">
                            <label class="form-label-modern">Category Name</label>
                            <input type="text" name="name" class="form-control-modern" placeholder="e.g., Medicine, Food" required>
                        </div>
                        <div class="form-group-modern">
                            <label class="form-label-modern">Description</label>
                            <input type="text" name="description" class="form-control-modern" placeholder="Brief description">
                        </div>
                        <div class="alert-modern alert-info-modern" style="margin-top:12px;">
                            <i class="bi bi-info-circle"></i>
                            <span>Category will be set as "Donation" type by default.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-plus-circle"></i> Add Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
