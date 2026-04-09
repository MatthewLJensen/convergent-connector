<?php
/**
 * Convergent Dashboard Server - FreePBX Module
 * Main Module Class
 *
 * Manages the Convergent Dashboard Server service
 */

namespace FreePBX\modules;

class Convergentdashboard extends \FreePBX_Helpers implements \BMO
{
    private $db;
    private $freepbx;

    public function __construct($freepbx = null)
    {
        if ($freepbx === null) {
            $freepbx = \FreePBX::create();
        }
        $this->freepbx = $freepbx;
        $this->db = $freepbx->Database;
    }

    /**
     * BMO Required Methods
     */
    /** Where the server code lives */
    private const INSTALL_PATH = '/opt/convergent-server';

    /** GitHub API base for this module's releases */
    private const MODULE_GITHUB_API = 'https://api.github.com/repos/MatthewLJensen/convergent-connector';

    public function install()
    {
        // install() runs as root via fwconsole

        // Create the server install directory + recordings subdirectory
        // owned by asterisk so the service can write to it
        $dirs = [
            self::INSTALL_PATH,
            self::INSTALL_PATH . '/recordings',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            chown($dir, 'asterisk');
            chgrp($dir, 'asterisk');
        }
    }

    public function uninstall()
    {
        try {
            \FreePBX::Pm2()->delete("convergent-monitoring");
        } catch (\Exception $e) {
            // Process may not exist
        }
        $startScript = '/var/lib/asterisk/convergent-start.sh';
        if (file_exists($startScript)) {
            @unlink($startScript);
        }
    }

    public function backup() {}
    public function restore($backup) {}

    public function doConfigPageInit($page)
    {
        // Handle form submissions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_settings':
                    $this->saveSettings($_POST);
                    break;
            }
        }
    }

    /**
     * AJAX Request Handler - Gate which commands are allowed
     */
    public function ajaxRequest($req, &$setting)
    {
        $allowed = [
            'get_status',
            'get_config',
            'save_config',
            'get_version',
            'check_update',
            'service_action',
            'get_credentials',
            'auto_detect_credentials',
            'test_connection',
            'get_journal',
            'check_prerequisites',
            'setup_service',
            'detect_ssl_paths',
            'test_hook',
            'download_update',
            'apply_update',
            'check_module_update',
            'install_module_update',
        ];
        return in_array($req, $allowed);
    }

    /**
     * AJAX Handler - Process AJAX requests
     */
    public function ajaxHandler()
    {
        $request = $_REQUEST['command'] ?? '';

        switch ($request) {
            case 'get_status':
                return $this->getServiceStatus();

            case 'get_config':
                return $this->getAllConfig();

            case 'save_config':
                $key = $_POST['key'] ?? '';
                $value = $_POST['value'] ?? '';
                return $this->setConfigValue($key, $value);

            case 'get_version':
                return $this->getInstalledVersion();

            case 'check_update':
                return $this->checkForUpdates();

            case 'service_action':
                $action = $_POST['action'] ?? '';
                return $this->serviceAction($action);

            case 'get_credentials':
                return $this->getSystemCredentials();

            case 'auto_detect_credentials':
                return $this->autoDetectAndSaveCredentials();

            case 'test_connection':
                return $this->testConnection();

            case 'get_journal':
                $lines = isset($_REQUEST['lines']) ? (int)$_REQUEST['lines'] : 100;
                return $this->getJournalLogs($lines);

            case 'check_prerequisites':
                return $this->checkPrerequisites();

            case 'setup_service':
                return $this->setupService();

            case 'detect_ssl_paths':
                return $this->detectSSLPaths();

            case 'test_hook':
                return $this->testHook();

            case 'download_update':
                $version = $_POST['version'] ?? '';
                return $this->downloadUpdate($version);

            case 'apply_update':
                $version = $_POST['version'] ?? '';
                $sha256 = $_POST['sha256'] ?? '';
                return $this->applyUpdate($version, $sha256);

            case 'check_module_update':
                return $this->checkModuleUpdate();

            case 'install_module_update':
                $tagName    = $_POST['tag_name']    ?? '';
                $zipballUrl = $_POST['zipball_url']  ?? '';
                return $this->downloadInstallModule($tagName, $zipballUrl);

            default:
                return ['status' => false, 'message' => 'Unknown command'];
        }
    }

    /**
     * Configuration Management
     */
    public function getConfigValue($key)
    {
        $sql = "SELECT `value` FROM `convergent_config` WHERE `key` = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$key]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['value'] : null;
    }

    public function setConfigValue($key, $value)
    {
        $sql = "UPDATE `convergent_config` SET `value` = ? WHERE `key` = ?";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$value, $key]);

        if ($result) {
            return ['status' => true, 'message' => 'Configuration saved'];
        }
        return ['status' => false, 'message' => 'Failed to save configuration'];
    }

    public function getAllConfig()
    {
        $sql = "SELECT `key`, `value`, `description` FROM `convergent_config`";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $config = [];
        foreach ($results as $row) {
            $config[$row['key']] = [
                'value' => $row['value'],
                'description' => $row['description']
            ];
        }

        return ['status' => true, 'config' => $config];
    }

    public function saveSettings($data)
    {
        // 16 config keys as per the plan
        $configKeys = [
            // Core Settings (6 keys)
            'dashboard_url',
            'convergent_api_key',
            'client_wss_port',
            'audio_port_start',
            'audio_port_end',
            'speech_lang',
            // AMI credentials (4 keys)
            'ami_host', 'ami_port', 'ami_username', 'ami_password',
            // ARI credentials (3 keys)
            'ari_url', 'ari_username', 'ari_password',
            // GraphQL credentials (4 keys)
            'gql_url', 'gql_token_url', 'gql_client_id', 'gql_client_secret',
            // SSL source paths (2 keys)
            'ssl_cert_path', 'ssl_key_path'
        ];

        foreach ($configKeys as $key) {
            if (isset($data[$key])) {
                $this->setConfigValue($key, trim($data[$key]));
            }
        }

        // Send SIGHUP to refresh config in running service
        $this->signalConfigRefresh();

        return ['status' => true, 'message' => 'Settings saved successfully'];
    }

    /**
     * Send SIGHUP to the convergent-monitoring service to refresh config
     */
    private function signalConfigRefresh()
    {
        $status = \FreePBX::Pm2()->getStatus("convergent-monitoring");
        if ($status && !empty($status['pid'])) {
            $killed = posix_kill((int)$status['pid'], 1); // SIGHUP
            if ($killed) {
                freepbx_log(FPBX_LOG_NOTICE, "Sent SIGHUP to convergent-monitoring (PID {$status['pid']})");
            } else {
                freepbx_log(FPBX_LOG_NOTICE, "Failed to send SIGHUP to convergent-monitoring");
            }
        }
    }

    /**
     * Auto-detect credentials and save to database
     */
    public function autoDetectAndSaveCredentials()
    {
        $credentials = $this->getSystemCredentials();
        $creds = $credentials['credentials'] ?? [];
        $updated = [];

        // Save AMI credentials
        if (!empty($creds['ami']['found'])) {
            $this->setConfigValue('ami_host', $creds['ami']['host'] ?? '127.0.0.1');
            $this->setConfigValue('ami_port', $creds['ami']['port'] ?? '5038');
            $this->setConfigValue('ami_username', $creds['ami']['username'] ?? '');
            $this->setConfigValue('ami_password', $creds['ami']['password'] ?? '');
            $updated['ami'] = true;
        }

        // Save ARI credentials
        if (!empty($creds['ari']['found'])) {
            $this->setConfigValue('ari_url', $creds['ari']['url'] ?? 'http://127.0.0.1:8088');
            $this->setConfigValue('ari_username', $creds['ari']['user'] ?? '');
            $this->setConfigValue('ari_password', $creds['ari']['password'] ?? '');
            $updated['ari'] = true;
        }

        // Save GraphQL credentials
        if (!empty($creds['graphql']['found'])) {
            $this->setConfigValue('gql_url', $creds['graphql']['url'] ?? '');
            $this->setConfigValue('gql_token_url', $creds['graphql']['token_url'] ?? '');
            $this->setConfigValue('gql_client_id', $creds['graphql']['client_id'] ?? '');
            $this->setConfigValue('gql_client_secret', $creds['graphql']['client_secret'] ?? '');
            $updated['graphql'] = true;
        }

        // Detect and save SSL paths
        $sslResult = $this->detectSSLPaths();
        if ($sslResult['status']) {
            $updated['ssl'] = true;
        }

        // Send SIGHUP to refresh config in running service
        $this->signalConfigRefresh();

        return [
            'status' => true,
            'message' => 'Credentials auto-detected and saved',
            'updated' => $updated,
            'credentials' => $creds
        ];
    }

    /**
     * Get SSL certificate paths that the service should use.
     * Alias for getSSLSourcePaths() — reads from config, then auto-detects.
     */
    public function getSSLPaths()
    {
        return $this->getSSLSourcePaths();
    }

    /**
     * Get SSL paths with full priority resolution:
     * 1. convergent_config (user override / saved detection)
     * 2. FreePBX Advanced Settings (HTTPTLSCERTFILE / HTTPTLSPRIVATEKEY)
     * 3. Version-based hardcoded paths
     */
    public function getSSLSourcePaths()
    {
        // 1. Check convergent_config table
        try {
            $certPath = $this->getConfigValue('ssl_cert_path');
            $keyPath  = $this->getConfigValue('ssl_key_path');
            if (!empty($certPath) && !empty($keyPath)) {
                return ['cert' => $certPath, 'key' => $keyPath];
            }
        } catch (\Exception $e) {
            // Table may not exist during install
        }

        // 2. Try FreePBX Advanced Settings
        try {
            $certPath = \FreePBX::Config()->get('HTTPTLSCERTFILE');
            $keyPath  = \FreePBX::Config()->get('HTTPTLSPRIVATEKEY');
            if (!empty($certPath) && !empty($keyPath)) {
                return ['cert' => $certPath, 'key' => $keyPath];
            }
        } catch (\Exception $e) {
            // Config not available during install
        }

        // 3. Fallback: version-based paths
        $version = $this->getFreePBXVersion();
        if ($version && $version >= 17) {
            return [
                'cert' => '/etc/apache2/pki/webserver.crt',
                'key'  => '/etc/apache2/pki/webserver.key',
            ];
        }
        return [
            'cert' => '/etc/httpd/pki/webserver.crt',
            'key'  => '/etc/httpd/pki/webserver.key',
        ];
    }

    /**
     * Detect readable SSL paths and save to convergent_config.
     * Called from "Redetect" button and autoDetectAndSaveCredentials().
     * Runs as asterisk user from web UI, so is_readable() reflects actual usability.
     */
    public function detectSSLPaths()
    {
        $attempted = [];

        // Try FreePBX Advanced Settings paths
        try {
            $certPath = \FreePBX::Config()->get('HTTPTLSCERTFILE');
            $keyPath  = \FreePBX::Config()->get('HTTPTLSPRIVATEKEY');
            if (!empty($certPath) && !empty($keyPath)) {
                $attempted[] = ['cert' => $certPath, 'key' => $keyPath, 'source' => 'FreePBX Advanced Settings'];
                if (is_readable($certPath) && is_readable($keyPath)) {
                    $this->setConfigValue('ssl_cert_path', $certPath);
                    $this->setConfigValue('ssl_key_path', $keyPath);
                    return [
                        'status' => true,
                        'message' => 'SSL paths detected from FreePBX Advanced Settings',
                        'cert' => $certPath,
                        'key' => $keyPath,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Config not available
        }

        // Try version-based paths
        $version = $this->getFreePBXVersion();
        if ($version && $version >= 17) {
            $certPath = '/etc/apache2/pki/webserver.crt';
            $keyPath  = '/etc/apache2/pki/webserver.key';
        } else {
            $certPath = '/etc/httpd/pki/webserver.crt';
            $keyPath  = '/etc/httpd/pki/webserver.key';
        }
        $attempted[] = ['cert' => $certPath, 'key' => $keyPath, 'source' => "FreePBX {$version} default"];
        if (is_readable($certPath) && is_readable($keyPath)) {
            $this->setConfigValue('ssl_cert_path', $certPath);
            $this->setConfigValue('ssl_key_path', $keyPath);
            return [
                'status' => true,
                'message' => "SSL paths detected from FreePBX {$version} defaults",
                'cert' => $certPath,
                'key' => $keyPath,
            ];
        }

        // Nothing readable — clear config so we don't point at stale paths
        $this->setConfigValue('ssl_cert_path', '');
        $this->setConfigValue('ssl_key_path', '');

        return [
            'status' => false,
            'message' => 'SSL certificates not readable by the asterisk user. '
                . 'Default paths: ' . $certPath . ', ' . $keyPath . '. '
                . 'Copy them to an asterisk-readable location or set custom paths in Settings.',
            'attempted' => $attempted,
        ];
    }

    /**
     * Trigger a hook via sysadmin/incron (runs as root).
     * Modeled on the Firewall module's runHook().
     *
     * @param string $hookname  Hook script name (must exist in hooks/ dir)
     * @param array|false $params  Optional parameters to pass to the hook
     * @return bool True if the hook was triggered and completed
     */
    public function runHook($hookname, $params = false, $timeout = 10)
    {
        // Verify sysadmin incron is installed
        if (!file_exists('/etc/incron.d/sysadmin')) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent runHook: sysadmin incron not found");
            return false;
        }

        $spoolDir = '/var/spool/asterisk/incron';
        if (!is_dir($spoolDir)) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent runHook: spool dir not found");
            return false;
        }

        $filename = "{$spoolDir}/convergentdashboard.{$hookname}";
        $contents = '';

        if (is_array($params)) {
            $b = base64_encode(gzcompress(json_encode($params)));

            // Check if sysadmin supports content-based params
            $max = @file_get_contents('/etc/sysadmin_contents_max');
            if ($max && strlen($b) <= (int)$max) {
                $filename .= ".CONTENTS";
                $contents = $b;
            } else {
                // Filename-based: replace / with _ for filesystem safety
                $filename .= "." . str_replace('/', '_', $b);
            }
        }

        // Create the trigger file
        file_put_contents($filename, $contents);

        // Wait for the hook to complete (it deletes the trigger file)
        $timeout = max(1, (int)$timeout);
        for ($i = 0; $i < $timeout; $i++) {
            if (!file_exists($filename)) {
                freepbx_log(FPBX_LOG_NOTICE, "Convergent runHook({$hookname}): completed");
                return true;
            }
            sleep(1);
        }

        freepbx_log(FPBX_LOG_ERROR, "Convergent runHook({$hookname}): timed out after {$timeout} seconds");
        return false;
    }

    /**
     * Test the sysadmin/incron hook dispatch path.
     * Triggers a lightweight hook that writes a marker file, then checks the result.
     */
    public function testHook()
    {
        $markerFile = '/tmp/convergent-hook-test.json';

        // Remove stale marker
        if (file_exists($markerFile)) {
            @unlink($markerFile);
        }

        $hookResult = $this->runHook('testhook');

        if ($hookResult && file_exists($markerFile)) {
            $raw = file_get_contents($markerFile);
            $marker = json_decode($raw, true);

            if ($marker) {
                $isRoot = ($marker['uid'] ?? -1) === 0;
                return [
                    'status' => true,
                    'ran_as_root' => $isRoot,
                    'marker' => $marker,
                    'message' => $isRoot
                        ? 'Hook executed successfully as root'
                        : 'Hook executed but NOT as root (uid=' . ($marker['uid'] ?? '?') . ')',
                ];
            }

            return [
                'status' => false,
                'message' => 'Marker file exists but could not be parsed',
                'raw' => $raw,
            ];
        }

        // Diagnose failure
        $diag = [
            'incron_exists' => file_exists('/etc/incron.d/sysadmin'),
            'spool_dir_exists' => is_dir('/var/spool/asterisk/incron'),
            'marker_appeared' => file_exists($markerFile),
        ];

        return [
            'status' => false,
            'message' => $hookResult
                ? 'Hook trigger file was consumed but marker file not written'
                : 'Hook timed out — sysadmin_manager may not dispatch for this module',
            'diagnostics' => $diag,
        ];
    }

    /**
     * Get FreePBX major version
     */
    private function getFreePBXVersion()
    {
        try {
            $sql = "SELECT value FROM admin WHERE variable = 'version' LIMIT 1";
            $stmt = $this->db->query($sql);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                $versionStr = $result['value'];
                $parts = explode('.', $versionStr);
                return (int) $parts[0];
            }
        } catch (\Exception $e) {
            // Fallback: check filesystem
            if (file_exists('/etc/apache2/pki')) {
                return 17;
            }
        }

        return 16; // Default
    }

    /**
     * Service Status and Management
     */
    public function getServiceStatus()
    {
        $wssPort = $this->getConfigValue('client_wss_port') ?: '18443';
        $status = \FreePBX::Pm2()->getStatus("convergent-monitoring");

        if ($status === false) {
            return [
                'status' => true,
                'service' => [
                    'name' => 'convergent-monitoring',
                    'active' => false,
                    'enabled' => false,
                    'uptime' => '',
                    'pid' => '',
                    'memory' => '',
                    'port_listening' => false,
                    'wss_port' => $wssPort
                ]
            ];
        }

        $pmStatus = $status['pm2_env']['status'] ?? 'stopped';
        $isActive = ($pmStatus === 'online');
        $pid = $status['pid'] ?? '';
        $uptime = $status['pm2_env']['created_at_human_diff'] ?? '';
        $memory = $status['monit']['human_memory'] ?? '';

        // Check WebSocket port
        $portOutput = [];
        exec("ss -tlnp | grep :" . escapeshellarg($wssPort) . " 2>&1", $portOutput);
        $portListening = !empty($portOutput);

        return [
            'status' => true,
            'service' => [
                'name' => 'convergent-monitoring',
                'active' => $isActive,
                'enabled' => $isActive,
                'uptime' => $uptime,
                'pid' => $pid,
                'memory' => $memory,
                'port_listening' => $portListening,
                'wss_port' => $wssPort
            ]
        ];
    }

    public function serviceAction($action)
    {
        $pm2 = \FreePBX::Pm2();
        $name = 'convergent-monitoring';

        try {
            switch ($action) {
                case 'start':
                    $scriptPath = '/var/lib/asterisk/convergent-start.sh';
                    if (!file_exists($scriptPath)) {
                        return ['status' => false, 'message' => 'Start script not found. Click "Setup Service" first.'];
                    }
                    // If the process is already registered in PM2 (stopped/errored),
                    // use restart() — it operates by name and never creates a second entry.
                    // Only call start() when the process is absent entirely.
                    $existing = $pm2->getStatus($name);
                    if (!empty($existing)) {
                        $pm2->restart($name);
                    } else {
                        $pm2->start($name, $scriptPath);
                    }
                    break;
                case 'stop':
                    $pm2->stop($name);
                    break;
                case 'restart':
                    $pm2->restart($name);
                    break;
                default:
                    return ['status' => false, 'message' => 'Invalid action'];
            }
        } catch (\Exception $e) {
            return ['status' => false, 'message' => "Service {$action} failed: " . $e->getMessage()];
        }

        $this->logStatus($action, 'success');
        return ['status' => true, 'message' => "Service {$action} successful"];
    }

    /**
     * Version Management
     */
    public function getInstalledVersion()
    {
        $installPath = self::INSTALL_PATH;
        $packageJson = $installPath . '/package.json';

        if (!file_exists($packageJson)) {
            return ['status' => false, 'message' => 'package.json not found', 'version' => null];
        }

        $content = file_get_contents($packageJson);
        $data = json_decode($content, true);

        if (isset($data['version'])) {
            return ['status' => true, 'version' => $data['version'], 'name' => $data['name'] ?? 'unknown'];
        }

        return ['status' => false, 'message' => 'Version not found in package.json', 'version' => null];
    }

    public function checkForUpdates()
    {
        $dashboardUrl = $this->getConfigValue('dashboard_url');
        $apiKey = $this->getConfigValue('convergent_api_key');

        if (empty($dashboardUrl) || empty($apiKey)) {
            return [
                'status' => false,
                'message' => 'Dashboard URL and API key must be configured in Settings'
            ];
        }

        $currentVersion = $this->getInstalledVersion();
        $currentVer = $currentVersion['version'] ?? null;

        // Fetch latest version from dashboard
        $ch = curl_init(rtrim($dashboardUrl, '/') . '/api/releases/latest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["x-api-key: {$apiKey}"],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['status' => false, 'message' => "Connection failed: {$curlError}"];
        }
        if ($httpCode !== 200) {
            return ['status' => false, 'message' => "Dashboard returned HTTP {$httpCode}"];
        }

        $data = json_decode($response, true);
        if (!$data || empty($data['version'])) {
            return [
                'status' => true,
                'current_version' => $currentVer ?? 'unknown',
                'update_available' => false,
                'message' => $data['message'] ?? 'No releases available on the dashboard'
            ];
        }

        $latestVer = $data['version'];
        $updateAvailable = $currentVer ? ($this->compareVersions($latestVer, $currentVer) > 0) : true;

        return [
            'status' => true,
            'current_version' => $currentVer ?? 'not installed',
            'latest_version' => $latestVer,
            'update_available' => $updateAvailable,
            'sha256' => $data['sha256'] ?? '',
            'release_notes' => $data['release_notes'] ?? '',
            'message' => $updateAvailable
                ? "Update available: v{$latestVer}"
                : "You are running the latest version (v{$currentVer})"
        ];
    }

    /**
     * Download a release tarball from the dashboard server
     */
    public function downloadUpdate($version)
    {
        if (empty($version) || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return ['status' => false, 'message' => 'Invalid version format'];
        }

        $dashboardUrl = $this->getConfigValue('dashboard_url');
        $apiKey = $this->getConfigValue('convergent_api_key');

        if (empty($dashboardUrl) || empty($apiKey)) {
            return ['status' => false, 'message' => 'Dashboard URL and API key must be configured'];
        }

        $url = rtrim($dashboardUrl, '/') . '/api/releases/download?version=' . urlencode($version);
        $destFile = "/tmp/convergent-server-{$version}.tar.gz";

        freepbx_log(FPBX_LOG_NOTICE, "Convergent downloadUpdate: downloading v{$version} from {$url}");

        $fp = fopen($destFile, 'w');
        if (!$fp) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent downloadUpdate: failed to open {$destFile} for writing");
            return ['status' => false, 'message' => 'Failed to open destination file for writing'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ["x-api-key: {$apiKey}"],
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent downloadUpdate: failed (HTTP {$httpCode}): {$curlError}");
            @unlink($destFile);
            return ['status' => false, 'message' => "Download failed (HTTP {$httpCode}): {$curlError}"];
        }

        $fileSize = filesize($destFile);
        if ($fileSize < 1024) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent downloadUpdate: file too small ({$fileSize} bytes)");
            @unlink($destFile);
            return ['status' => false, 'message' => 'Downloaded file is too small — likely an error response'];
        }

        freepbx_log(FPBX_LOG_NOTICE, "Convergent downloadUpdate: saved v{$version} to {$destFile} ({$fileSize} bytes)");

        return [
            'status' => true,
            'message' => "Downloaded v{$version} ({$fileSize} bytes)",
            'file' => $destFile,
            'size' => $fileSize
        ];
    }

    /**
     * Apply a downloaded update directly (runs as asterisk user).
     * Extracts tarball, runs npm ci, and restarts the PM2 service.
     * On failure, restores from backup.
     */
    public function applyUpdate($version, $sha256 = '')
    {
        if (empty($version) || !preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return ['status' => false, 'message' => 'Invalid version format'];
        }

        $tarball = "/tmp/convergent-server-{$version}.tar.gz";
        if (!file_exists($tarball)) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent applyUpdate: tarball not found at {$tarball}");
            return ['status' => false, 'message' => "Tarball not found at {$tarball}. Download it first."];
        }

        freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: starting update to v{$version}");

        // Verify SHA256 if provided
        if (!empty($sha256)) {
            $actualHash = hash_file('sha256', $tarball);
            freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: SHA256 check — expected={$sha256}, actual={$actualHash}");
            if ($actualHash !== $sha256) {
                return [
                    'status' => false,
                    'message' => "SHA256 mismatch. Expected: {$sha256}, Got: {$actualHash}"
                ];
            }
        }

        // Find npm path from prerequisites
        $npmPath = '/usr/bin/npm';
        $prereqs = $this->checkPrerequisites();
        $nvmInfo = $prereqs['prerequisites']['node']['nvm_info'] ?? null;
        if ($nvmInfo && !empty($nvmInfo['npm_path'])) {
            $npmPath = $nvmInfo['npm_path'];
        }

        // Extend PHP execution time for npm ci
        set_time_limit(180);

        $installPath = self::INSTALL_PATH;
        $backupPath = "/tmp/convergent-server-backup";
        $steps = [];

        // Step 1: Stop PM2 process
        freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: stopping PM2 process");
        try {
            \FreePBX::Pm2()->stop("convergent-monitoring");
            $steps[] = 'Stopped service';
        } catch (\Exception $e) {
            $steps[] = 'Service was not running';
        }

        // Step 2: Backup current install
        freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: backing up {$installPath} to {$backupPath}");
        if (is_dir($backupPath)) {
            $this->execLog("rm -rf " . escapeshellarg($backupPath));
        }
        $cpResult = $this->execLog("cp -a " . escapeshellarg($installPath) . " " . escapeshellarg($backupPath));
        if ($cpResult['code'] !== 0) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent applyUpdate: backup failed: " . $cpResult['output']);
            $this->restartService();
            return ['status' => false, 'message' => 'Backup failed: ' . $cpResult['output'], 'steps' => $steps];
        }
        $steps[] = 'Backed up current install';

        // Step 3: Preserve node_modules, recordings, .env
        $preserveDirs = ['node_modules', 'recordings', '.env'];
        $tempPreserve = "/tmp/convergent-update-preserve-" . getmypid();
        @mkdir($tempPreserve, 0755, true);

        foreach ($preserveDirs as $item) {
            $itemPath = "{$installPath}/{$item}";
            if (file_exists($itemPath)) {
                $this->execLog("mv " . escapeshellarg($itemPath) . " " . escapeshellarg("{$tempPreserve}/{$item}"));
                freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: preserved {$item}");
            }
        }

        // Step 4: Clear and extract
        $this->execLog("rm -rf " . escapeshellarg($installPath) . "/*");

        $extractResult = $this->execLog(
            "tar -xzf " . escapeshellarg($tarball)
            . " -C " . escapeshellarg(dirname($installPath))
            . " --strip-components=0"
        );

        if ($extractResult['code'] !== 0) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent applyUpdate: extraction failed: " . $extractResult['output']);
            // Restore preserved items then backup
            foreach ($preserveDirs as $item) {
                if (file_exists("{$tempPreserve}/{$item}")) {
                    $this->execLog("mv " . escapeshellarg("{$tempPreserve}/{$item}") . " " . escapeshellarg("{$installPath}/{$item}"));
                }
            }
            $this->execLog("rm -rf " . escapeshellarg($tempPreserve));
            $this->restoreBackup($backupPath, $installPath);
            return ['status' => false, 'message' => 'Extraction failed: ' . $extractResult['output'], 'steps' => $steps];
        }
        $steps[] = 'Extracted new version';

        // Log what was extracted
        $extractedFiles = @scandir($installPath);
        freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: extracted files: " . json_encode($extractedFiles ?: []));

        // Step 5: Restore preserved items
        foreach ($preserveDirs as $item) {
            $preservedItem = "{$tempPreserve}/{$item}";
            if (file_exists($preservedItem)) {
                $targetItem = "{$installPath}/{$item}";
                if (file_exists($targetItem)) {
                    $this->execLog("rm -rf " . escapeshellarg($targetItem));
                }
                $this->execLog("mv " . escapeshellarg($preservedItem) . " " . escapeshellarg($targetItem));
            }
        }
        $this->execLog("rm -rf " . escapeshellarg($tempPreserve));
        $steps[] = 'Restored node_modules and data';

        // Step 6: Run npm ci (prepend nvm bin dir to PATH so npm finds the right node)
        $npmBinDir = dirname($npmPath);
        freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: running npm ci with {$npmPath} (PATH prefix: {$npmBinDir})");
        $npmResult = $this->execLog(
            "export PATH=" . escapeshellarg($npmBinDir) . ":\$PATH && "
            . "cd " . escapeshellarg($installPath) . " && " . escapeshellarg($npmPath) . " install --omit=dev 2>&1"
        );

        if ($npmResult['code'] !== 0) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent applyUpdate: npm ci failed (exit {$npmResult['code']}): " . $npmResult['output']);
            $this->restoreBackup($backupPath, $installPath);
            return [
                'status' => false,
                'message' => 'npm ci failed. Restored from backup.',
                'npm_output' => $npmResult['output'],
                'steps' => $steps
            ];
        }
        $steps[] = 'Installed dependencies';

        // Step 7: Verify version
        $newVersion = $this->getInstalledVersion();
        $installedVer = $newVersion['version'] ?? 'unknown';
        freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: installed version is now v{$installedVer}");
        $steps[] = "Version: v{$installedVer}";

        // Step 8: Restart service
        $this->restartService();
        $steps[] = 'Restarted service';

        // Clean up tarball
        @unlink($tarball);

        freepbx_log(FPBX_LOG_NOTICE, "Convergent applyUpdate: update to v{$version} complete");

        return [
            'status' => true,
            'message' => "Successfully updated to v{$installedVer}",
            'version' => $installedVer,
            'steps' => $steps
        ];
    }

    /**
     * Execute a shell command and return output + exit code with logging
     */
    private function execLog($cmd)
    {
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        $outputStr = implode("\n", $output);
        if ($code !== 0) {
            freepbx_log(FPBX_LOG_NOTICE, "Convergent exec FAILED ({$code}): {$cmd} => {$outputStr}");
        }
        return ['code' => $code, 'output' => $outputStr];
    }

    /**
     * Restore from backup after a failed update
     */
    private function restoreBackup($backupPath, $installPath)
    {
        freepbx_log(FPBX_LOG_NOTICE, "Convergent restoreBackup: restoring {$backupPath} -> {$installPath}");
        if (is_dir($backupPath)) {
            $this->execLog("rm -rf " . escapeshellarg($installPath));
            $this->execLog("cp -a " . escapeshellarg($backupPath) . " " . escapeshellarg($installPath));
        }
        $this->restartService();
    }

    /**
     * Restart the convergent-monitoring PM2 service
     */
    private function restartService()
    {
        $pm2  = \FreePBX::Pm2();
        $name = 'convergent-monitoring';
        $script = '/var/lib/asterisk/convergent-start.sh';
        try {
            $existing = $pm2->getStatus($name);
            if (!empty($existing)) {
                $pm2->restart($name);
            } else {
                $pm2->start($name, $script);
            }
        } catch (\Exception $e) {
            freepbx_log(FPBX_LOG_ERROR, "Convergent restartService: failed: " . $e->getMessage());
        }
    }

    /**
     * System Credentials - Read from FreePBX/Asterisk config files
     */
    public function getSystemCredentials()
    {
        $credentials = [];

        // Get ARI credentials from ari.conf
        $ariCreds = $this->parseAriConfig();
        $credentials['ari'] = $ariCreds;

        // Get AMI credentials from manager.conf
        $amiCreds = $this->parseAmiConfig();
        $credentials['ami'] = $amiCreds;

        // Get GraphQL credentials from FreePBX (if available)
        $gqlCreds = $this->getGraphQLCredentials();
        $credentials['graphql'] = $gqlCreds;

        return ['status' => true, 'credentials' => $credentials];
    }

    private function parseAriConfig()
    {
        $ariConfPath = '/etc/asterisk/ari.conf';
        $creds = [
            'url' => 'http://127.0.0.1:8088',
            'user' => '',
            'password' => '',
            'found' => false
        ];

        if (!file_exists($ariConfPath)) {
            return $creds;
        }

        $content = file_get_contents($ariConfPath);
        $lines = explode("\n", $content);
        $inUserSection = false;
        $currentUser = '';

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || $line[0] === ';' || $line[0] === '#') {
                continue;
            }

            // Check for section header [username]
            if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
                $section = $matches[1];
                if ($section !== 'general') {
                    $inUserSection = true;
                    $currentUser = $section;
                    $creds['user'] = $section;
                } else {
                    $inUserSection = false;
                }
                continue;
            }

            // Parse key=value pairs
            if ($inUserSection && strpos($line, '=') !== false) {
                list($key, $value) = array_map('trim', explode('=', $line, 2));
                if ($key === 'password' || $key === 'password_format') {
                    if ($key === 'password') {
                        $creds['password'] = $value;
                        $creds['found'] = true;
                    }
                }
            }
        }

        // Get HTTP bind address from http.conf if exists
        $httpConfPath = '/etc/asterisk/http.conf';
        if (file_exists($httpConfPath)) {
            $httpContent = file_get_contents($httpConfPath);
            if (preg_match('/bindaddr\s*=\s*(.+)/', $httpContent, $matches)) {
                $bindAddr = trim($matches[1]);
                if (preg_match('/bindport\s*=\s*(\d+)/', $httpContent, $portMatches)) {
                    $port = trim($portMatches[1]);
                    $creds['url'] = "http://{$bindAddr}:{$port}";
                }
            }
        }

        return $creds;
    }

    private function parseAmiConfig()
    {
        $managerConfPath = '/etc/asterisk/manager.conf';
        $creds = [
            'host' => '127.0.0.1',
            'port' => '5038',
            'username' => '',
            'password' => '',
            'found' => false
        ];

        if (!file_exists($managerConfPath)) {
            return $creds;
        }

        $content = file_get_contents($managerConfPath);
        $lines = explode("\n", $content);
        $inUserSection = false;
        $currentUser = '';

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line) || $line[0] === ';' || $line[0] === '#') {
                continue;
            }

            // Check for general section for port
            if (preg_match('/^\[general\]$/', $line)) {
                $inUserSection = false;
                continue;
            }

            // Check for section header [username]
            if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
                $section = $matches[1];
                if ($section !== 'general') {
                    $inUserSection = true;
                    $currentUser = $section;
                    $creds['username'] = $section;
                }
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = array_map('trim', explode('=', $line, 2));

                if (!$inUserSection && $key === 'port') {
                    $creds['port'] = $value;
                }

                if ($inUserSection && $key === 'secret') {
                    $creds['password'] = $value;
                    $creds['found'] = true;
                }
            }
        }

        return $creds;
    }

    private function getGraphQLCredentials()
    {
        // FreePBX GraphQL API credentials
        // These are typically stored in the FreePBX database
        $creds = [
            'url' => '',
            'token_url' => '',
            'client_id' => '',
            'client_secret' => '',
            'found' => false
        ];

        // Try to get from FreePBX's API module if available
        try {
            // Check if API module is installed
            $apiConfPath = '/etc/freepbx_api.conf';
            if (file_exists($apiConfPath)) {
                $content = file_get_contents($apiConfPath);
                // Parse the config file if it exists
            }

            // Alternatively, get from database
            $sql = "SELECT * FROM `rest_applications` WHERE `name` = 'convergent' LIMIT 1";
            $stmt = $this->db->query($sql);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                $hostname = gethostname();
                $protocol = 'https';
                $port = '443';

                $creds['url'] = "{$protocol}://{$hostname}:{$port}/admin/api/api/gql";
                $creds['token_url'] = "{$protocol}://{$hostname}:{$port}/admin/api/api/token";
                $creds['client_id'] = $result['client_id'] ?? '';
                $creds['client_secret'] = $result['client_secret'] ?? '';
                $creds['found'] = true;
            }
        } catch (\Exception $e) {
            // API module not available
        }

        return $creds;
    }


    /**
     * Test connection to the ARI server WebSocket
     */
    public function testConnection()
    {
        $wssPort = $this->getConfigValue('client_wss_port') ?: '18443';

        // Check if SSL certs exist for auto-detected paths
        $sslPaths = $this->getSSLPaths();
        $useSSL = file_exists($sslPaths['cert']) && file_exists($sslPaths['key']);

        $protocol = $useSSL ? 'wss' : 'ws';
        $url = "{$protocol}://127.0.0.1:{$wssPort}";

        // Simple port check
        $connection = @fsockopen('127.0.0.1', (int)$wssPort, $errno, $errstr, 5);

        if ($connection) {
            fclose($connection);
            return [
                'status' => true,
                'message' => "WebSocket server is accepting connections on port {$wssPort}",
                'url' => $url
            ];
        }

        return [
            'status' => false,
            'message' => "Cannot connect to WebSocket server on port {$wssPort}: {$errstr}",
            'url' => $url
        ];
    }

    /**
     * Logging
     */
    private function logStatus($action, $status, $details = '')
    {
        $sql = "INSERT INTO `convergent_status_log` (`status`, `message`, `details`) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $action, $details]);
    }

    public function getStatusLog($limit = 50)
    {
        $limit = (int)$limit;
        $sql = "SELECT * FROM `convergent_status_log` ORDER BY `logged_at` DESC LIMIT {$limit}";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the current module options for display
     */
    public function getOptions()
    {
        return $this->getAllConfig();
    }

    /**
     * Get PM2 logs for the service
     */
    public function getJournalLogs($lines = 100)
    {
        $lines = min(max($lines, 10), 1000);
        $status = \FreePBX::Pm2()->getStatus("convergent-monitoring");

        $logContent = '';

        if ($status) {
            $outLog = $status['pm2_env']['pm_out_log_path'] ?? '';
            $errLog = $status['pm2_env']['pm_err_log_path'] ?? '';

            $logLines = [];
            foreach ([$outLog, $errLog] as $logFile) {
                if (!empty($logFile) && file_exists($logFile)) {
                    $allLines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($allLines) {
                        $logLines = array_merge($logLines, $allLines);
                    }
                }
            }

            $logLines = array_slice($logLines, -$lines);
            $logContent = implode("\n", $logLines);
        }

        // Fallback: try known pm2 log paths
        if (empty($logContent)) {
            $fallbackPaths = [
                '/var/log/asterisk/convergent-monitoring_out.log',
                '/var/log/asterisk/convergent-monitoring_err.log',
            ];
            foreach ($fallbackPaths as $path) {
                if (file_exists($path)) {
                    $allLines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($allLines) {
                        $logContent .= implode("\n", array_slice($allLines, -$lines)) . "\n";
                    }
                }
            }
        }

        return [
            'status' => !empty($logContent),
            'logs' => $logContent ?: 'No logs available. The service may not have been started yet.'
        ];
    }

    /**
     * Module Self-Update
     */

    /**
     * Read the installed module version from module.xml
     */
    public function getModuleVersion()
    {
        $xmlPath = __DIR__ . '/module.xml';
        if (!file_exists($xmlPath)) {
            return ['status' => false, 'version' => null, 'message' => 'module.xml not found'];
        }
        $xml = simplexml_load_file($xmlPath);
        if (!$xml) {
            return ['status' => false, 'version' => null, 'message' => 'Could not parse module.xml'];
        }
        return [
            'status'  => true,
            'version' => (string)$xml->version,
            'name'    => (string)$xml->name,
        ];
    }

    /**
     * Check GitHub releases for a newer module version
     */
    public function checkModuleUpdate()
    {
        $current    = $this->getModuleVersion();
        $currentVer = $current['version'] ?? null;

        $ch = curl_init(self::MODULE_GITHUB_API . '/releases/latest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.github+json',
                'User-Agent: FreePBX-ConvergentDashboard/' . ($currentVer ?? '1.0'),
            ],
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['status' => false, 'message' => "Connection failed: {$curlError}"];
        }
        if ($httpCode === 404) {
            return ['status' => false, 'message' => 'No releases found on GitHub'];
        }
        if ($httpCode !== 200) {
            return ['status' => false, 'message' => "GitHub returned HTTP {$httpCode}"];
        }

        $data = json_decode($response, true);
        if (!$data || empty($data['tag_name'])) {
            return ['status' => false, 'message' => 'Could not parse GitHub release data'];
        }

        $latestVer       = ltrim($data['tag_name'], 'v');
        $updateAvailable = $currentVer ? ($this->compareVersions($latestVer, $currentVer) > 0) : true;

        return [
            'status'          => true,
            'current_version' => $currentVer ?? 'unknown',
            'latest_version'  => $latestVer,
            'tag_name'        => $data['tag_name'],
            'update_available' => $updateAvailable,
            'zipball_url'     => $data['zipball_url'] ?? '',
            'release_notes'   => $data['body'] ?? '',
            'message'         => $updateAvailable
                ? "Update available: v{$latestVer}"
                : "Module is up to date (v{$currentVer})",
        ];
    }

    /**
     * Download a GitHub zipball and install it via the installmodule root hook
     */
    public function downloadInstallModule($tagName, $zipballUrl)
    {
        if (empty($tagName) || empty($zipballUrl)) {
            return ['status' => false, 'message' => 'Tag name and download URL are required'];
        }

        $safeTag  = preg_replace('/[^a-zA-Z0-9._-]/', '', $tagName);
        $destFile = "/tmp/convergentdashboard-{$safeTag}.zip";

        freepbx_log(FPBX_LOG_NOTICE, "Convergent downloadInstallModule: downloading {$tagName}");

        $fp = fopen($destFile, 'w');
        if (!$fp) {
            return ['status' => false, 'message' => 'Failed to open destination file for writing'];
        }

        $currentVer = $this->getModuleVersion()['version'] ?? '1.0';
        $ch = curl_init($zipballUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => [
                'Accept: application/vnd.github+json',
                'User-Agent: FreePBX-ConvergentDashboard/' . $currentVer,
            ],
            CURLOPT_FILE           => $fp,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $success   = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            @unlink($destFile);
            return ['status' => false, 'message' => "Download failed (HTTP {$httpCode}): {$curlError}"];
        }

        $fileSize = filesize($destFile);
        if ($fileSize < 1024) {
            @unlink($destFile);
            return ['status' => false, 'message' => 'Downloaded file is too small — likely an error response'];
        }

        freepbx_log(FPBX_LOG_NOTICE, "Convergent downloadInstallModule: {$fileSize} bytes downloaded, triggering install hook");

        $hookResult = $this->runHook('installmodule', [
            'file'        => $destFile,
            'module_path' => __DIR__,
        ], 60);

        @unlink($destFile);

        if (!$hookResult) {
            return [
                'status'  => false,
                'message' => 'Module downloaded but the installation hook timed out. '
                    . 'Check /tmp/convergent-installmodule.log for details.',
            ];
        }

        return [
            'status'  => true,
            'message' => "Module updated to {$tagName}. Reloading...",
        ];
    }

    /**
     * Minimum required Node.js version
     */
    private const MIN_NODE_VERSION = '16.17.0';

    /**
     * Compare two semver version strings
     * Returns: -1 if $v1 < $v2, 0 if equal, 1 if $v1 > $v2
     */
    private function compareVersions($v1, $v2)
    {
        // Strip leading 'v' if present
        $v1 = ltrim($v1, 'v');
        $v2 = ltrim($v2, 'v');

        $parts1 = array_map('intval', explode('.', $v1));
        $parts2 = array_map('intval', explode('.', $v2));

        // Pad arrays to same length
        while (count($parts1) < 3) $parts1[] = 0;
        while (count($parts2) < 3) $parts2[] = 0;

        for ($i = 0; $i < 3; $i++) {
            if ($parts1[$i] < $parts2[$i]) return -1;
            if ($parts1[$i] > $parts2[$i]) return 1;
        }

        return 0;
    }

    /**
     * Find nvm installation and suitable Node.js version
     * Returns array with nvm info and paths to node/npm/npx binaries
     */
    private function findNvmNode()
    {
        $result = [
            'nvm_found' => false,
            'nvm_dir' => '',
            'available_versions' => [],
            'suitable_version' => '',
            'node_path' => '',
            'npm_path' => '',
            'npx_path' => '',
            'message' => ''
        ];

        // First, try to detect nvm by running commands as the asterisk user
        // This avoids permission issues when apache can't read asterisk's home directory
        $nvmResult = $this->detectNvmAsAsterisk();
        if ($nvmResult['found']) {
            return $nvmResult['result'];
        }

        // Fallback: try direct filesystem access (works if permissions allow)
        // Common nvm installation locations to check
        $nvmLocations = [
            '/root/.nvm',
            '/home/asterisk/.nvm',
            '/var/lib/asterisk/.nvm',
            '/opt/nvm',
            '/usr/local/nvm',
            getenv('NVM_DIR') ?: ''
        ];

        // Also check home directories of common users
        $passwdFile = '/etc/passwd';
        if (file_exists($passwdFile)) {
            $lines = file($passwdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode(':', $line);
                if (count($parts) >= 6) {
                    $homeDir = $parts[5];
                    if (!empty($homeDir) && !in_array("{$homeDir}/.nvm", $nvmLocations)) {
                        $nvmLocations[] = "{$homeDir}/.nvm";
                    }
                }
            }
        }

        $nvmDir = '';
        foreach ($nvmLocations as $loc) {
            if (!empty($loc) && @is_dir($loc) && @is_dir("{$loc}/versions/node")) {
                $nvmDir = $loc;
                break;
            }
        }

        if (empty($nvmDir)) {
            $result['message'] = 'nvm not found. Install nvm and Node.js >= ' . self::MIN_NODE_VERSION;
            return $result;
        }

        $result['nvm_found'] = true;
        $result['nvm_dir'] = $nvmDir;

        // Find available Node versions
        $versionsDir = "{$nvmDir}/versions/node";
        $versions = [];

        if (@is_dir($versionsDir)) {
            $dirs = @scandir($versionsDir);
            if ($dirs) {
                foreach ($dirs as $dir) {
                    if ($dir === '.' || $dir === '..') continue;
                    // Version directories are named like 'v16.17.0'
                    if (preg_match('/^v?(\d+\.\d+\.\d+)$/', $dir, $matches)) {
                        $version = $matches[1];
                        $nodeBin = "{$versionsDir}/{$dir}/bin/node";
                        // Don't use is_executable() - web server user may not have permission
                        // but the asterisk user (who runs the service) does
                        if (@file_exists($nodeBin) || @is_link($nodeBin)) {
                            $versions[$version] = $dir;
                        }
                    }
                }
            }
        }

        if (empty($versions)) {
            $result['message'] = 'nvm found but no Node.js versions installed. Run: nvm install 16';
            return $result;
        }

        // Sort versions descending
        uksort($versions, function($a, $b) {
            return $this->compareVersions($b, $a);
        });

        $result['available_versions'] = $versions;

        // Find the best suitable version (highest that meets minimum)
        foreach ($versions as $version => $dirName) {
            if ($this->compareVersions($version, self::MIN_NODE_VERSION) >= 0) {
                $result['suitable_version'] = $version;
                $basePath = "{$versionsDir}/{$dirName}/bin";
                $result['node_path'] = "{$basePath}/node";
                $result['npm_path'] = "{$basePath}/npm";
                $result['npx_path'] = "{$basePath}/npx";

                break;
            }
        }

        if (empty($result['suitable_version'])) {
            $result['message'] = 'nvm found but no Node.js version >= ' . self::MIN_NODE_VERSION . ' installed. Run: nvm install 16';
        }

        return $result;
    }

    /**
     * Detect nvm installation by sourcing nvm.sh and running commands
     * Since httpd runs as asterisk, we can access nvm directly
     */
    private function detectNvmAsAsterisk()
    {
        $result = [
            'found' => false,
            'result' => [
                'nvm_found' => false,
                'nvm_dir' => '',
                'available_versions' => [],
                'suitable_version' => '',
                'node_path' => '',
                'npm_path' => '',
                'npx_path' => '',
                'message' => ''
            ]
        ];

        // Get the home directory for the current user (asterisk)
        $homeDir = getenv('HOME') ?: posix_getpwuid(posix_getuid())['dir'] ?? '';
        if (empty($homeDir)) {
            return $result;
        }

        // Try to get node path from nvm by sourcing nvm.sh
        // Since PHP/httpd runs as asterisk, we can do this directly
        $script = "source {$homeDir}/.nvm/nvm.sh 2>/dev/null && which node 2>/dev/null";
        $output = [];
        $returnCode = 0;
        exec("bash -c " . escapeshellarg($script) . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0 || empty($output[0])) {
            // nvm not available
            return $result;
        }

        $nodePath = trim($output[0]);
        if (empty($nodePath) || strpos($nodePath, '/') !== 0) {
            return $result;
        }

        // Get node version
        $versionOutput = [];
        exec("bash -c " . escapeshellarg("source {$homeDir}/.nvm/nvm.sh 2>/dev/null && node --version 2>/dev/null") . " 2>&1", $versionOutput, $versionCode);

        $nodeVersion = '';
        if ($versionCode === 0 && !empty($versionOutput[0])) {
            $nodeVersion = trim($versionOutput[0]);
            $nodeVersion = ltrim($nodeVersion, 'v');
        }

        if (empty($nodeVersion)) {
            return $result;
        }

        // Check if version meets minimum
        if ($this->compareVersions($nodeVersion, self::MIN_NODE_VERSION) < 0) {
            $result['result']['nvm_found'] = true;
            $result['result']['message'] = "nvm Node.js (v{$nodeVersion}) is below minimum required (" . self::MIN_NODE_VERSION . "). Run: nvm install 16";
            return $result;
        }

        $nvmDir = "{$homeDir}/.nvm";

        // Build the result
        $basePath = dirname($nodePath);
        $result['found'] = true;
        $result['result'] = [
            'nvm_found' => true,
            'nvm_dir' => $nvmDir,
            'available_versions' => [$nodeVersion => "v{$nodeVersion}"],
            'suitable_version' => $nodeVersion,
            'node_path' => $nodePath,
            'npm_path' => "{$basePath}/npm",
            'npx_path' => "{$basePath}/npx",
            'message' => ''
        ];

        return $result;
    }

    /**
     * Check if FreePBX PM2 module is available
     */
    private function isPm2Available()
    {
        try {
            $pm2 = \FreePBX::Pm2();
            return is_object($pm2);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check prerequisites for the service
     */
    public function checkPrerequisites()
    {
        $installPath = self::INSTALL_PATH;

        $prerequisites = [
            'server_code' => [
                'name' => 'Server Code',
                'path' => $installPath,
                'installed' => is_dir($installPath) && file_exists("{$installPath}/package.json"),
                'message' => ''
            ],
            'compiled_js' => [
                'name' => 'Compiled JavaScript',
                'path' => "{$installPath}/dist/bin/convergent-monitoring.js",
                'installed' => file_exists("{$installPath}/dist/bin/convergent-monitoring.js"),
                'message' => ''
            ],
            'node_modules' => [
                'name' => 'Node Modules',
                'path' => "{$installPath}/node_modules",
                'installed' => is_dir("{$installPath}/node_modules") && count(@scandir("{$installPath}/node_modules") ?: []) > 2,
                'message' => ''
            ],
            'node' => [
                'name' => 'Node.js',
                'installed' => false,
                'version' => '',
                'version_ok' => false,
                'min_version' => self::MIN_NODE_VERSION,
                'using_nvm' => false,
                'nvm_info' => null,
                'node_path' => '',
                'message' => ''
            ],
            'pm2' => [
                'name' => 'PM2 Module',
                'installed' => $this->isPm2Available(),
                'message' => ''
            ],
            'start_script' => [
                'name' => 'Start Script',
                'path' => '/var/lib/asterisk/convergent-start.sh',
                'installed' => file_exists('/var/lib/asterisk/convergent-start.sh'),
                'message' => ''
            ]
        ];

        // Check nvm first (preferred - avoids PATH issues with global packages)
        $nvmInfo = $this->findNvmNode();
        $prerequisites['node']['nvm_info'] = $nvmInfo;

        if ($nvmInfo['suitable_version']) {
            // Found suitable nvm-managed Node
            $prerequisites['node']['installed'] = true;
            $prerequisites['node']['version'] = 'v' . $nvmInfo['suitable_version'];
            $prerequisites['node']['version_ok'] = true;
            $prerequisites['node']['using_nvm'] = true;
            $prerequisites['node']['node_path'] = $nvmInfo['node_path'];
            $prerequisites['node']['message'] = "Using nvm-managed Node: v{$nvmInfo['suitable_version']}";
        } else {
            // nvm not available or no suitable version - fall back to system Node
            $nodeOutput = [];
            exec('node --version 2>&1', $nodeOutput, $nodeCode);
            $systemNodeVersion = '';
            $systemNodeOk = false;

            if ($nodeCode === 0 && !empty($nodeOutput[0])) {
                $systemNodeVersion = trim($nodeOutput[0]);
                $systemNodeOk = $this->compareVersions($systemNodeVersion, self::MIN_NODE_VERSION) >= 0;
            }

            if ($systemNodeOk) {
                // System Node is sufficient
                $prerequisites['node']['installed'] = true;
                $prerequisites['node']['version'] = $systemNodeVersion;
                $prerequisites['node']['version_ok'] = true;
                $prerequisites['node']['node_path'] = trim(shell_exec('which node 2>/dev/null') ?: '/usr/bin/node');
            } else {
                // No suitable Node found anywhere
                $prerequisites['node']['installed'] = false;
                $prerequisites['node']['version'] = $systemNodeVersion ?: '';
                $prerequisites['node']['version_ok'] = false;

                if ($systemNodeVersion) {
                    $prerequisites['node']['message'] = "System Node ({$systemNodeVersion}) is below minimum required (" . self::MIN_NODE_VERSION . "). " . ($nvmInfo['message'] ?: 'Install nvm and run: nvm install 16');
                } else {
                    $prerequisites['node']['message'] = $nvmInfo['message'] ?: 'Node.js not found. Install Node.js >= ' . self::MIN_NODE_VERSION . ' or use nvm.';
                }
            }
        }

        // Set messages for other prerequisites
        if (!$prerequisites['server_code']['installed']) {
            $prerequisites['server_code']['message'] = "Server code not found at {$installPath}";
        }
        if (!$prerequisites['compiled_js']['installed']) {
            $prerequisites['compiled_js']['message'] = "Compiled JavaScript not found. Run: cd {$installPath} && npm run build";
        }
        if (!$prerequisites['node_modules']['installed']) {
            $prerequisites['node_modules']['message'] = "Dependencies not installed. Run: cd {$installPath} && npm install";
        }
        if (!$prerequisites['pm2']['installed']) {
            $prerequisites['pm2']['message'] = 'FreePBX PM2 module not found. Install it via Module Admin.';
        }
        if (!$prerequisites['start_script']['installed']) {
            $prerequisites['start_script']['message'] = 'Click "Setup Service" to create the start script.';
        }

        // Calculate overall readiness
        $allReady = $prerequisites['server_code']['installed'] &&
                    $prerequisites['compiled_js']['installed'] &&
                    $prerequisites['node_modules']['installed'] &&
                    $prerequisites['node']['installed'] &&
                    $prerequisites['node']['version_ok'] &&
                    $prerequisites['pm2']['installed'] &&
                    $prerequisites['start_script']['installed'];

        $canSetup = $prerequisites['server_code']['installed'] &&
                    $prerequisites['compiled_js']['installed'] &&
                    $prerequisites['node_modules']['installed'] &&
                    $prerequisites['node']['installed'] &&
                    $prerequisites['node']['version_ok'] &&
                    $prerequisites['pm2']['installed'];

        return [
            'status' => true,
            'prerequisites' => $prerequisites,
            'all_ready' => $allReady,
            'can_setup' => $canSetup,
            'install_path' => $installPath
        ];
    }

    /**
     * Setup the PM2 service via wrapper script
     */
    public function setupService()
    {
        $installPath = self::INSTALL_PATH;
        $scriptPath = '/var/lib/asterisk/convergent-start.sh';

        $prereqs = $this->checkPrerequisites();

        // Log each prerequisite status for debugging
        $prereqStatus = [];
        foreach ($prereqs['prerequisites'] as $key => $p) {
            $prereqStatus[$key] = $p['installed'] ?? false;
        }
        $prereqStatus['node_version_ok'] = $prereqs['prerequisites']['node']['version_ok'] ?? false;
        freepbx_log(FPBX_LOG_NOTICE, "Convergent setupService: can_setup=" . ($prereqs['can_setup'] ? 'true' : 'false')
            . ", prereqs=" . json_encode($prereqStatus));

        if (!$prereqs['can_setup']) {
            // Include the detailed status in the error response
            $failedItems = [];
            foreach (['server_code', 'compiled_js', 'node_modules', 'node', 'pm2'] as $key) {
                $installed = $prereqs['prerequisites'][$key]['installed'] ?? false;
                if (!$installed) {
                    $failedItems[] = $prereqs['prerequisites'][$key]['name'] ?? $key;
                }
            }
            if (!($prereqs['prerequisites']['node']['version_ok'] ?? false)) {
                $failedItems[] = 'Node.js version';
            }
            $failedStr = !empty($failedItems) ? ' Failed: ' . implode(', ', $failedItems) . '.' : '';

            return [
                'status' => false,
                'message' => 'Prerequisites not met.' . $failedStr,
                'prerequisites' => $prereqs['prerequisites']
            ];
        }

        $nodePath = $prereqs['prerequisites']['node']['node_path'];

        // Generate wrapper script
        $scriptContent = "#!/bin/bash\nexec " . escapeshellarg($nodePath) . " "
            . escapeshellarg("{$installPath}/dist/bin/convergent-monitoring.js") . "\n";

        file_put_contents($scriptPath, $scriptContent);
        chmod($scriptPath, 0755);

        // Start via PM2
        try {
            $pm2     = \FreePBX::Pm2();
            $existing = $pm2->getStatus("convergent-monitoring");
            if (!empty($existing)) {
                $pm2->restart("convergent-monitoring");
            } else {
                $pm2->start("convergent-monitoring", $scriptPath);
            }
        } catch (\Exception $e) {
            return ['status' => false, 'message' => 'Failed to start: ' . $e->getMessage()];
        }

        $this->logStatus('setup_service', 'success', "Started with node={$nodePath}");

        return [
            'status' => true,
            'message' => 'Service started successfully via PM2',
            'details' => ['Start script created', 'Service started via PM2']
        ];
    }

    // =========================================================================
    // Dialplan Generation & Destination Registration
    // =========================================================================

    /**
     * Called by FreePBX during fwconsole reload to inject Asterisk dialplan.
     *
     * FreePBX rebuilds extensions_additional.conf from scratch on each reload
     * by having every module append its dialplan here. Writing to a standalone
     * file without an #include would mean Asterisk never loads it, so we append
     * directly to extensions_additional.conf — which is the standard FreePBX
     * module pattern.
     */
    public function generate()
    {
        $conf  = "\n; *** Convergent Dashboard - auto-generated, do not edit ***\n\n";
        $conf .= "[convergent-fax-receive]\n";
        $conf .= "exten => s,1,NoOp(=== INBOUND FAX TEST ===)\n";
        $conf .= "same => n,Set(FAXOPT(ecm)=yes)\n";
        $conf .= "same => n,Set(FAXOPT(minrate)=4800)\n";
        $conf .= "same => n,Set(FAXOPT(maxrate)=14400)\n";
        $conf .= "same => n,Set(FAXFILE=\${ASTSPOOLDIR}/fax/\${UNIQUEID}.tif)\n";
        $conf .= "same => n,StopPlaytones()\n";
        $conf .= "same => n,ReceiveFAX(\${FAXFILE},zf)\n";
        $conf .= "same => n,NoOp(FAX Result: \${FAXSTATUS} - \${FAXSTATUSSTRING})\n";
        $conf .= "same => n,NoOp(Saved to: \${FAXFILE})\n";
        $conf .= "same => n,Set(FAX_RESULT_STATUS=\${FAXSTATUS})\n";
        $conf .= "same => n,Set(FAX_RESULT_ERROR=\${FAXERROR})\n";
        $conf .= "same => n,Set(FAX_RESULT_STATUSSTRING=\${FAXSTATUSSTRING})\n";
        $conf .= "same => n,Set(FAX_RESULT_MODE=\${FAXMODE})\n";
        $conf .= "same => n,Hangup()\n";
        $conf .= "\n";
        $conf .= "[convergent-fax-send]\n";
        $conf .= "exten => s,1,NoOp(=== CONVERGENT OUTBOUND FAX (via s extension) ===)\n";
        $conf .= "same => n,NoOp(Fax ID: \${FAXID})\n";
        $conf .= "same => n,NoOp(Destination: \${DESTINATION})\n";
        $conf .= "same => n,NoOp(File: \${FAXFILE})\n";
        $conf .= "same => n,NoOp(Trunk: \${FAXTRUNK})\n";
        $conf .= "same => n,Set(FAXOPT(ecm)=yes)\n";
        $conf .= "same => n,Set(FAXOPT(headerinfo)=\${FAXHEADER})\n";
        $conf .= "same => n,Set(FAXOPT(localstationid)=\${CALLERID(num)})\n";
        $conf .= "same => n,Set(FAXOPT(minrate)=4800)\n";
        $conf .= "same => n,Set(FAXOPT(maxrate)=14400)\n";
        $conf .= "same => n,Wait(1)\n";
        $conf .= "same => n,SendFAX(\${FAXFILE},zf)\n";
        $conf .= "same => n,NoOp(FAX Result: \${FAXSTATUS} - \${FAXSTATUSSTRING})\n";
        $conf .= "same => n,NoOp(Pages: \${FAXPAGES}, Rate: \${FAXBITRATE}, Mode: \${FAXMODE})\n";
        $conf .= "same => n,Set(FAX_RESULT_STATUS=\${FAXSTATUS})\n";
        $conf .= "same => n,Set(FAX_RESULT_ERROR=\${FAXERROR})\n";
        $conf .= "same => n,Set(FAX_RESULT_STATUSSTRING=\${FAXSTATUSSTRING})\n";
        $conf .= "same => n,Set(FAX_RESULT_MODE=\${FAXMODE})\n";
        $conf .= "same => n,Hangup()\n";
        $conf .= "\n; *** End Convergent Dashboard ***\n";

        file_put_contents('/etc/asterisk/extensions_additional.conf', $conf, FILE_APPEND);
    }

    /**
     * Returns the list of destinations this module exposes for routing.
     * FreePBX uses this to populate destination dropdowns (inbound routes,
     * IVR, ring groups, etc.).
     *
     * @return array
     */
    public function getDestinations()
    {
        return [
            [
                'destination' => 'convergent-fax-receive,s,1',
                'description' => 'Convergent Fax: Receive Fax',
                'category'    => _('Convergent'),
            ],
        ];
    }

    /**
     * Returns the dialplan routing target for a destination registered above.
     * FreePBX calls this when building the goto logic for a selected destination.
     *
     * @param  string $destination  The destination string from getDestinations()
     * @return array|null
     */
    public function getDestination($destination)
    {
        if ($destination === 'convergent-fax-receive,s,1') {
            return [
                'context' => 'convergent-fax-receive',
                'exten'   => 's',
                'priority' => 1,
            ];
        }
        return null;
    }

    /**
     * Ensure that nvm node binaries remain executable.
     * FreePBX calls this during fwconsole chown - returning the nvm directory
     * prevents it from stripping execute permissions off node/npm/npx binaries.
     */
    public function chownFreepbx() {
        $files = array();

        $nvmInfo = $this->findNvmNode();
        $nvmDir = $nvmInfo['nvm_dir'] ?? '';

        if (!empty($nvmDir) && is_dir($nvmDir)) {
            $files[] = array(
                'type'  => 'execdir',
                'path'  => $nvmDir,
                'perms' => 0755,
            );
        }

        return $files;
    }

}
