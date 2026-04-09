<?php
/**
 * Convergent Dashboard Server - Status View
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$service = $serviceStatus['service'] ?? [];
$isActive = $service['active'] ?? false;
$portListening = $service['port_listening'] ?? false;

// Get prerequisites status
$prereqs = $prerequisites['prerequisites'] ?? [];
$allReady = $prerequisites['all_ready'] ?? false;
$canSetup = $prerequisites['can_setup'] ?? false;
?>

<!-- Prerequisites Panel -->
<div class="panel panel-<?php echo $allReady ? 'success' : 'warning'; ?>">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-<?php echo $allReady ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            Service Prerequisites
            <?php if ($allReady): ?>
                <span class="label label-success pull-right">Ready</span>
            <?php else: ?>
                <span class="label label-warning pull-right">Setup Required</span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="panel-body">
        <table class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th width="200">Component</th>
                    <th width="100" class="text-center">Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <!-- Server Code -->
                <tr class="<?php echo ($prereqs['server_code']['installed'] ?? false) ? 'success' : 'danger'; ?>">
                    <td><i class="fa fa-code"></i> Server Code</td>
                    <td class="text-center">
                        <?php if ($prereqs['server_code']['installed'] ?? false): ?>
                            <span class="label label-success"><i class="fa fa-check"></i> Installed</span>
                        <?php else: ?>
                            <span class="label label-danger"><i class="fa fa-times"></i> Missing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prereqs['server_code']['installed'] ?? false): ?>
                            <code><?php echo htmlspecialchars($prereqs['server_code']['path'] ?? '/opt/convergent-server'); ?></code>
                        <?php else: ?>
                            <?php echo htmlspecialchars($prereqs['server_code']['message'] ?? 'Server code not found'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Node Modules -->
                <tr class="<?php echo ($prereqs['node_modules']['installed'] ?? false) ? 'success' : 'danger'; ?>">
                    <td><i class="fa fa-archive"></i> Node Modules</td>
                    <td class="text-center">
                        <?php if ($prereqs['node_modules']['installed'] ?? false): ?>
                            <span class="label label-success"><i class="fa fa-check"></i> Installed</span>
                        <?php else: ?>
                            <span class="label label-danger"><i class="fa fa-times"></i> Missing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prereqs['node_modules']['installed'] ?? false): ?>
                            <code><?php echo htmlspecialchars($prereqs['node_modules']['path'] ?? '/opt/convergent-server/node_modules'); ?></code>
                        <?php else: ?>
                            <?php echo htmlspecialchars($prereqs['node_modules']['message'] ?? 'Run npm install in the server directory'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Node.js -->
                <?php
                $nodeInstalled = $prereqs['node']['installed'] ?? false;
                $nodeVersionOk = $prereqs['node']['version_ok'] ?? false;
                $nodeUsingNvm = $prereqs['node']['using_nvm'] ?? false;
                $nodeRowClass = ($nodeInstalled && $nodeVersionOk) ? 'success' : 'danger';
                ?>
                <tr class="<?php echo $nodeRowClass; ?>">
                    <td><i class="fa fa-cog"></i> Node.js</td>
                    <td class="text-center">
                        <?php if ($nodeInstalled && $nodeVersionOk): ?>
                            <span class="label label-success"><i class="fa fa-check"></i> OK</span>
                        <?php elseif ($nodeInstalled && !$nodeVersionOk): ?>
                            <span class="label label-danger"><i class="fa fa-times"></i> Version Too Old</span>
                        <?php else: ?>
                            <span class="label label-danger"><i class="fa fa-times"></i> Missing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($nodeInstalled && $nodeVersionOk): ?>
                            Version: <?php echo htmlspecialchars($prereqs['node']['version'] ?? ''); ?>
                            <?php if ($nodeUsingNvm): ?>
                                <span class="label label-info" style="margin-left: 10px;">via nvm</span>
                            <?php endif; ?>
                            <?php if (!empty($prereqs['node']['node_path'])): ?>
                                <br><small class="text-muted">Path: <code><?php echo htmlspecialchars($prereqs['node']['node_path']); ?></code></small>
                            <?php endif; ?>
                            <?php if (!empty($prereqs['node']['message'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($prereqs['node']['message']); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php echo htmlspecialchars($prereqs['node']['message'] ?? 'Node.js not found'); ?>
                            <br><small class="text-muted">Minimum required: <?php echo htmlspecialchars($prereqs['node']['min_version'] ?? '16.17.0'); ?></small>
                            <?php
                            // Show nvm info if available
                            $nvmInfo = $prereqs['node']['nvm_info'] ?? null;
                            if ($nvmInfo):
                                if ($nvmInfo['nvm_found'] && !empty($nvmInfo['available_versions'])):
                            ?>
                                <br><small class="text-warning">
                                    <i class="fa fa-info-circle"></i> nvm found at <code><?php echo htmlspecialchars($nvmInfo['nvm_dir']); ?></code>
                                    with versions: <?php echo htmlspecialchars(implode(', ', array_keys($nvmInfo['available_versions']))); ?>
                                    <br>Install a compatible version: <code>nvm install 16</code> or <code>nvm install 18</code>
                                </small>
                            <?php elseif (!$nvmInfo['nvm_found']): ?>
                                <br><small class="text-info">
                                    <i class="fa fa-lightbulb-o"></i> Tip: Install nvm to manage Node.js versions without affecting the system Node.
                                    <br><code>curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash</code>
                                    <br>Then: <code>nvm install 16</code>
                                </small>
                            <?php
                                endif;
                            endif;
                            ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Compiled JavaScript -->
                <tr class="<?php echo ($prereqs['compiled_js']['installed'] ?? false) ? 'success' : 'danger'; ?>">
                    <td><i class="fa fa-cog"></i> Compiled JavaScript</td>
                    <td class="text-center">
                        <?php if ($prereqs['compiled_js']['installed'] ?? false): ?>
                            <span class="label label-success"><i class="fa fa-check"></i> Installed</span>
                        <?php else: ?>
                            <span class="label label-danger"><i class="fa fa-times"></i> Missing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prereqs['compiled_js']['installed'] ?? false): ?>
                            <code><?php echo htmlspecialchars($prereqs['compiled_js']['path'] ?? ''); ?></code>
                        <?php else: ?>
                            <?php echo htmlspecialchars($prereqs['compiled_js']['message'] ?? 'Compiled JS not found. Run: npm run build'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- PM2 Module -->
                <tr class="<?php echo ($prereqs['pm2']['installed'] ?? false) ? 'success' : 'danger'; ?>">
                    <td><i class="fa fa-cog"></i> PM2 Module</td>
                    <td class="text-center">
                        <?php if ($prereqs['pm2']['installed'] ?? false): ?>
                            <span class="label label-success"><i class="fa fa-check"></i> Available</span>
                        <?php else: ?>
                            <span class="label label-danger"><i class="fa fa-times"></i> Missing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prereqs['pm2']['installed'] ?? false): ?>
                            FreePBX PM2 module is available
                        <?php else: ?>
                            <?php echo htmlspecialchars($prereqs['pm2']['message'] ?? 'Install FreePBX PM2 module'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Start Script -->
                <tr class="<?php echo ($prereqs['start_script']['installed'] ?? false) ? 'success' : 'warning'; ?>">
                    <td><i class="fa fa-file-text"></i> Start Script</td>
                    <td class="text-center">
                        <?php if ($prereqs['start_script']['installed'] ?? false): ?>
                            <span class="label label-success"><i class="fa fa-check"></i> Created</span>
                        <?php else: ?>
                            <span class="label label-warning"><i class="fa fa-exclamation"></i> Not Created</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prereqs['start_script']['installed'] ?? false): ?>
                            <code><?php echo htmlspecialchars($prereqs['start_script']['path'] ?? '/var/lib/asterisk/convergent-start.sh'); ?></code>
                        <?php else: ?>
                            <?php echo htmlspecialchars($prereqs['start_script']['message'] ?? 'Click "Setup Service" to create'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php if (!$allReady): ?>
        <div class="alert alert-info" style="margin-bottom: 15px;">
            <?php if (!$canSetup): ?>
                <strong><i class="fa fa-info-circle"></i> Installation Required:</strong>
                Node.js >= <?php echo htmlspecialchars($prereqs['node']['min_version'] ?? '16.17.0'); ?> and the compiled server code must be installed before the service can be configured.
                <br><br>
                <?php
                $nodeVersionOk = $prereqs['node']['version_ok'] ?? false;
                $nodeInstalled = $prereqs['node']['installed'] ?? false;
                $systemNodeTooOld = !$nodeVersionOk && !empty($prereqs['node']['version']);
                ?>
                <?php if ($systemNodeTooOld): ?>
                <strong>Node.js Version Issue:</strong>
                Your system Node.js (<?php echo htmlspecialchars($prereqs['node']['version']); ?>) is older than the required version (<?php echo htmlspecialchars($prereqs['node']['min_version'] ?? '16.17.0'); ?>).
                <br><br>
                <strong>Option 1: Use nvm (Recommended)</strong>
                <pre style="margin-top: 10px; margin-bottom: 10px;">
# Install nvm (as the user who will manage Node versions)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc

# Install Node.js 16 or 18
nvm install 16</pre>
                <strong>Option 2: Upgrade system Node.js</strong>
                <pre style="margin-top: 10px; margin-bottom: 0;">
# This may affect other applications using the system Node
dnf module reset nodejs
dnf module enable nodejs:16
dnf install nodejs</pre>
                <?php else: ?>
                <strong>Quick Install Commands:</strong>
                <pre style="margin-top: 10px; margin-bottom: 0;">
# Option 1: Install via nvm (recommended for FreePBX 16)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
source ~/.bashrc
nvm install 16

# Option 2: Install system Node.js (if version >= 16.17.0 available)
dnf install nodejs

# Deploy server code to /opt/convergent-server</pre>
                <?php endif; ?>
            <?php else: ?>
                <strong><i class="fa fa-info-circle"></i> Setup Required:</strong>
                Prerequisites are installed. Click "Setup Service" to create the start script and launch via PM2.
                <?php if ($prereqs['node']['using_nvm'] ?? false): ?>
                <br><small class="text-muted">
                    <i class="fa fa-info-circle"></i> The service will be configured to use the nvm-managed Node.js at:
                    <code><?php echo htmlspecialchars($prereqs['node']['node_path'] ?? ''); ?></code>
                </small>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <button type="button" class="btn btn-primary btn-lg" id="btn-setup-service" <?php echo !$canSetup ? 'disabled' : ''; ?>>
            <i class="fa fa-cogs"></i> Setup Service
        </button>
        <?php endif; ?>

        <div id="setup-result" class="alert" style="margin-top: 15px; display: none;"></div>
    </div>
</div>

<div class="panel panel-default">
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

<!-- Version Information -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-info-circle"></i> Version Information</h3>
    </div>
    <div class="panel-body">
        <table class="table table-bordered">
            <tr>
                <th width="200">Package Name</th>
                <td><?php echo htmlspecialchars($version['name'] ?? 'unknown'); ?></td>
            </tr>
            <tr>
                <th>Installed Version</th>
                <td>
                    <?php if ($version['status']): ?>
                        <span class="label label-primary"><?php echo htmlspecialchars($version['version']); ?></span>
                    <?php else: ?>
                        <span class="text-muted">Not installed or not found</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Install Path</th>
                <td><code><?php echo htmlspecialchars($prereqs['server_code']['path'] ?? '/opt/convergent-server'); ?></code></td>
            </tr>
        </table>

        <div style="margin-top: 15px;">
            <button type="button" class="btn btn-default" id="btn-check-update">
                <i class="fa fa-cloud-download"></i> Check for Updates
            </button>
            <span id="update-status" class="text-muted" style="margin-left: 10px;"></span>
        </div>
    </div>
</div>

<!-- Module Version -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-puzzle-piece"></i> FreePBX Module Version</h3>
    </div>
    <div class="panel-body">
        <table class="table table-bordered">
            <tr>
                <th width="200">Installed Version</th>
                <td>
                    <?php if ($moduleVersion['status']): ?>
                        <span class="label label-primary"><?php echo htmlspecialchars($moduleVersion['version']); ?></span>
                    <?php else: ?>
                        <span class="text-muted">Unknown</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Repository</th>
                <td>
                    <a href="https://github.com/MatthewLJensen/convergent-connector" target="_blank">
                        <i class="fa fa-github"></i> MatthewLJensen/convergent-connector
                    </a>
                </td>
            </tr>
        </table>

        <div style="margin-top: 15px;">
            <button type="button" class="btn btn-default" id="btn-check-module-update">
                <i class="fa fa-refresh"></i> Check for Module Update
            </button>
            <span id="module-update-status" class="text-muted" style="margin-left: 10px;"></span>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
    </div>
    <div class="panel-body">
        <button type="button" class="btn btn-primary" id="btn-test-connection" <?php echo !$allReady ? 'disabled' : ''; ?>>
            <i class="fa fa-check-circle"></i> Test WebSocket Connection
        </button>

        <div id="action-result" class="alert" style="margin-top: 15px; display: none;"></div>
    </div>
</div>
