<?php
require_once __DIR__ . '/includes/language.php';
$currentPage = basename($_SERVER['PHP_SELF']);

if (!function_exists('isActivePage')) {
function isActivePage($pages, $currentPage) {
  if (!is_array($pages)) {
    $pages = [$pages];
  }
  return in_array($currentPage, $pages) ? 'active' : '';
}
}

// Get page title based on current page
if (!function_exists('getPageTitle')) {
function getPageTitle($page) {
  $titles = [
    'dashboard.php' => 'Dashboard',
    'dashboard_doctor.php' => 'Doctor Dashboard',
    'dashboard_donor.php' => 'Donor Dashboard',
    'dashboard_monk.php' => 'Monk Dashboard',
    'donation_management.php' => 'Donations',
    'bill_management.php' => 'Bills',
    'patient_appointments.php' => 'Appointments',
    'reports.php' => 'Reports',
    'chatbot.php' => 'AI Assistant',
    'monk_management.php' => 'Monk Management',
    'doctor_management.php' => 'Doctor Management',
    'room_management.php' => 'Room Management',
    'room_slot_management.php' => 'Room Slots',
    'table.php' => 'User Management',
    'category_management.php' => 'Categories',
    'title_management.php' => 'Titles',
    'doctor_availability.php' => 'Doctor Availability',
    'import_monks.php' => 'Import Monks',
    'export_report.php' => 'Export Report',
    'edit.php' => 'Edit Record',
    'generate_receipt.php' => 'Receipt',
  ];
  return $titles[$page] ?? 'Dashboard';
}
}

$pageTitle = getPageTitle($currentPage);
$userName = $_SESSION['username'] ?? 'User';
$userRole = $_SESSION['role_name'] ?? 'Admin';
$userInitial = strtoupper(substr($userName, 0, 1));
$isAdmin = ($userRole === 'Admin');
$isDoctor = ($userRole === 'Doctor');
$isDonor = ($userRole === 'Donor');
$isMonk = ($userRole === 'Monk');
?>

<!-- Modern Design System -->
<link rel="stylesheet" href="assets/css/modern-design.css">
<script src="assets/js/modern-app.js"></script>

<!-- Sidebar -->
<aside class="app-sidebar" id="appSidebar">
  <a href="index.php" class="sidebar-header" style="text-decoration:none;color:inherit;">
    <div class="sidebar-logo">
      <i class="bi bi-heart-pulse"></i>
    </div>
    <div class="sidebar-brand">
      Seela Suwa
      <small>Healthcare Management</small>
    </div>
  </a>

  <nav class="sidebar-nav">
    <div class="sidebar-section-label"><?= __('dashboard') ?></div>
    <a class="sidebar-link <?= isActivePage('dashboard.php', $currentPage) ?>" href="dashboard.php">
      <i class="bi bi-grid-1x2"></i> <?= __('dashboard') ?>
    </a>

    <?php if ($isAdmin): ?>
    <!-- Admin: Full Access -->
    <div class="sidebar-section-label">Main</div>
    <a class="sidebar-link <?= isActivePage('donation_management.php', $currentPage) ?>" href="donation_management.php">
      <i class="bi bi-cash-coin"></i> <?= __('donations') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('bill_management.php', $currentPage) ?>" href="bill_management.php">
      <i class="bi bi-receipt-cutoff"></i> <?= __('bills') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('patient_appointments.php', $currentPage) ?>" href="patient_appointments.php">
      <i class="bi bi-calendar2-check"></i> <?= __('appointments') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('reports.php', $currentPage) ?>" href="reports.php">
      <i class="bi bi-bar-chart-line"></i> <?= __('reports') ?>
    </a>

    <div class="sidebar-section-label"><?= __('manage') ?></div>
    <a class="sidebar-link <?= isActivePage('monk_management.php', $currentPage) ?>" href="monk_management.php">
      <i class="bi bi-person-hearts"></i> <?= __('monks') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('doctor_management.php', $currentPage) ?>" href="doctor_management.php">
      <i class="bi bi-person-badge"></i> <?= __('doctors') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('room_management.php', $currentPage) ?>" href="room_management.php">
      <i class="bi bi-door-open"></i> <?= __('rooms') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('room_slot_management.php', $currentPage) ?>" href="room_slot_management.php">
      <i class="bi bi-calendar3-range"></i> <?= __('room_slots') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('table.php', $currentPage) ?>" href="table.php">
      <i class="bi bi-people"></i> <?= __('users') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('category_management.php', $currentPage) ?>" href="category_management.php">
      <i class="bi bi-tags"></i> <?= __('categories') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('title_management.php', $currentPage) ?>" href="title_management.php">
      <i class="bi bi-award"></i> <?= __('titles') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('doctor_availability.php', $currentPage) ?>" href="doctor_availability.php">
      <i class="bi bi-clock-history"></i> <?= __('doctor_availability') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('import_monks.php', $currentPage) ?>" href="import_monks.php">
      <i class="bi bi-upload"></i> <?= __('import_monks') ?>
    </a>

    <?php elseif ($isDoctor): ?>
    <!-- Doctor: Appointments, Patients, Availability -->
    <div class="sidebar-section-label">My Practice</div>
    <a class="sidebar-link <?= isActivePage('patient_appointments.php', $currentPage) ?>" href="patient_appointments.php">
      <i class="bi bi-calendar2-check"></i> <?= __('appointments') ?>
    </a>
    <a class="sidebar-link <?= isActivePage('monk_management.php', $currentPage) ?>" href="monk_management.php">
      <i class="bi bi-person-hearts"></i> Patient Records
    </a>
    <a class="sidebar-link <?= isActivePage('doctor_availability.php', $currentPage) ?>" href="doctor_availability.php">
      <i class="bi bi-clock-history"></i> My Availability
    </a>

    <?php elseif ($isDonor): ?>
    <!-- Donor: Donations, Transparency -->
    <div class="sidebar-section-label">My Donations</div>
    <a class="sidebar-link" href="#" data-bs-toggle="modal" data-bs-target="#donateModal">
      <i class="bi bi-heart"></i> Make Donation
    </a>

    <div class="sidebar-section-label">Tools</div>
    <a class="sidebar-link <?= isActivePage('chatbot.php', $currentPage) ?>" href="chatbot.php">
      <i class="bi bi-robot"></i> <?= __('ai_assistant') ?>
    </a>

    <?php elseif ($isMonk): ?>
    <!-- Monk: Health Records, Appointments, Doctors -->
    <div class="sidebar-section-label">My Health</div>
    <a class="sidebar-link <?= isActivePage('patient_appointments.php', $currentPage) ?>" href="patient_appointments.php">
      <i class="bi bi-calendar2-check"></i> My Appointments
    </a>
    <a class="sidebar-link <?= isActivePage('doctor_management.php', $currentPage) ?>" href="doctor_management.php">
      <i class="bi bi-person-badge"></i> View Doctors
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <a class="sidebar-link" href="logout.php">
      <i class="bi bi-box-arrow-left"></i> <?= __('logout') ?>
    </a>
  </div>
</aside>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Main Content Wrapper -->
<div class="app-main">
  <!-- Top Bar -->
  <header class="app-topbar">
    <button class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
      <i class="bi bi-list"></i>
    </button>
    <h1 class="topbar-title"><?= htmlspecialchars($pageTitle) ?></h1>

    <div class="topbar-actions">
      <!-- Language Switcher -->
      <div class="dropdown">
        <button class="topbar-btn dropdown-toggle" data-bs-toggle="dropdown" title="<?= __('language') ?>" style="width:auto;padding:0 12px;gap:6px;display:inline-flex;align-items:center;font-size:13px;">
          <?= getCurrentLanguage() == 'si' ? '🇱🇰' : '🇬🇧' ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>" href="?lang=en">🇬🇧 English</a></li>
          <li><a class="dropdown-item <?= getCurrentLanguage() == 'si' ? 'active' : '' ?>" href="?lang=si">🇱🇰 සිංහල</a></li>
        </ul>
      </div>

      <!-- Dark Mode Toggle -->
      <button class="topbar-btn" id="theme-toggle-modern" title="Toggle Dark Mode">
        <i class="bi bi-moon-fill"></i>
      </button>

      <!-- User Profile -->
      <div class="dropdown">
        <button class="topbar-user dropdown-toggle" data-bs-toggle="dropdown" style="border:none;">
          <div class="topbar-avatar"><?= htmlspecialchars($userInitial) ?></div>
          <div>
            <div class="topbar-user-name"><?= htmlspecialchars($userName) ?></div>
            <div class="topbar-user-role"><?= htmlspecialchars($userRole) ?></div>
          </div>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="dashboard.php"><i class="bi bi-grid me-2"></i> Dashboard</a></li>
          <?php if ($isDoctor): ?>
          <li><a class="dropdown-item" href="dashboard_doctor.php?edit_profile=1"><i class="bi bi-pencil-square me-2"></i> Update My Profile</a></li>
          <?php endif; ?>
          <?php if ($isMonk): ?>
          <li><a class="dropdown-item" href="dashboard_monk.php?edit_profile=1"><i class="bi bi-pencil-square me-2"></i> Update My Details</a></li>
          <?php endif; ?>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> <?= __('logout') ?></a></li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Page Content -->
  <div class="app-content">
