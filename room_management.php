<?php
session_start();

$servername = "localhost";
$dbusername = "root";
$db_password = "";
$dbname = "monastery_healthcare";

$con = new mysqli($servername, $dbusername, $db_password, $dbname);
if ($con->connect_error) die("Connection failed: " . $con->connect_error);

$error = "";
$success = "";

// Handle CREATE / UPDATE / DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    if ($form_name === 'create') {
        $name = trim($_POST['name']);
        $type = trim($_POST['type']);
        $capacity = intval($_POST['capacity']);
        $status = $_POST['status'];

        // Check duplicate
        $check = $con->prepare("SELECT room_id FROM rooms WHERE name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Room name already exists.";
        } else {
            $stmt = $con->prepare("INSERT INTO rooms (name, type, capacity, status) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $name, $type, $capacity, $status);
            if ($stmt->execute()) {
                $success = "Room added successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }

    if ($form_name === 'update') {
        $room_id = intval($_POST['room_id']);
        $name = trim($_POST['name']);
        $type = trim($_POST['type']);
        $capacity = intval($_POST['capacity']);
        $status = $_POST['status'];

        // Check duplicate (excluding current)
        $check = $con->prepare("SELECT room_id FROM rooms WHERE name = ? AND room_id != ?");
        $check->bind_param("si", $name, $room_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = "Room name already exists.";
        } else {
            $stmt = $con->prepare("UPDATE rooms SET name=?, type=?, capacity=?, status=? WHERE room_id=?");
            $stmt->bind_param("ssisi", $name, $type, $capacity, $status, $room_id);
            if ($stmt->execute()) {
                $success = "Room updated successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }

    if ($form_name === 'delete') {
        $room_id = intval($_POST['room_id']);
        $stmt = $con->prepare("DELETE FROM rooms WHERE room_id=?");
        $stmt->bind_param("i", $room_id);
        if ($stmt->execute()) {
            $success = "Room deleted successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch rooms
$rooms_res = $con->query("SELECT * FROM rooms ORDER BY name ASC");

$status_colors = [
    'available' => 'success',
    'occupied' => 'warning',
    'maintenance' => 'danger'
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Room Management - Seela Suwa Herath Bikshu Gilan Arana</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>
<?php include 'navbar.php'; ?>

    <?php if($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <div>
                <h2><i class="bi bi-door-open"></i> Room Management</h2>
                <p>Manage consultation and treatment rooms</p>
            </div>
            <button class="btn-modern btn-primary-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> Add Room
            </button>
        </div>
        <div class="table-responsive-modern">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Room Name</th>
                        <th>Type</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rooms_res->num_rows == 0): ?>
                        <tr>
                            <td colspan="5" class="text-center">
                                <i class="bi bi-info-circle"></i> No rooms found. Click "Add Room" to create one.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php while($row = $rooms_res->fetch_assoc()): ?>
                        <tr>
                            <td><i class="bi bi-door-closed"></i> <?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['type']) ?></td>
                            <td><?= $row['capacity'] ?> person(s)</td>
                            <td><span class="badge-modern badge-<?= $status_colors[$row['status']] ?> badge-dot"><?= ucfirst($row['status']) ?></span></td>
                            <td>
                                <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['room_id'] ?>" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['room_id'] ?>" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editModal<?= $row['room_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Room</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="form_name" value="update">
                                            <input type="hidden" name="room_id" value="<?= $row['room_id'] ?>">

                                            <div class="form-group-modern">
                                                <label class="form-label-modern">Room Name</label>
                                                <input type="text" name="name" class="form-control-modern" value="<?= htmlspecialchars($row['name']) ?>" required>
                                            </div>

                                            <div class="form-group-modern">
                                                <label class="form-label-modern">Type</label>
                                                <input type="text" name="type" class="form-control-modern" value="<?= htmlspecialchars($row['type']) ?>" placeholder="e.g., Consultation, Treatment" required>
                                            </div>

                                            <div class="form-group-modern">
                                                <label class="form-label-modern">Capacity</label>
                                                <input type="number" name="capacity" class="form-control-modern" value="<?= $row['capacity'] ?>" min="1" required>
                                            </div>

                                            <div class="form-group-modern">
                                                <label class="form-label-modern">Status</label>
                                                <select name="status" class="form-control-modern" required>
                                                    <option value="available" <?= $row['status'] == 'available' ? 'selected' : '' ?>>Available</option>
                                                    <option value="occupied" <?= $row['status'] == 'occupied' ? 'selected' : '' ?>>Occupied</option>
                                                    <option value="maintenance" <?= $row['status'] == 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn-modern btn-primary-modern">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Delete Modal -->
                        <div class="modal fade" id="deleteModal<?= $row['room_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="post">
                                        <div class="modal-header">
                                            <h5 class="modal-title"><i class="bi bi-trash"></i> Confirm Delete</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete <strong><?= htmlspecialchars($row['name']) ?></strong>?</p>
                                            <p class="text-muted small">This will also delete all associated room slots.</p>
                                            <input type="hidden" name="form_name" value="delete">
                                            <input type="hidden" name="room_id" value="<?= $row['room_id'] ?>">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn-modern btn-primary-modern">Yes, Delete</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

<!-- Add New Room Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="form_name" value="create">

                    <div class="form-group-modern">
                        <label class="form-label-modern">Room Name</label>
                        <input type="text" name="name" class="form-control-modern" placeholder="e.g., Consultation Room 1" required>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Type</label>
                        <input type="text" name="type" class="form-control-modern" placeholder="e.g., Consultation, Treatment" required>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Capacity</label>
                        <input type="number" name="capacity" class="form-control-modern" value="1" min="1" required>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Status</label>
                        <select name="status" class="form-control-modern" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
