/**
 * Convergent Dashboard Server - FreePBX Module
 * JavaScript
 */

(function($) {
    'use strict';

    // Module namespace
    var Convergent = {
        // Store detected credentials for copy-to-settings functionality
        detectedCredentials: null,

        // Initialize the module
        init: function() {
            var self = this;

            // Track which tab is active (from query param on load)
            var params = new URLSearchParams(window.location.search);
            this.currentTab = params.get('tab') || 'status';

            // Update on tab switch
            $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
                self.currentTab = $(e.target).attr('href').replace('#', '');
            });

            this.bindEvents();
            this.initPasswordToggles();
        },

        // Track the current tab (updated on tab switch)
        currentTab: 'status',

        // Reload the page while preserving the active tab via ?tab= query param
        reloadPage: function() {
            var params = new URLSearchParams(window.location.search);
            params.delete('tab');
            if (this.currentTab && this.currentTab !== 'status') {
                params.set('tab', this.currentTab);
            }
            window.location.href = window.location.pathname + '?' + params.toString();
        },

        // Bind event handlers
        bindEvents: function() {
            var self = this;

            // Service action buttons
            $(document).on('click', '.btn-service-action', function() {
                var action = $(this).data('action');
                self.serviceAction(action);
            });

            // Refresh status button
            $('#btn-refresh-status').on('click', function() {
                self.refreshStatus();
            });

            // Check for updates button
            $('#btn-check-update').on('click', function() {
                self.checkForUpdates();
            });

            // Test connection button
            $('#btn-test-connection').on('click', function() {
                self.testConnection();
            });

            // Refresh credentials button (credentials tab)
            $('#btn-refresh-credentials').on('click', function() {
                self.reloadPage();
            });

            // Save and restart button
            $('#btn-save-and-restart').on('click', function() {
                self.saveAndRestart();
            });

            // Auto-detect buttons in settings tab (per-section)
            $(document).on('click', '.btn-auto-detect', function() {
                var type = $(this).data('type');
                self.autoDetectCredentials(type);
            });

            // Auto-detect all credentials button
            $('#btn-auto-detect-all').on('click', function() {
                self.autoDetectAllCredentials();
            });

            // Copy to settings buttons (from credentials tab)
            $(document).on('click', '.btn-copy-to-settings', function() {
                var type = $(this).data('type');
                self.copyToSettings(type);
            });

            // Copy all to settings button
            $('#btn-copy-all-to-settings').on('click', function() {
                self.copyAllToSettings();
            });

            // Setup service button
            $('#btn-setup-service').on('click', function() {
                self.setupService();
            });

            // Check for module update button
            $('#btn-check-module-update').on('click', function() {
                self.checkModuleUpdate();
            });
        },

        // Initialize password toggle buttons
        initPasswordToggles: function() {
            $('.btn-toggle-password').on('click', function() {
                var targetId = $(this).data('target');
                var $input = $('#' + targetId);
                var $icon = $(this).find('i');

                if ($input.attr('type') === 'password') {
                    $input.attr('type', 'text');
                    $icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    $input.attr('type', 'password');
                    $icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
        },

        // Perform service action (start, stop, restart, enable, disable)
        serviceAction: function(action) {
            var self = this;
            var $btn = $('.btn-service-action[data-action="' + action + '"]');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> ' + action + '...');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'service_action',
                    action: action
                },
                success: function(response) {
                    if (response.status) {
                        self.showResult('success', response.message);
                        // Refresh status after action
                        setTimeout(function() {
                            self.refreshStatus();
                        }, 1000);
                    } else {
                        self.showResult('danger', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showResult('danger', 'Request failed: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Refresh service status
        refreshStatus: function() {
            var $btn = $('#btn-refresh-status');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Refreshing...');

            // Simply reload the page to get fresh data
            setTimeout(function() {
                location.reload();
            }, 500);
        },

        // Check for updates
        checkForUpdates: function() {
            var self = this;
            var $btn = $('#btn-check-update');
            var $status = $('#update-status');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Checking...');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'check_update'
                },
                success: function(response) {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (response.update_available) {
                        var html = '<span class="text-success"><i class="fa fa-arrow-circle-up"></i> Update available: v' + self.escapeHtml(response.latest_version) + '</span>';
                        if (response.release_notes) {
                            html += '<br><small class="text-muted">' + self.escapeHtml(response.release_notes) + '</small>';
                        }
                        html += '<br><button type="button" class="btn btn-success btn-sm" id="btn-download-install" style="margin-top: 8px;"'
                              + ' data-version="' + self.escapeHtml(response.latest_version) + '"'
                              + ' data-sha256="' + self.escapeHtml(response.sha256 || '') + '">'
                              + '<i class="fa fa-download"></i> Download &amp; Install v' + self.escapeHtml(response.latest_version)
                              + '</button>';
                        $status.html(html);

                        // Bind the download button
                        $('#btn-download-install').on('click', function() {
                            var version = $(this).data('version');
                            var sha256 = $(this).data('sha256');
                            self.downloadAndInstall(version, sha256);
                        });
                    } else {
                        $status.html('<span class="text-muted"><i class="fa fa-check-circle"></i> ' + self.escapeHtml(response.message) + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).html(originalHtml);
                    $status.html('<span class="text-danger"><i class="fa fa-times-circle"></i> Check failed: ' + error + '</span>');
                }
            });
        },

        // Download and install an update
        downloadAndInstall: function(version, sha256) {
            var self = this;
            var $btn = $('#btn-download-install');
            var $status = $('#update-status');

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Downloading...');

            // Step 1: Download
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'download_update',
                    version: version
                },
                success: function(response) {
                    if (response.status) {
                        $btn.html('<span class="loading-spinner"></span> Installing...');
                        $status.prepend('<br><small class="text-muted">Downloaded. Installing — this may take a minute...</small>');

                        // Step 2: Apply
                        $.ajax({
                            url: 'ajax.php',
                            type: 'POST',
                            dataType: 'json',
                            timeout: 150000, // 2.5 min for npm ci
                            data: {
                                module: 'convergentdashboard',
                                command: 'apply_update',
                                version: version,
                                sha256: sha256 || ''
                            },
                            success: function(applyResponse) {
                                // Build steps list if available
                                var stepsHtml = '';
                                if (applyResponse.steps && applyResponse.steps.length > 0) {
                                    stepsHtml = '<ul style="margin-top:8px;margin-bottom:8px;">';
                                    for (var i = 0; i < applyResponse.steps.length; i++) {
                                        stepsHtml += '<li>' + self.escapeHtml(applyResponse.steps[i]) + '</li>';
                                    }
                                    stepsHtml += '</ul>';
                                }
                                if (applyResponse.status) {
                                    $status.html('<span class="text-success"><i class="fa fa-check-circle"></i> '
                                        + self.escapeHtml(applyResponse.message)
                                        + '</span>' + stepsHtml + '<em>Reloading page...</em>');
                                    setTimeout(function() { location.reload(); }, 3000);
                                } else {
                                    var errHtml = '<span class="text-danger"><i class="fa fa-times-circle"></i> Install failed: '
                                        + self.escapeHtml(applyResponse.message) + '</span>' + stepsHtml;
                                    if (applyResponse.npm_output) {
                                        errHtml += '<pre style="max-height:200px;overflow:auto;margin-top:8px;">' + self.escapeHtml(applyResponse.npm_output) + '</pre>';
                                    }
                                    errHtml += '<br><button type="button" class="btn btn-warning btn-sm" style="margin-top:8px;" onclick="location.reload()">'
                                        + '<i class="fa fa-refresh"></i> Refresh</button>';
                                    $status.html(errHtml);
                                }
                            },
                            error: function(xhr, status, error) {
                                $status.html('<span class="text-danger"><i class="fa fa-times-circle"></i> Install request failed: '
                                    + error + '</span>'
                                    + '<br><small class="text-muted">The update may still be in progress. Wait a moment and refresh.</small>'
                                    + '<br><button type="button" class="btn btn-warning btn-sm" style="margin-top:8px;" onclick="location.reload()">'
                                    + '<i class="fa fa-refresh"></i> Refresh</button>');
                            }
                        });
                    } else {
                        $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Retry Download');
                        $status.prepend('<br><span class="text-danger">Download failed: ' + self.escapeHtml(response.message) + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Retry Download');
                    $status.prepend('<br><span class="text-danger">Download request failed: ' + error + '</span>');
                }
            });
        },

        // Test WebSocket connection
        testConnection: function() {
            var self = this;
            var $btn = $('#btn-test-connection');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Testing...');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'test_connection'
                },
                success: function(response) {
                    if (response.status) {
                        self.showResult('success', response.message + ' (' + response.url + ')');
                    } else {
                        self.showResult('warning', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showResult('danger', 'Connection test failed: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Auto-detect credentials for a specific type and update form fields
        autoDetectCredentials: function(type) {
            var self = this;
            var $btn = $('.btn-auto-detect[data-type="' + type + '"]');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Detecting...');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'get_credentials'
                },
                success: function(response) {
                    if (response.status && response.credentials) {
                        self.detectedCredentials = response.credentials;
                        self.fillCredentialFields(type, response.credentials);
                        self.showSettingsResult('success', type.toUpperCase() + ' credentials updated from auto-detection');
                    } else {
                        self.showSettingsResult('danger', 'Failed to detect credentials');
                    }
                },
                error: function(xhr, status, error) {
                    self.showSettingsResult('danger', 'Detection failed: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Auto-detect all credentials and save to DB
        autoDetectAllCredentials: function() {
            var self = this;
            var $btn = $('#btn-auto-detect-all');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Detecting...');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'auto_detect_credentials'
                },
                success: function(response) {
                    if (response.status) {
                        self.showSettingsResult('success', 'All credentials auto-detected and saved. Reloading page...');
                        setTimeout(function() {
                            self.reloadPage();
                        }, 1500);
                    } else {
                        self.showSettingsResult('danger', 'Failed to auto-detect credentials');
                    }
                },
                error: function(xhr, status, error) {
                    self.showSettingsResult('danger', 'Auto-detection failed: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Fill form fields with detected credentials
        fillCredentialFields: function(type, credentials) {
            if (type === 'ami' && credentials.ami) {
                $('#ami_host').val(credentials.ami.host || '127.0.0.1');
                $('#ami_port').val(credentials.ami.port || '5038');
                $('#ami_username').val(credentials.ami.username || '');
                $('#ami_password').val(credentials.ami.password || '');
            } else if (type === 'ari' && credentials.ari) {
                $('#ari_url').val(credentials.ari.url || 'http://127.0.0.1:8088');
                $('#ari_username').val(credentials.ari.user || '');
                $('#ari_password').val(credentials.ari.password || '');
            } else if (type === 'graphql' && credentials.graphql) {
                $('#gql_url').val(credentials.graphql.url || '');
                $('#gql_token_url').val(credentials.graphql.token_url || '');
                $('#gql_client_id').val(credentials.graphql.client_id || '');
                $('#gql_client_secret').val(credentials.graphql.client_secret || '');
            }
        },

        // Copy detected credentials to settings (from credentials tab)
        copyToSettings: function(type) {
            var self = this;

            // First fetch the credentials
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'get_credentials'
                },
                success: function(response) {
                    if (response.status && response.credentials) {
                        self.detectedCredentials = response.credentials;
                        // Switch to settings tab
                        $('a[href="#settings"]').tab('show');
                        // Fill the fields
                        setTimeout(function() {
                            self.fillCredentialFields(type, response.credentials);
                            self.showSettingsResult('success', type.toUpperCase() + ' credentials copied. Click "Save Settings" to save.');
                        }, 300);
                    } else {
                        self.showCredsResult('danger', 'Failed to fetch credentials');
                    }
                },
                error: function(xhr, status, error) {
                    self.showCredsResult('danger', 'Failed to fetch credentials: ' + error);
                }
            });
        },

        // Copy all detected credentials to settings
        copyAllToSettings: function() {
            var self = this;

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'get_credentials'
                },
                success: function(response) {
                    if (response.status && response.credentials) {
                        self.detectedCredentials = response.credentials;
                        // Switch to settings tab
                        $('a[href="#settings"]').tab('show');
                        // Fill all fields
                        setTimeout(function() {
                            self.fillCredentialFields('ami', response.credentials);
                            self.fillCredentialFields('ari', response.credentials);
                            self.fillCredentialFields('graphql', response.credentials);
                            self.showSettingsResult('success', 'All detected credentials copied. Click "Save Settings" to save.');
                        }, 300);
                    } else {
                        self.showCredsResult('danger', 'Failed to fetch credentials');
                    }
                },
                error: function(xhr, status, error) {
                    self.showCredsResult('danger', 'Failed to fetch credentials: ' + error);
                }
            });
        },

        // Save settings and restart service
        saveAndRestart: function() {
            var self = this;
            var $form = $('#convergent-settings-form');
            var $btn = $('#btn-save-and-restart');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Saving...');

            // First submit the form
            $.ajax({
                url: '',
                type: 'POST',
                data: $form.serialize(),
                success: function() {
                    // Then restart service
                    self.serviceAction('restart');
                    self.showSettingsResult('success', 'Settings saved. Service restarting...');
                },
                error: function(xhr, status, error) {
                    self.showSettingsResult('danger', 'Save failed: ' + error);
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Show result in action-result div (status tab)
        showResult: function(type, message) {
            var $result = $('#action-result');
            $result.removeClass('alert-success alert-danger alert-warning alert-info')
                   .addClass('alert-' + type)
                   .html(message)
                   .fadeIn();

            // Auto-hide after 10 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    $result.fadeOut();
                }, 10000);
            }
        },

        // Show result in settings tab
        showSettingsResult: function(type, message) {
            var $result = $('#settings-action-result');
            if ($result.length === 0) {
                $result = $('#action-result');
            }
            $result.removeClass('alert-success alert-danger alert-warning alert-info')
                   .addClass('alert-' + type)
                   .html(message)
                   .fadeIn();

            if (type === 'success') {
                setTimeout(function() {
                    $result.fadeOut();
                }, 10000);
            }
        },

        // Show result in credentials tab
        showCredsResult: function(type, message) {
            var $result = $('#creds-action-result');
            $result.removeClass('alert-success alert-danger alert-warning alert-info')
                   .addClass('alert-' + type)
                   .html(message)
                   .fadeIn();

            if (type === 'success') {
                setTimeout(function() {
                    $result.fadeOut();
                }, 10000);
            }
        },

        // Setup systemd service and sudoers
        setupService: function() {
            var self = this;
            var $btn = $('#btn-setup-service');
            var $result = $('#setup-result');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Setting up service...');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'setup_service'
                },
                success: function(response) {
                    if (response.status) {
                        var msg = '<strong>' + response.message + '</strong>';
                        if (response.details && response.details.length > 0) {
                            msg += '<ul style="margin-top: 10px; margin-bottom: 0;">';
                            for (var i = 0; i < response.details.length; i++) {
                                msg += '<li>' + response.details[i] + '</li>';
                            }
                            msg += '</ul>';
                        }
                        // Show nvm info if applicable
                        if (response.using_nvm) {
                            msg += '<p style="margin-top: 10px;"><i class="fa fa-info-circle"></i> <strong>Using nvm-managed Node.js:</strong></p>';
                            msg += '<ul><li>Node: <code>' + self.escapeHtml(response.node_path || '') + '</code></li></ul>';
                        }
                        msg += '<br><em>Refreshing page...</em>';
                        $result.removeClass('alert-danger alert-warning alert-info').addClass('alert-success').html(msg).fadeIn();

                        // Reload page after short delay
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else if (response.manual_setup) {
                        // Manual setup required - show commands
                        var msg = '<strong>' + response.message + '</strong>';
                        // Show nvm info if applicable
                        if (response.using_nvm) {
                            msg += '<div class="alert alert-info" style="margin-top: 10px; margin-bottom: 10px;">';
                            msg += '<i class="fa fa-info-circle"></i> <strong>Note:</strong> The service is configured to use nvm-managed Node.js:';
                            msg += '<br>Node: <code>' + self.escapeHtml(response.node_path || '') + '</code>';
                            msg += '</div>';
                        }
                        msg += '<p style="margin-top: 15px;">Copy and run the following commands as root on your FreePBX server:</p>';
                        msg += '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 4px; overflow-x: auto; max-height: 400px;">';
                        for (var i = 0; i < response.commands.length; i++) {
                            var line = response.commands[i];
                            // Syntax highlight comments
                            if (line.indexOf('#') === 0) {
                                msg += '<span style="color: #6a9955;">' + self.escapeHtml(line) + '</span>\n';
                            } else if (line === '') {
                                msg += '\n';
                            } else {
                                msg += self.escapeHtml(line) + '\n';
                            }
                        }
                        msg += '</pre>';
                        msg += '<button type="button" class="btn btn-default btn-sm" onclick="Convergent.copySetupCommands()"><i class="fa fa-copy"></i> Copy Commands</button>';
                        msg += '<button type="button" class="btn btn-info btn-sm" style="margin-left: 10px;" onclick="location.reload()"><i class="fa fa-refresh"></i> Refresh After Running</button>';

                        // Store commands for copy function
                        self.setupCommands = response.commands.join('\n');

                        $result.removeClass('alert-success alert-danger').addClass('alert-warning').html(msg).fadeIn();
                        $btn.prop('disabled', false).html(originalHtml);
                    } else {
                        var msg = '<strong>' + response.message + '</strong>';
                        if (response.errors && response.errors.length > 0) {
                            msg += '<ul style="margin-top: 10px; margin-bottom: 0;">';
                            for (var i = 0; i < response.errors.length; i++) {
                                msg += '<li class="text-danger">' + response.errors[i] + '</li>';
                            }
                            msg += '</ul>';
                        }
                        if (response.details && response.details.length > 0) {
                            msg += '<p style="margin-top: 10px;"><strong>Completed:</strong></p><ul>';
                            for (var i = 0; i < response.details.length; i++) {
                                msg += '<li class="text-success">' + response.details[i] + '</li>';
                            }
                            msg += '</ul>';
                        }
                        $result.removeClass('alert-success alert-info').addClass('alert-danger').html(msg).fadeIn();
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    $result.removeClass('alert-success alert-info').addClass('alert-danger')
                           .html('<strong>Setup failed:</strong> ' + error)
                           .fadeIn();
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        },

        // Copy setup commands to clipboard
        copySetupCommands: function() {
            var self = this;
            if (self.setupCommands) {
                navigator.clipboard.writeText(self.setupCommands).then(function() {
                    alert('Commands copied to clipboard!');
                }).catch(function() {
                    // Fallback for older browsers
                    var textarea = document.createElement('textarea');
                    textarea.value = self.setupCommands;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    alert('Commands copied to clipboard!');
                });
            }
        },

        // Check GitHub for a newer module version
        checkModuleUpdate: function() {
            var self = this;
            var $btn    = $('#btn-check-module-update');
            var $status = $('#module-update-status');
            var originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Checking...');
            $status.html('');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    module: 'convergentdashboard',
                    command: 'check_module_update'
                },
                success: function(response) {
                    $btn.prop('disabled', false).html(originalHtml);
                    if (!response.status) {
                        $status.html('<span class="text-danger"><i class="fa fa-times-circle"></i> ' + self.escapeHtml(response.message) + '</span>');
                        return;
                    }
                    if (response.update_available) {
                        var html = '<span class="text-success"><i class="fa fa-arrow-circle-up"></i> ' + self.escapeHtml(response.message) + '</span>';
                        if (response.release_notes) {
                            html += '<br><small class="text-muted">' + self.escapeHtml(response.release_notes) + '</small>';
                        }
                        html += '<br><button type="button" class="btn btn-success btn-sm" id="btn-install-module-update" style="margin-top: 8px;"'
                              + ' data-tag="' + self.escapeHtml(response.tag_name) + '"'
                              + ' data-zipball="' + self.escapeHtml(response.zipball_url) + '">'
                              + '<i class="fa fa-download"></i> Install v' + self.escapeHtml(response.latest_version) + '</button>';
                        $status.html(html);

                        $('#btn-install-module-update').on('click', function() {
                            self.installModuleUpdate($(this).data('tag'), $(this).data('zipball'));
                        });
                    } else {
                        $status.html('<span class="text-muted"><i class="fa fa-check-circle"></i> ' + self.escapeHtml(response.message) + '</span>');
                    }
                },
                error: function(xhr, status, error) {
                    $btn.prop('disabled', false).html(originalHtml);
                    $status.html('<span class="text-danger"><i class="fa fa-times-circle"></i> Check failed: ' + error + '</span>');
                }
            });
        },

        // Download and install a module update from GitHub
        installModuleUpdate: function(tagName, zipballUrl) {
            var self = this;
            var $btn    = $('#btn-install-module-update');
            var $status = $('#module-update-status');

            $btn.prop('disabled', true).html('<span class="loading-spinner"></span> Installing...');
            $status.find('span:first').html('<span class="text-info"><i class="fa fa-spinner fa-spin"></i> Downloading and installing — this may take a moment...</span>');

            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                dataType: 'json',
                timeout: 90000,
                data: {
                    module:      'convergentdashboard',
                    command:     'install_module_update',
                    tag_name:    tagName,
                    zipball_url: zipballUrl
                },
                success: function(response) {
                    if (response.status) {
                        $status.html('<span class="text-success"><i class="fa fa-check-circle"></i> ' + self.escapeHtml(response.message) + '</span>');
                        setTimeout(function() { location.reload(); }, 2000);
                    } else {
                        $status.html('<span class="text-danger"><i class="fa fa-times-circle"></i> ' + self.escapeHtml(response.message) + '</span>');
                        $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Retry');
                    }
                },
                error: function(xhr, status, error) {
                    $status.html('<span class="text-danger"><i class="fa fa-times-circle"></i> Request failed: ' + error
                        + '</span><br><small class="text-muted">Check /tmp/convergent-installmodule.log on the server.</small>');
                    $btn.prop('disabled', false).html('<i class="fa fa-download"></i> Retry');
                }
            });
        },

        // HTML escape helper
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    // Make Convergent available globally for onclick handlers
    window.Convergent = Convergent;

    // Initialize on document ready
    $(document).ready(function() {
        Convergent.init();
    });

})(jQuery);
