<?php
/**
 * Convergent Dashboard Server - Status View
 *
 * The slow panels (prerequisites: Node.js detection; service: PM2 status;
 * certificate) are not rendered here. Each is a placeholder that
 * assets/js/convergent.js fills in from its own AJAX request
 * (command=status_section), so the page loads straight away and each panel
 * appears as soon as its checks finish. See views/status_*.php.
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
?>

<div class="cvg-status-section" data-section="prereqs">
    <div class="panel panel-default cvg-section-loading">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-check-circle"></i> Service Prerequisites</h3>
        </div>
        <div class="panel-body text-muted">
            <i class="fa fa-spinner fa-spin"></i> Checking prerequisites&hellip;
        </div>
    </div>
</div>

<div class="cvg-status-section" data-section="service">
    <div class="panel panel-default cvg-section-loading">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-tachometer"></i> Service Status</h3>
        </div>
        <div class="panel-body text-muted">
            <i class="fa fa-spinner fa-spin"></i> Checking service status&hellip;
        </div>
    </div>
</div>

<div class="cvg-status-section" data-section="cert">
    <div class="panel panel-default cvg-section-loading">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-lock"></i> SSL Certificate</h3>
        </div>
        <div class="panel-body text-muted">
            <i class="fa fa-spinner fa-spin"></i> Reading certificate&hellip;
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
                <td><code>/opt/convergent-server</code></td>
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
            <button type="button" class="btn btn-warning" id="btn-force-reinstall-module"
                    title="Re-download and reinstall the latest release even if you're already on it (for testing the update flow)">
                <i class="fa fa-recycle"></i> Force Reinstall
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
        <button type="button" class="btn btn-primary" id="btn-test-connection" disabled>
            <i class="fa fa-check-circle"></i> Test WebSocket Connection
        </button>

        <div id="action-result" class="alert" style="margin-top: 15px; display: none;"></div>
    </div>
</div>
