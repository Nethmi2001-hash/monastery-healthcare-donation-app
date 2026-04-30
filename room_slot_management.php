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

$days_map = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Handle CREATE / UPDATE / DELETE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['form_name'])) {
    $form_name = $_POST['form_name'];

    if ($form_name === 'create') {
        $room_id = intval($_POST['room_id']);
        $day_of_week = intval($_POST['day_of_week']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];

        if (strtotime($end_time) <= strtotime($start_time)) {
            $error = "End time must be after start time.";
        } else {
            $stmt = $con->prepare("INSERT INTO room_slots (room_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->bind_param("iiss", $room_id, $day_of_week, $start_time, $end_time);
            if ($stmt->execute()) {
                $success = "Room slot added successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($form_name === 'update') {
        $room_slot_id = intval($_POST['room_slot_id']);
        $room_id = intval($_POST['room_id']);
        $day_of_week = intval($_POST['day_of_week']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (strtotime($end_time) <= strtotime($start_time)) {
            $error = "End time must be after start time.";
        } else {
            $stmt = $con->prepare("UPDATE room_slots SET room_id=?, day_of_week=?, start_time=?, end_time=?, is_active=? WHERE room_slot_id=?");
            $stmt->bind_param("iissii", $room_id, $day_of_week, $start_time, $end_time, $is_active, $room_slot_id);
            if ($stmt->execute()) {
                $success = "Room slot updated successfully!";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if ($form_name === 'delete') {
        $room_slot_id = intval($_POST['room_slot_id']);
        $stmt = $con->prepare("DELETE FROM room_slots WHERE room_slot_id=?");
        $stmt->bind_param("i", $room_slot_id);
        if ($stmt->execute()) {
            $success = "Room slot deleted successfully!";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch room slots with room info
$slots_query = "SELECT rs.*, r.name as room_name, r.type 
                FROM room_slots rs 
                JOIN rooms r ON rs.room_id = r.room_id 
                ORDER BY r.name, rs.day_of_week, rs.start_time";
$slots_res = $con->query($slots_query);

// Fetch rooms for dropdown
$rooms_res = $con->query("SELECT room_id, name, type FROM rooms ORDER BY name ASC");
$rooms = [];
while ($r = $rooms_res->fetch_assoc()) {
    $rooms[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Slot Management - Seela suwa herath</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body>

<?php include 'navbar.php'; ?>

    <!-- Alerts -->
    <?php if($error): ?>
        <div class="alert-modern alert-danger-modern">
            <i class="bi bi-exclamation-triangle"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if($success): ?>
        <div class="alert-modern alert-success-modern">
            <i class="bi bi-check-circle"></i>
            <span><?= htmlspecialchars($success) ?></span>
        </div>
    <?php endif; ?>

    <!-- Room Slots Table -->
    <div class="modern-table-wrapper">
        <div class="modern-table-header">
            <h5><i class="bi bi-calendar-range me-2"></i>Room Slot Management</h5>
            <button class="btn-modern btn-primary-modern" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="bi bi-plus-circle"></i> Add Time Slot
            </button>
        </div>
        <div class="table-responsive-modern">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Day</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($slots_res->num_rows == 0): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:2rem;">
                                <i class="bi bi-info-circle me-1"></i> No room slots found. Click "Add Time Slot" to create one.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php 
                    $current_room = null;
                    while($row = $slots_res->fetch_assoc()): 
                        if ($current_room !== $row['room_id']) {
                            $current_room = $row['room_id'];
                        }
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;"><i class="bi bi-door-open me-1" style="color:var(--primary-500);"></i><?= htmlspecialchars($row['room_name']) ?></div>
                            <small style="color:var(--text-secondary);"><?= htmlspecialchars($row['type']) ?></small>
                        </td>
                        <td><span class="badge-modern badge-primary"><?= $days_map[$row['day_of_week']] ?></span></td>
                        <td>
                            <i class="bi bi-clock me-1"></i>
                            <?= date('g:i A', strtotime($row['start_time'])) ?> - 
                            <?= date('g:i A', strtotime($row['end_time'])) ?>
                        </td>
                        <td>
                            <?php if($row['is_active']): ?>
                                <span class="badge-modern badge-success badge-dot">Active</span>
                            <?php else: ?>
                                <span class="badge-modern badge-secondary badge-dot">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['room_slot_id'] ?>" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn-icon danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $row['room_slot_id'] ?>" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>

                    <!-- Edit Modal -->
                    <div class="modal fade" id="editModal<?= $row['room_slot_id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="post">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Room Slot</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="form_name" value="update">
                                        <input type="hidden" name="room_slot_id" value="<?= $row['room_slot_id'] ?>">
                                        
                                        <div class="form-group-modern">
                                            <label class="form-label-modern">Room</label>
                                            <select name="room_id" class="form-select-modern" required>
                                                <?php foreach($rooms as $room): ?>
                                                    <option value="<?= $room['room_id'] ?>" <?= $room['room_id'] == $row['room_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($room['name']) ?> - <?= $room['type'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="form-group-modern">
                                            <label class="form-label-modern">Day of Week</label>
                                            <select name="day_of_week" class="form-select-modern" required>
                                                <?php foreach($days_map as $idx => $day_name): ?>
                                                    <option value="<?= $idx ?>" <?= $idx == $row['day_of_week'] ? 'selected' : '' ?>><?= $day_name ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">Start Time</label>
                                                    <input type="time" name="start_time" class="form-control-modern" value="<?= $row['start_time'] ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group-modern">
                                                    <label class="form-label-modern">End Time</label>
                                                    <input type="time" name="end_time" class="form-control-modern" value="<?= $row['end_time'] ?>" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-check" style="margin-top:8px;">
                                            <input type="checkbox" name="is_active" class="form-check-input" id="active<?= $row['room_slot_id'] ?>" <?= $row['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="active<?= $row['room_slot_id'] ?>">Active</label>
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
                    <div class="modal fade" id="deleteModal<?= $row['room_slot_id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="post">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Confirm Delete</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Are you sure you want to delete this room slot?</p>
                                        <p><strong><?= htmlspecialchars($row['room_name']) ?></strong> - 
                                        <?= $days_map[$row['day_of_week']] ?> 
                                        (<?= date('g:i A', strtotime($row['start_time'])) ?> - <?= date('g:i A', strtotime($row['end_time'])) ?>)</p>
                                        <input type="hidden" name="form_name" value="delete">
                                        <input type="hidden" name="room_slot_id" value="<?= $row['room_slot_id'] ?>">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn-modern btn-danger-modern"><i class="bi bi-trash"></i> Delete</button>
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

<!-- Add New Slot Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Room Slot</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="form_name" value="create">
                    
                    <div class="form-group-modern">
                        <label class="form-label-modern">Room</label>
                        <select name="room_id" class="form-select-modern" required>
                            <option value="">-- Select Room --</option>
                            <?php foreach($rooms as $room): ?>
                                <option value="<?= $room['room_id'] ?>">
                                    <?= htmlspecialchars($room['name']) ?> - <?= $room['type'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group-modern">
                        <label class="form-label-modern">Day of Week</label>
                        <select name="day_of_week" class="form-select-modern" required>
                            <option value="">-- Select Day --</option>
                            <?php foreach($days_map as $idx => $day_name): ?>
                                <option value="<?= $idx ?>"><?= $day_name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Start Time</label>
                                <input type="time" name="start_time" class="form-control-modern" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group-modern">
                                <label class="form-label-modern">End Time</label>
                                <input type="time" name="end_time" class="form-control-modern" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert-modern alert-info-modern" style="margin-top:12px;">
                        <i class="bi bi-info-circle"></i>
                        <span>Slot will be set as active by default.</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modern btn-outline-modern" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-modern btn-primary-modern"><i class="bi bi-plus-circle"></i> Add Slot</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
