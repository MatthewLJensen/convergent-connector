<?php
/**
 * Convergent Dashboard Server - FreePBX Module
 * Main Page
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Get the module instance
$convergent = FreePBX::Convergentdashboard();

// Handle tab selection
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'status';

// Get current data
$config        = $convergent->getAllConfig();
$version       = $convergent->getInstalledVersion();
$moduleVersion = $convergent->getModuleVersion();

// Only run expensive checks on the status tab
if ($activeTab === 'status') {
    $serviceStatus = $convergent->getServiceStatus();
    $prerequisites = $convergent->checkPrerequisites();
} else {
    $serviceStatus = ['service' => []];
    $prerequisites = ['prerequisites' => [], 'all_ready' => false, 'can_setup' => false];
}

// Get success/error messages from form submissions
$message = '';
$messageType = '';
if (isset($_SESSION['convergent_message'])) {
    $message = $_SESSION['convergent_message'];
    $messageType = $_SESSION['convergent_message_type'] ?? 'info';
    unset($_SESSION['convergent_message']);
    unset($_SESSION['convergent_message_type']);
}
?>

<div class="container-fluid">
    <h1><i class="fa fa-server"></i> Convergent Dashboard Server</h1>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="<?php echo $activeTab === 'status' ? 'active' : ''; ?>">
            <a href="#status" aria-controls="status" role="tab" data-toggle="tab">
                <i class="fa fa-heartbeat"></i> Status
            </a>
        </li>
        <li role="presentation" class="<?php echo $activeTab === 'settings' ? 'active' : ''; ?>">
            <a href="#settings" aria-controls="settings" role="tab" data-toggle="tab">
                <i class="fa fa-cog"></i> Settings
            </a>
        </li>
        <li role="presentation" class="<?php echo $activeTab === 'credentials' ? 'active' : ''; ?>">
            <a href="#credentials" aria-controls="credentials" role="tab" data-toggle="tab">
                <i class="fa fa-key"></i> Credentials
            </a>
        </li>
        <li role="presentation" class="<?php echo $activeTab === 'logs' ? 'active' : ''; ?>">
            <a href="#logs" aria-controls="logs" role="tab" data-toggle="tab">
                <i class="fa fa-list"></i> Logs
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Status Tab -->
        <div role="tabpanel" class="tab-pane <?php echo $activeTab === 'status' ? 'active' : ''; ?>" id="status">
            <?php include __DIR__ . '/views/status.php'; ?>
        </div>

        <!-- Settings Tab -->
        <div role="tabpanel" class="tab-pane <?php echo $activeTab === 'settings' ? 'active' : ''; ?>" id="settings">
            <?php include __DIR__ . '/views/settings.php'; ?>
        </div>

        <!-- Credentials Tab -->
        <div role="tabpanel" class="tab-pane <?php echo $activeTab === 'credentials' ? 'active' : ''; ?>" id="credentials">
            <?php include __DIR__ . '/views/credentials.php'; ?>
        </div>

        <!-- Logs Tab -->
        <div role="tabpanel" class="tab-pane <?php echo $activeTab === 'logs' ? 'active' : ''; ?>" id="logs">
            <?php include __DIR__ . '/views/logs.php'; ?>
        </div>
    </div>
</div>

<!-- Include module CSS and JS -->
<link rel="stylesheet" href="modules/convergentdashboard/assets/css/convergent.css">
<script src="modules/convergentdashboard/assets/js/convergent.js"></script>
