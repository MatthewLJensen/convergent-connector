<?php
/**
 * Convergent Dashboard Server - Settings View
 *
 * Reorganized form with Core Settings and credential sections with Re-detect buttons
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$cfg = $config['config'] ?? [];
?>

<form method="post" action="" class="fpbx-submit" id="convergent-settings-form">
    <input type="hidden" name="action" value="save_settings">

    <!-- Core Settings -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title"><i class="fa fa-cog"></i> Core Settings</h3>
        </div>
        <div class="panel-body">
            <!-- Dashboard URL -->
            <div class="form-group">
                <label for="dashboard_url">Dashboard URL</label>
                <input type="text" class="form-control" id="dashboard_url" name="dashboard_url"
                       value="<?php echo htmlspecialchars($cfg['dashboard_url']['value'] ?? ''); ?>"
                       placeholder="https://dashboard.example.com">
                <p class="help-block">URL of the Convergent Dashboard server (for updates and API communication)</p>
            </div>

            <!-- API Key -->
            <div class="form-group">
                <label for="convergent_api_key">Convergent API Key <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" class="form-control" id="convergent_api_key" name="convergent_api_key"
                           value="<?php echo htmlspecialchars($cfg['convergent_api_key']['value'] ?? ''); ?>"
                           placeholder="Enter your Convergent Dashboard API key" required>
                    <span class="input-group-btn">
                        <button class="btn btn-default btn-toggle-password" type="button" data-target="convergent_api_key">
                            <i class="fa fa-eye"></i>
                        </button>
                    </span>
                </div>
                <p class="help-block">API key for authenticating with the Convergent Dashboard</p>
            </div>

            <!-- WebSocket Port -->
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="client_wss_port">Client WebSocket Port</label>
                        <input type="number" class="form-control" id="client_wss_port" name="client_wss_port"
                               value="<?php echo htmlspecialchars($cfg['client_wss_port']['value'] ?? '18443'); ?>"
                               min="1024" max="65535">
                        <p class="help-block">Port for WebSocket connections (default: 18443)</p>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="alert alert-warning" style="margin-top: 25px;">
                        <i class="fa fa-exclamation-triangle"></i>
                        <strong>Firewall Note:</strong> This port must be allowed through your firewall for clients to connect.
                        <br>Example: <code>firewall-cmd --permanent --add-port=18443/tcp && firewall-cmd --reload</code>
                    </div>
                </div>
            </div>

            <!-- SSL Certificate Paths -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ssl_cert_path">SSL Certificate Path</label>
                        <input type="text" class="form-control" id="ssl_cert_path" name="ssl_cert_path"
                               value="<?php echo htmlspecialchars($cfg['ssl_cert_path']['value'] ?? ''); ?>"
                               placeholder="/etc/letsencrypt/live/example.com/fullchain.pem">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ssl_key_path">SSL Private Key Path</label>
                        <input type="text" class="form-control" id="ssl_key_path" name="ssl_key_path"
                               value="<?php echo htmlspecialchars($cfg['ssl_key_path']['value'] ?? ''); ?>"
                               placeholder="/etc/letsencrypt/live/example.com/privkey.pem">
                    </div>
                </div>
            </div>
            <p class="help-block">
                SSL certificates used by the WebSocket server. Auto-detected from FreePBX settings on install.
                Override here to use Let's Encrypt or other certificates.
            </p>

            <!-- Audio Port Range -->
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="audio_port_start">Audio Port Start</label>
                        <input type="number" class="form-control" id="audio_port_start" name="audio_port_start"
                               value="<?php echo htmlspecialchars($cfg['audio_port_start']['value'] ?? '9000'); ?>"
                               min="1024" max="65535">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="audio_port_end">Audio Port End</label>
                        <input type="number" class="form-control" id="audio_port_end" name="audio_port_end"
                               value="<?php echo htmlspecialchars($cfg['audio_port_end']['value'] ?? '10000'); ?>"
                               min="1024" max="65535">
                    </div>
                </div>
                <div class="col-md-4">
                    <p class="help-block" style="margin-top: 25px;">
                        Internal port range for Asterisk audio streaming.<br>
                        ~1000 ports = ~1000 concurrent transcriptions.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- AMI Credentials -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-terminal"></i> AMI (Asterisk Manager Interface) Credentials
                <button type="button" class="btn btn-sm btn-info pull-right btn-auto-detect" data-type="ami">
                    <i class="fa fa-magic"></i> Re-detect
                </button>
            </h3>
        </div>
        <div class="panel-body">
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                These credentials were auto-detected from <code>/etc/asterisk/manager.conf</code>.
                Click "Re-detect" to refresh or edit manually below.
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ami_host">AMI Host</label>
                        <input type="text" class="form-control" id="ami_host" name="ami_host"
                               value="<?php echo htmlspecialchars($cfg['ami_host']['value'] ?? '127.0.0.1'); ?>"
                               placeholder="127.0.0.1">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ami_port">AMI Port</label>
                        <input type="number" class="form-control" id="ami_port" name="ami_port"
                               value="<?php echo htmlspecialchars($cfg['ami_port']['value'] ?? '5038'); ?>"
                               placeholder="5038">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ami_username">AMI Username</label>
                        <input type="text" class="form-control" id="ami_username" name="ami_username"
                               value="<?php echo htmlspecialchars($cfg['ami_username']['value'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ami_password">AMI Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="ami_password" name="ami_password"
                                   value="<?php echo htmlspecialchars($cfg['ami_password']['value'] ?? ''); ?>">
                            <span class="input-group-btn">
                                <button class="btn btn-default btn-toggle-password" type="button" data-target="ami_password">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ARI Credentials -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-code"></i> ARI (Asterisk REST Interface) Credentials
                <button type="button" class="btn btn-sm btn-info pull-right btn-auto-detect" data-type="ari">
                    <i class="fa fa-magic"></i> Re-detect
                </button>
            </h3>
        </div>
        <div class="panel-body">
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                These credentials were auto-detected from <code>/etc/asterisk/ari.conf</code> and <code>http.conf</code>.
                Click "Re-detect" to refresh or edit manually below.
            </div>
            <div class="form-group">
                <label for="ari_url">ARI URL</label>
                <input type="text" class="form-control" id="ari_url" name="ari_url"
                       value="<?php echo htmlspecialchars($cfg['ari_url']['value'] ?? 'http://127.0.0.1:8088'); ?>"
                       placeholder="http://127.0.0.1:8088">
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ari_username">ARI Username</label>
                        <input type="text" class="form-control" id="ari_username" name="ari_username"
                               value="<?php echo htmlspecialchars($cfg['ari_username']['value'] ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="ari_password">ARI Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="ari_password" name="ari_password"
                                   value="<?php echo htmlspecialchars($cfg['ari_password']['value'] ?? ''); ?>">
                            <span class="input-group-btn">
                                <button class="btn btn-default btn-toggle-password" type="button" data-target="ari_password">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GraphQL Credentials -->
    <div class="panel panel-default">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-cloud"></i> FreePBX GraphQL API Credentials
                <button type="button" class="btn btn-sm btn-info pull-right btn-auto-detect" data-type="graphql">
                    <i class="fa fa-magic"></i> Re-detect
                </button>
            </h3>
        </div>
        <div class="panel-body">
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i>
                These credentials are read from the FreePBX API module database (<code>rest_applications</code> table).
                Click "Re-detect" to refresh or edit manually below.
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="gql_url">GraphQL URL</label>
                        <input type="text" class="form-control" id="gql_url" name="gql_url"
                               value="<?php echo htmlspecialchars($cfg['gql_url']['value'] ?? ''); ?>"
                               placeholder="https://hostname:443/admin/api/api/gql">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="gql_token_url">Token URL</label>
                        <input type="text" class="form-control" id="gql_token_url" name="gql_token_url"
                               value="<?php echo htmlspecialchars($cfg['gql_token_url']['value'] ?? ''); ?>"
                               placeholder="https://hostname:443/admin/api/api/token">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="gql_client_id">Client ID</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="gql_client_id" name="gql_client_id"
                                   value="<?php echo htmlspecialchars($cfg['gql_client_id']['value'] ?? ''); ?>">
                            <span class="input-group-btn">
                                <button class="btn btn-default btn-toggle-password" type="button" data-target="gql_client_id">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="gql_client_secret">Client Secret</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="gql_client_secret" name="gql_client_secret"
                                   value="<?php echo htmlspecialchars($cfg['gql_client_secret']['value'] ?? ''); ?>">
                            <span class="input-group-btn">
                                <button class="btn btn-default btn-toggle-password" type="button" data-target="gql_client_secret">
                                    <i class="fa fa-eye"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Buttons -->
    <div class="row">
        <div class="col-md-12">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fa fa-save"></i> Save Settings
            </button>
            <button type="button" class="btn btn-warning btn-lg" id="btn-save-and-restart" style="margin-left: 10px;">
                <i class="fa fa-refresh"></i> Save & Restart Service
            </button>
            <button type="button" class="btn btn-info btn-lg" id="btn-auto-detect-all" style="margin-left: 10px;">
                <i class="fa fa-magic"></i> Re-detect All Credentials
            </button>
        </div>
    </div>
</form>

<!-- Result display -->
<div id="settings-action-result" class="alert" style="margin-top: 15px; display: none;"></div>
