<?php
require_once __DIR__ . '/../../includes/db_config.php';
$conn = getDBConnection();

echo "--- ROLES ---\n";
$roles = $conn->query("SELECT * FROM roles");
while ($row = $roles->fetch_assoc()) {
    print_r($row);
}

echo "\n--- ADMIN USERS ---\n";
$users = $conn->query("SELECT u.user_id, u.name, u.email, u.password_hash, u.role_id, r.role_name FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'Admin'");
while ($row = $users->fetch_assoc()) {
    print_r($row);
}

echo "\n--- ALL USERS WITH admin@monastery.lk ---\n";
$all = $conn->query("SELECT * FROM users WHERE email = 'admin@monastery.lk'");
while ($row = $all->fetch_assoc()) {
    print_r($row);
}

$conn->close();
