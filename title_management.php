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

//  Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    //  Create title
    if ($form_name === "create" && !empty($_POST['user_title'])) {
        $title = trim($_POST['user_title']);

        // Check if title exists
        $check_stmt = $con->prepare("SELECT title_id FROM titles WHERE LOWER(title_name) = LOWER(?)");
        $check_stmt->bind_param("s", $title);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error = "This title already exists. Please enter a different one.";
        } else {
            $stmt = $con->prepare("INSERT INTO titles (title_name) VALUES (?)");
            $stmt->bind_param("s", $title);
            if ($stmt->execute()) {
                $success = "Title added successfully!";
            } else {
                $error = "Error: " . $con->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }

    //  Update title
    elseif ($form_name === "update" && !empty($_POST['id']) && !empty($_POST['user_title'])) {
	$id = intval($_POST['id']);
    $title = trim($_POST['user_title']);

    // Normalize title for comparison
    $title_lower = strtolower($title);

    //  Check for duplicates excluding the current record
    $check_stmt = $con->prepare("
        SELECT title_id FROM titles 
        WHERE LOWER(TRIM(title_name)) = ? AND title_id != ?
    ");
    $check_stmt->bind_param("si", $title_lower, $id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $error = "This title already exists. Please enter a different one.";
    } else {
        //  update
        $stmt = $con->prepare("UPDATE titles SET title_name = ? WHERE title_id = ?");
        $stmt->bind_param("si", $title, $id);
        if ($stmt->execute()) {
            $success = "Title updated successfully!";
        } else {
            $error = "Error updating title: " . $con->error;
        }
        $stmt->close();
    }
    $check_stmt->close();
}
    //  Delete title
    elseif ($form_name === "delete" && !empty($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $con->prepare("DELETE FROM titles WHERE title_id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success = "Title deleted successfully!";
        } else {
            $error = "Error deleting title: " . $con->error;
        }
        $stmt->close();
    }
} 
//  Fetch titles for table (always runs)
$result = $con->query("SELECT * FROM titles ORDER BY title_id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Title Management - Seela suwa herath</title>
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

    <!-- Titles Table -->
    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <h5><i class="bi bi-award me-2"></i>Monk Titles</h5>
            <button class="btn-modern btn-primary-modern btn-sm-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> Add Title
            </button>
        </div>
        <div class="table-responsive-modern">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['title_id'] ?></td>
                        <td><div style="font-weight:600;"><i class="bi bi-award-fill me-1" style="color:var(--primary-500);"></i><?= htmlspecialchars($row['title_name']) ?></div></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['title_id'] ?>" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['title_id'] ?>" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?= $row['title_id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form method="post">
                            <div class="modal-header">
                              <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Title</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <input type="hidden" name="form_name" value="update">
                              <input type="hidden" name="id" value="<?= $row['title_id'] ?>">
                              <div class="form-group-modern">
                                  <label class="form-label-modern">Title Name</label>
                                  <input type="text" name="user_title" class="form-control-modern" value="<?= htmlspecialchars($row['title_name']) ?>" required>
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
                    <div class="modal fade" id="deleteModal<?= $row['title_id'] ?>" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Confirm Delete</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            Are you sure you want to delete title <strong><?= htmlspecialchars($row['title_name']) ?></strong>?<br>
                            <small style="color:var(--text-secondary);">This action cannot be undone.</small>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="id" value="<?= $row['title_id'] ?>">
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

    <!-- Add Title Modal -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Title</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="form_name" value="create">
                        <div class="form-group-modern">
                            <label class="form-label-modern">Title Name</label>
                            <input type="text" name="user_title" class="form-control-modern" placeholder="e.g., Ven., Rev., Most Ven." required>
                        </div>
                        <div class="alert-modern alert-info-modern" style="margin-top:12px;">
                            <i class="bi bi-info-circle"></i>
                            <span>Title will be used as honorific prefix for monks.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-plus-circle"></i> Add Title</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
