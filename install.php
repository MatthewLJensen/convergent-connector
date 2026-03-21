<?php
/**
 * Convergent Dashboard Server - FreePBX Module
 * Installation Script
 *
 * Creates database tables and default configuration
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db;

// Create configuration table
$sql = "CREATE TABLE IF NOT EXISTS `convergent_config` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT,
    `description` VARCHAR(255),
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$result = $db->query($sql);
if (DB::IsError($result)) {
    die_freepbx($result->getMessage());
}

// Create service status log table
$sql = "CREATE TABLE IF NOT EXISTS `convergent_status_log` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `status` VARCHAR(50) NOT NULL,
    `message` TEXT,
    `details` TEXT,
    `logged_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `status` (`status`),
    KEY `logged_at` (`logged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$result = $db->query($sql);
if (DB::IsError($result)) {
    die_freepbx($result->getMessage());
}

// Helper function to parse AMI config from manager.conf
function parseAmiConfig() {
    $managerConfPath = '/etc/asterisk/manager.conf';
    $creds = [
        'host' => '127.0.0.1',
        'port' => '5038',
        'username' => '',
        'password' => '',
    ];

    if (!file_exists($managerConfPath)) {
        return $creds;
    }

    $content = file_get_contents($managerConfPath);
    $lines = explode("\n", $content);
    $inUserSection = false;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === ';' || $line[0] === '#') {
            continue;
        }

        if (preg_match('/^\[general\]$/i', $line)) {
            $inUserSection = false;
            continue;
        }

        if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
            $section = $matches[1];
            if (strtolower($section) !== 'general') {
                $inUserSection = true;
                $creds['username'] = $section;
            }
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = array_map('trim', explode('=', $line, 2));
            $key = strtolower($key);

            if (!$inUserSection && $key === 'port') {
                $creds['port'] = $value;
            }
            if ($inUserSection && $key === 'secret') {
                $creds['password'] = $value;
            }
        }
    }

    return $creds;
}

// Helper function to parse ARI config from ari.conf and http.conf
function parseAriConfig() {
    $ariConfPath = '/etc/asterisk/ari.conf';
    $httpConfPath = '/etc/asterisk/http.conf';
    $creds = [
        'url' => 'http://127.0.0.1:8088',
        'username' => '',
        'password' => '',
    ];

    // Parse ari.conf for username and password
    if (file_exists($ariConfPath)) {
        $content = file_get_contents($ariConfPath);
        $lines = explode("\n", $content);
        $inUserSection = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === ';' || $line[0] === '#') {
                continue;
            }

            if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
                $section = $matches[1];
                if (strtolower($section) !== 'general') {
                    $inUserSection = true;
                    $creds['username'] = $section;
                } else {
                    $inUserSection = false;
                }
                continue;
            }

            if ($inUserSection && strpos($line, '=') !== false) {
                list($key, $value) = array_map('trim', explode('=', $line, 2));
                if (strtolower($key) === 'password') {
                    $creds['password'] = $value;
                }
            }
        }
    }

    // Parse http.conf for bind address and port
    if (file_exists($httpConfPath)) {
        $content = file_get_contents($httpConfPath);
        $bindAddr = '127.0.0.1';
        $port = '8088';

        if (preg_match('/bindaddr\s*=\s*(.+)/', $content, $matches)) {
            $bindAddr = trim($matches[1]);
        }
        if (preg_match('/bindport\s*=\s*(\d+)/', $content, $matches)) {
            $port = trim($matches[1]);
        }

        $creds['url'] = "http://{$bindAddr}:{$port}";
    }

    return $creds;
}

// Helper function to get GraphQL credentials from FreePBX database
function getGraphQLCredentials($db) {
    $creds = [
        'url' => '',
        'token_url' => '',
        'client_id' => '',
        'client_secret' => '',
    ];

    try {
        // Check if the rest_applications table exists first
        $checkTable = $db->query("SHOW TABLES LIKE 'rest_applications'");
        if (DB::IsError($checkTable) || $checkTable->numRows() == 0) {
            // Table doesn't exist, API module not installed
            return $creds;
        }

        $sql = "SELECT `client_id`, `client_secret` FROM `rest_applications` WHERE `name` = 'convergent' LIMIT 1";
        $result = $db->query($sql);

        if (DB::IsError($result)) {
            // Query failed
            return $creds;
        }

        $row = $result->fetchRow(DB_FETCHMODE_ASSOC);

        if ($row) {
            $hostname = gethostname();
            $protocol = 'https';
            $port = '443';

            $creds['url'] = "{$protocol}://{$hostname}:{$port}/admin/api/api/gql";
            $creds['token_url'] = "{$protocol}://{$hostname}:{$port}/admin/api/api/token";
            $creds['client_id'] = $row['client_id'] ?? '';
            $creds['client_secret'] = $row['client_secret'] ?? '';
        }
    } catch (Exception $e) {
        // API module may not be installed
    }

    return $creds;
}

// Helper function to detect best SSL cert paths
function detectSSLInstallPaths() {
    $paths = ['cert' => '', 'key' => ''];

    // Try FreePBX Advanced Settings first
    try {
        if (class_exists('\FreePBX') && method_exists('\FreePBX', 'Config')) {
            $certPath = \FreePBX::Config()->get('HTTPTLSCERTFILE');
            $keyPath  = \FreePBX::Config()->get('HTTPTLSPRIVATEKEY');
            if (!empty($certPath) && !empty($keyPath)) {
                return ['cert' => $certPath, 'key' => $keyPath];
            }
        }
    } catch (Exception $e) {
        // Config not available during install
    }

    // Fallback: version-based paths
    // Check admin table for version
    global $db;
    $version = 16;
    try {
        $result = $db->query("SELECT value FROM admin WHERE variable = 'version' LIMIT 1");
        if (!DB::IsError($result)) {
            $row = $result->fetchRow(DB_FETCHMODE_ASSOC);
            if ($row) {
                $parts = explode('.', $row['value']);
                $version = (int)$parts[0];
            }
        }
    } catch (Exception $e) {
        if (file_exists('/etc/apache2/pki')) {
            $version = 17;
        }
    }

    if ($version >= 17) {
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

// Auto-detect credentials on install
$amiCreds = parseAmiConfig();
$ariCreds = parseAriConfig();
$gqlCreds = getGraphQLCredentials($db);
$sslPaths = detectSSLInstallPaths();

// Insert default configuration values (16 keys total)
$defaults = [
    // Core Settings (6 keys)
    ['dashboard_url', '', 'URL of the Convergent Dashboard server (e.g. https://dashboard.example.com)'],
    ['convergent_api_key', '', 'API key for Convergent Dashboard authentication'],
    ['client_wss_port', '18443', 'WebSocket port for client connections. Must be allowed through firewall.'],
    ['audio_port_start', '9000', 'Start of internal port range for Asterisk audio streaming'],
    ['audio_port_end', '10000', 'End of internal port range (~1000 concurrent transcriptions)'],
    ['speech_lang', 'en-US', 'Speech recognition language (BCP-47)'],

    // AMI Credentials (4 keys) - Auto-detected on install
    ['ami_host', $amiCreds['host'], 'AMI host'],
    ['ami_port', $amiCreds['port'], 'AMI port'],
    ['ami_username', $amiCreds['username'], 'AMI username'],
    ['ami_password', $amiCreds['password'], 'AMI password'],

    // ARI Credentials (3 keys) - Auto-detected on install
    ['ari_url', $ariCreds['url'], 'ARI URL'],
    ['ari_username', $ariCreds['username'], 'ARI username'],
    ['ari_password', $ariCreds['password'], 'ARI password'],

    // GraphQL Credentials (4 keys) - Auto-detected on install
    ['gql_url', $gqlCreds['url'], 'GraphQL endpoint URL'],
    ['gql_token_url', $gqlCreds['token_url'], 'OAuth token URL'],
    ['gql_client_id', $gqlCreds['client_id'], 'OAuth client ID'],
    ['gql_client_secret', $gqlCreds['client_secret'], 'OAuth client secret'],

    // SSL Paths (2 keys) - Auto-detected on install
    ['ssl_cert_path', $sslPaths['cert'], 'SSL certificate path'],
    ['ssl_key_path', $sslPaths['key'], 'SSL private key path'],
];

foreach ($defaults as $config) {
    $key = $config[0];
    $value = $config[1];
    $description = $config[2];

    // Only insert if key doesn't exist (preserve existing values on upgrade)
    $escapedKey = $db->escapeSimple($key);
    $checkSql = "SELECT COUNT(*) as cnt FROM `convergent_config` WHERE `key` = '{$escapedKey}'";
    $checkResult = $db->query($checkSql);

    if (DB::IsError($checkResult)) {
        freepbx_log(FPBX_LOG_ERROR, "Failed to check config key: $key - " . $checkResult->getMessage());
        continue;
    }

    $row = $checkResult->fetchRow(DB_FETCHMODE_ASSOC);
    $count = $row ? (int)$row['cnt'] : 0;

    if ($count == 0) {
        $escapedValue = $db->escapeSimple($value);
        $escapedDesc = $db->escapeSimple($description);
        $insertSql = "INSERT INTO `convergent_config` (`key`, `value`, `description`) VALUES ('{$escapedKey}', '{$escapedValue}', '{$escapedDesc}')";
        $result = $db->query($insertSql);
        if (DB::IsError($result)) {
            freepbx_log(FPBX_LOG_ERROR, "Failed to insert default config: $key - " . $result->getMessage());
        }
    }
}

// Log installation
freepbx_log(FPBX_LOG_NOTICE, "Convergent Dashboard Server module installed successfully");
