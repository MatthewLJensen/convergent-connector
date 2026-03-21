<?php
/**
 * Convergent Dashboard Server - Credentials View
 *
 * Shows live auto-detected credentials for reference (not from DB).
 * Users can compare these with Settings tab values and copy if needed.
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Get live auto-detected credentials from the system
$credentials = $convergent->getSystemCredentials();
$creds = $credentials['credentials'] ?? [];

// Get SSL source paths (config -> Advanced Settings -> version-based)
$sslPaths = $convergent->getSSLSourcePaths();
$certReadable = is_readable($sslPaths['cert']);
$keyReadable  = is_readable($sslPaths['key']);
?>

<div class="alert alert-info">
    <i class="fa fa-info-circle"></i>
    This page shows credentials <strong>auto-detected in real-time</strong> from your FreePBX/Asterisk configuration files.
    <br>
    Compare these with the values in the <a href="#settings" data-toggle="tab"><strong>Settings</strong></a> tab.
    Use the "Copy to Settings" buttons to update Settings with these detected values.
</div>

<!-- SSL Configuration -->
<div class="panel panel-info">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-lock"></i> SSL Certificate Paths
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted">
            Pre-populated from FreePBX Advanced Settings (HTTPS TLS Certificate/Key Location).
            Override in the <a href="#settings" data-toggle="tab"><strong>Settings</strong></a> tab if needed.
            Files must be readable by the <code>asterisk</code> user.
        </p>

        <table class="table table-bordered">
            <tr>
                <th width="200">Certificate Path</th>
                <td id="ssl-cert-cell">
                    <code><?php echo htmlspecialchars($sslPaths['cert']); ?></code>
                    <?php if (file_exists($sslPaths['cert'])): ?>
                        <span class="label label-success"><i class="fa fa-check"></i> Exists</span>
                    <?php else: ?>
                        <span class="label label-warning"><i class="fa fa-warning"></i> Not Found</span>
                    <?php endif; ?>
                    <?php if ($certReadable): ?>
                        <span class="label label-success"><i class="fa fa-check"></i> Readable</span>
                    <?php else: ?>
                        <span class="label label-danger"><i class="fa fa-times"></i> Not Readable</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Key Path</th>
                <td id="ssl-key-cell">
                    <code><?php echo htmlspecialchars($sslPaths['key']); ?></code>
                    <?php if (file_exists($sslPaths['key'])): ?>
                        <span class="label label-success"><i class="fa fa-check"></i> Exists</span>
                    <?php else: ?>
                        <span class="label label-warning"><i class="fa fa-warning"></i> Not Found</span>
                    <?php endif; ?>
                    <?php if ($keyReadable): ?>
                        <span class="label label-success"><i class="fa fa-check"></i> Readable</span>
                    <?php else: ?>
                        <span class="label label-danger"><i class="fa fa-times"></i> Not Readable</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if (!$certReadable || !$keyReadable): ?>
        <div class="alert alert-warning" id="ssl-warning">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>SSL certificates not readable by the asterisk user.</strong>
            The WebSocket server will start without SSL (insecure).
            <br>
            Copy the certificates to an asterisk-readable location or set custom paths in
            <a href="#settings" data-toggle="tab"><strong>Settings</strong></a>.
        </div>
        <?php endif; ?>

        <button type="button" class="btn btn-default" id="btn-redetect-ssl">
            <i class="fa fa-search"></i> Redetect SSL Paths
        </button>
        <span id="ssl-redetect-result" style="margin-left: 10px;"></span>
    </div>
</div>

<!-- ARI Credentials (Live Auto-detected) -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-code"></i> ARI (Asterisk REST Interface) - Live Detection
            <?php if ($creds['ari']['found'] ?? false): ?>
                <span class="label label-success pull-right"><i class="fa fa-check"></i> Detected</span>
            <?php else: ?>
                <span class="label label-warning pull-right"><i class="fa fa-warning"></i> Not Found</span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted">Source: <code>/etc/asterisk/ari.conf</code> and <code>/etc/asterisk/http.conf</code></p>

        <table class="table table-bordered">
            <tr>
                <th width="200">Server URL</th>
                <td><code><?php echo htmlspecialchars($creds['ari']['url'] ?? 'http://127.0.0.1:8088'); ?></code></td>
            </tr>
            <tr>
                <th>Username</th>
                <td>
                    <?php if (!empty($creds['ari']['user'])): ?>
                        <code><?php echo htmlspecialchars($creds['ari']['user']); ?></code>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Password</th>
                <td>
                    <?php if (!empty($creds['ari']['password'])): ?>
                        <span class="password-mask" data-password="<?php echo htmlspecialchars($creds['ari']['password']); ?>">
                            ************
                        </span>
                        <button type="button" class="btn btn-xs btn-default btn-show-password">
                            <i class="fa fa-eye"></i> Show
                        </button>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ($creds['ari']['found'] ?? false): ?>
        <button type="button" class="btn btn-primary btn-copy-to-settings" data-type="ari">
            <i class="fa fa-copy"></i> Copy to Settings
        </button>
        <?php else: ?>
        <div class="alert alert-warning">
            <strong>ARI credentials not found.</strong>
            Make sure ARI is enabled and configured in <code>/etc/asterisk/ari.conf</code>.
            <br><br>
            Example configuration:
            <pre>[asterisk]
type=user
password=your_password
read_only=no</pre>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- AMI Credentials (Live Auto-detected) -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-terminal"></i> AMI (Asterisk Manager Interface) - Live Detection
            <?php if ($creds['ami']['found'] ?? false): ?>
                <span class="label label-success pull-right"><i class="fa fa-check"></i> Detected</span>
            <?php else: ?>
                <span class="label label-warning pull-right"><i class="fa fa-warning"></i> Not Found</span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted">Source: <code>/etc/asterisk/manager.conf</code></p>

        <table class="table table-bordered">
            <tr>
                <th width="200">Host</th>
                <td><code><?php echo htmlspecialchars($creds['ami']['host'] ?? '127.0.0.1'); ?></code></td>
            </tr>
            <tr>
                <th>Port</th>
                <td><code><?php echo htmlspecialchars($creds['ami']['port'] ?? '5038'); ?></code></td>
            </tr>
            <tr>
                <th>Username</th>
                <td>
                    <?php if (!empty($creds['ami']['username'])): ?>
                        <code><?php echo htmlspecialchars($creds['ami']['username']); ?></code>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Password</th>
                <td>
                    <?php if (!empty($creds['ami']['password'])): ?>
                        <span class="password-mask" data-password="<?php echo htmlspecialchars($creds['ami']['password']); ?>">
                            ************
                        </span>
                        <button type="button" class="btn btn-xs btn-default btn-show-password">
                            <i class="fa fa-eye"></i> Show
                        </button>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ($creds['ami']['found'] ?? false): ?>
        <button type="button" class="btn btn-primary btn-copy-to-settings" data-type="ami">
            <i class="fa fa-copy"></i> Copy to Settings
        </button>
        <?php else: ?>
        <div class="alert alert-warning">
            <strong>AMI credentials not found.</strong>
            Make sure AMI is enabled in <code>/etc/asterisk/manager.conf</code>.
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- GraphQL Credentials (Live Auto-detected) -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-cloud"></i> FreePBX GraphQL API - Live Detection
            <?php if ($creds['graphql']['found'] ?? false): ?>
                <span class="label label-success pull-right"><i class="fa fa-check"></i> Detected</span>
            <?php else: ?>
                <span class="label label-warning pull-right"><i class="fa fa-warning"></i> Not Configured</span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="panel-body">
        <p class="text-muted">Source: FreePBX API Module database (<code>rest_applications</code> table)</p>

        <table class="table table-bordered">
            <tr>
                <th width="200">GraphQL URL</th>
                <td>
                    <?php if (!empty($creds['graphql']['url'])): ?>
                        <code><?php echo htmlspecialchars($creds['graphql']['url']); ?></code>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Token URL</th>
                <td>
                    <?php if (!empty($creds['graphql']['token_url'])): ?>
                        <code><?php echo htmlspecialchars($creds['graphql']['token_url']); ?></code>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Client ID</th>
                <td>
                    <?php if (!empty($creds['graphql']['client_id'])): ?>
                        <span class="password-mask" data-password="<?php echo htmlspecialchars($creds['graphql']['client_id']); ?>">
                            <?php echo substr($creds['graphql']['client_id'], 0, 8); ?>************
                        </span>
                        <button type="button" class="btn btn-xs btn-default btn-show-password">
                            <i class="fa fa-eye"></i> Show
                        </button>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Client Secret</th>
                <td>
                    <?php if (!empty($creds['graphql']['client_secret'])): ?>
                        <span class="password-mask" data-password="<?php echo htmlspecialchars($creds['graphql']['client_secret']); ?>">
                            ************
                        </span>
                        <button type="button" class="btn btn-xs btn-default btn-show-password">
                            <i class="fa fa-eye"></i> Show
                        </button>
                    <?php else: ?>
                        <span class="text-muted">Not configured</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ($creds['graphql']['found'] ?? false): ?>
        <button type="button" class="btn btn-primary btn-copy-to-settings" data-type="graphql">
            <i class="fa fa-copy"></i> Copy to Settings
        </button>
        <?php else: ?>
        <div class="alert alert-info">
            <strong>GraphQL API not configured.</strong>
            <p>To enable GraphQL API access:</p>
            <ol>
                <li>Go to <strong>Admin > API</strong> in FreePBX</li>
                <li>Create a new application named "convergent"</li>
                <li>Copy the Client ID and Client Secret</li>
                <li>The credentials will be automatically detected</li>
            </ol>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Actions -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-magic"></i> Actions</h3>
    </div>
    <div class="panel-body">
        <button type="button" class="btn btn-primary" id="btn-refresh-credentials">
            <i class="fa fa-refresh"></i> Refresh Detection
        </button>

        <button type="button" class="btn btn-success" id="btn-copy-all-to-settings">
            <i class="fa fa-copy"></i> Copy All Detected Credentials to Settings
        </button>

        <div id="creds-action-result" class="alert" style="margin-top: 15px; display: none;"></div>
    </div>
</div>

<script>
// Toggle password visibility
document.querySelectorAll('.btn-show-password').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var mask = this.previousElementSibling;
        var password = mask.getAttribute('data-password');
        var icon = this.querySelector('i');

        if (mask.textContent === password) {
            mask.textContent = '************';
            icon.className = 'fa fa-eye';
            this.innerHTML = '<i class="fa fa-eye"></i> Show';
        } else {
            mask.textContent = password;
            icon.className = 'fa fa-eye-slash';
            this.innerHTML = '<i class="fa fa-eye-slash"></i> Hide';
        }
    });
});

// Redetect SSL paths
document.getElementById('btn-redetect-ssl').addEventListener('click', function() {
    var btn = this;
    var resultSpan = document.getElementById('ssl-redetect-result');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Detecting...';
    resultSpan.innerHTML = '';

    $.ajax({
        url: 'ajax.php?module=convergentdashboard&command=detect_ssl_paths',
        type: 'POST',
        dataType: 'json',
        success: function(data) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-search"></i> Redetect SSL Paths';
            if (data.status) {
                resultSpan.innerHTML = '<span class="text-success"><i class="fa fa-check"></i> ' +
                    data.message + '</span>';
                // Update the displayed paths in-place
                var certCell = document.getElementById('ssl-cert-cell');
                var keyCell = document.getElementById('ssl-key-cell');
                if (certCell && data.cert) {
                    certCell.innerHTML = '<code>' + $('<span>').text(data.cert).html() + '</code> ' +
                        '<span class="label label-success"><i class="fa fa-check"></i> Exists</span> ' +
                        '<span class="label label-success"><i class="fa fa-check"></i> Readable</span>';
                }
                if (keyCell && data.key) {
                    keyCell.innerHTML = '<code>' + $('<span>').text(data.key).html() + '</code> ' +
                        '<span class="label label-success"><i class="fa fa-check"></i> Exists</span> ' +
                        '<span class="label label-success"><i class="fa fa-check"></i> Readable</span>';
                }
                // Remove warning if present
                var warning = document.getElementById('ssl-warning');
                if (warning) warning.style.display = 'none';
            } else {
                resultSpan.innerHTML = '<span class="text-danger"><i class="fa fa-times"></i> ' +
                    data.message + '</span>';
            }
        },
        error: function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-search"></i> Redetect SSL Paths';
            resultSpan.innerHTML = '<span class="text-danger"><i class="fa fa-times"></i> Request failed</span>';
        }
    });
});
</script>
