<?php
/**
 * Convergent Dashboard Server - Status tab: Service Status panel.
 * Rendered by Convergentdashboard::renderStatusSection('service') and loaded
 * into the Status tab over AJAX. Expects $serviceStatus (getServiceStatus())
 * and $prerequisites (checkPrerequisites(); all_ready gates the controls).
 */

// Included by renderStatusSection() during an authenticated ajax.php request,
// which defines CONVERGENT_STATUS_SECTION first.
if (!defined('FREEPBX_IS_AUTH') && !defined('CONVERGENT_STATUS_SECTION')) { die('No direct script access allowed'); }

$service       = $serviceStatus['service'] ?? [];
$isActive      = $service['active'] ?? false;
$portListening = $service['port_listening'] ?? false;
$allReady      = $prerequisites['all_ready'] ?? false;
?>

<div class="panel panel-default" id="cvg-service-panel" data-all-ready="<?php echo $allReady ? '1' : '0'; ?>">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-tachometer"></i> Service Status</h3>
    </div>
    <div class="panel-body">
        <?php if (!$allReady): ?>
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i> Service controls are disabled until prerequisites are met and the service is set up.
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Service Status Card -->
            <div class="col-md-4">
                <div class="well text-center">
                    <h4>Service Status</h4>
                    <?php if (!$allReady): ?>
                        <span class="label label-default" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-minus-circle"></i> Not Configured
                        </span>
                    <?php elseif ($isActive): ?>
                        <span class="label label-success" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-check-circle"></i> Running
                        </span>
                    <?php else: ?>
                        <span class="label label-danger" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-times-circle"></i> Stopped
                        </span>
                    <?php endif; ?>
                    <p class="text-muted" style="margin-top: 10px;">
                        <?php echo htmlspecialchars($service['name'] ?? 'convergent-monitoring'); ?>
                    </p>
                </div>
            </div>

            <!-- WebSocket Status Card -->
            <div class="col-md-4">
                <div class="well text-center">
                    <h4>WebSocket Port</h4>
                    <?php if (!$allReady): ?>
                        <span class="label label-default" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-plug"></i> N/A
                        </span>
                    <?php elseif ($portListening): ?>
                        <span class="label label-success" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-plug"></i> Listening
                        </span>
                    <?php else: ?>
                        <span class="label label-warning" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-plug"></i> Not Listening
                        </span>
                    <?php endif; ?>
                    <p class="text-muted" style="margin-top: 10px;">
                        Port: <?php echo htmlspecialchars($service['wss_port'] ?? '18443'); ?>
                    </p>
                </div>
            </div>

            <!-- Memory Usage Card -->
            <div class="col-md-4">
                <div class="well text-center">
                    <h4>Memory Usage</h4>
                    <?php if (!$allReady): ?>
                        <span class="label label-default" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-microchip"></i> N/A
                        </span>
                    <?php elseif ($isActive && !empty($service['memory'])): ?>
                        <span class="label label-info" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-microchip"></i> <?php echo htmlspecialchars($service['memory']); ?>
                        </span>
                    <?php else: ?>
                        <span class="label label-default" style="font-size: 1.5em; padding: 10px 20px;">
                            <i class="fa fa-microchip"></i> N/A
                        </span>
                    <?php endif; ?>
                    <p class="text-muted" style="margin-top: 10px;">
                        Managed by PM2
                    </p>
                </div>
            </div>
        </div>

        <!-- Service Details -->
        <?php if ($allReady && $isActive && !empty($service['uptime'])): ?>
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <table class="table table-bordered">
                    <tr>
                        <th width="200">Process ID</th>
                        <td><?php echo htmlspecialchars($service['pid'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Running Since</th>
                        <td><?php echo htmlspecialchars($service['uptime']); ?></td>
                    </tr>
                    <?php if (!empty($service['memory'])): ?>
                    <tr>
                        <th>Memory Usage</th>
                        <td><?php echo htmlspecialchars($service['memory']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Service Controls -->
        <div class="row" style="margin-top: 20px;">
            <div class="col-md-12">
                <h4>Service Controls</h4>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-success btn-service-action" data-action="start" <?php echo (!$allReady || $isActive) ? 'disabled' : ''; ?>>
                        <i class="fa fa-play"></i> Start
                    </button>
                    <button type="button" class="btn btn-danger btn-service-action" data-action="stop" <?php echo (!$allReady || !$isActive) ? 'disabled' : ''; ?>>
                        <i class="fa fa-stop"></i> Stop
                    </button>
                    <button type="button" class="btn btn-warning btn-service-action" data-action="restart" <?php echo !$allReady ? 'disabled' : ''; ?>>
                        <i class="fa fa-refresh"></i> Restart
                    </button>
                </div>

                <button type="button" class="btn btn-info" id="btn-refresh-status" style="margin-left: 20px;">
                    <i class="fa fa-sync"></i> Refresh Status
                </button>
            </div>
        </div>
    </div>
</div>
