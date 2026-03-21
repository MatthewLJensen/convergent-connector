The following documentation was created from the open source firewall module via an llm.

FreePBX Firewall Module: Root Hook Execution via Sysadmin
Overview
The FreePBX Firewall module implements a sophisticated hook system that leverages the Sysadmin module to execute privileged operations as root. This is achieved through incron (inode change notification) triggers that monitor the firewall spool directory for file changes.

Architecture
1. Hook Trigger Mechanism
The core mechanism is defined in Firewall.class.php within the runHook() method:

PHP
public function runHook($hookname, $params = false)
Key Process:

The method checks for /etc/incron.d/sysadmin to verify Sysadmin RPM is installed
Creates trigger files in the asterisk spool directory at {ASTSPOOLDIR}/incron/
Incron monitors this directory for file modifications
When files are created/modified, Sysadmin's incron rules execute the corresponding hooks as root
2. Hook Execution Flow
Step 1: File Creation in Spool Directory

Code
Firewall PHP code creates: /var/spool/asterisk/incron/firewall.{hookname}
Step 2: Incron Detection

Sysadmin's incron rules monitor /etc/incron.d/sysadmin
The /var/spool/asterisk/incron/ directory is watched by Sysadmin
File creation/modification triggers incron event
Step 3: Hook Execution

Incron (running as root via Sysadmin) executes the corresponding hook script
The hook script is located in /admin/modules/firewall/hooks/{hookname}
All hooks are PHP scripts with a PHP shebang: #!/usr/bin/env php
3. Parameter Passing
The module supports passing parameters to hooks through two methods:

Method 1: Content-Based (Modern Sysadmin)

Checks /etc/sysadmin_contents_max for max content size
Stores base64-encoded, gzip-compressed JSON in file contents
Filename becomes: firewall.{hookname}.CONTENTS
Method 2: Filename-Based (Legacy)

Encodes parameters directly in the filename
Base64 characters are "derped" (/ replaced with _) for filesystem safety
Filename becomes: firewall.{hookname}.{encoded_params}
PHP
if (is_array($params)) {
    $b = base64_encode(gzcompress(json_encode($params)));
    if ($max) {
        $filename .= ".CONTENTS";
        $contents = $b;
    } else {
        $filename .= ".".str_replace('/', '_', $b);
    }
}
Hook Security Implementation
Each hook follows a strict security validation pattern:

1. Signature Verification
All hooks begin with:

PHP
require '/usr/lib/sysadmin/includes.php';
$g = new \Sysadmin\GPG();
$sigfile = \Sysadmin\FreePBX::Config()->get('AMPWEBROOT')."/admin/modules/firewall/module.sig";
$sig = $g->checkSig($sigfile);

if (!isset($sig['config']['hash']) || $sig['config']['hash'] !== "sha256") {
    throw new \Exception("Invalid sig file.. Hash is not sha256");
}
2. File Hash Validation
Hooks validate included files against the module signature:

PHP
if (empty($sig['hashes']['hooks/validator.php'])) {
    throw new \Exception("Validator not part of module.sig");
}
$vhash = hash_file('sha256', __DIR__."/validator.php");
if ($vhash !== $sig['hashes']['hooks/validator.php']) {
    throw new \Exception("Validator tampered");
}
3. Validator Class Instantiation
The Validator class is instantiated with preloaded hashes:

PHP
$v = new \FreePBX\modules\Firewall\Validator($sig);
This allows hooks to safely include module files while running as root.

Available Hooks
The module includes several hooks:

1. firewall - Main Firewall Hook
Location: hooks/firewall
Purpose: Starts the main voipfirewalld daemon
Validates: hooks/voipfirewalld binary before execution
Tasks:
Validates GPG signature
Verifies firewalld daemon integrity
Starts daemon with proper logging
Sets log file permissions
2. addrfcnetworks - RFC1918 Network Configuration
Location: hooks/addrfcnetworks
Purpose: Automatically adds RFC1918 private address ranges to the trusted zone
Networks Added:
192.168.0.0/16
172.16.0.0/12
10.0.0.0/8
fc00::/8
fd00::/8
3. removenetwork - Network Removal
Location: hooks/removenetwork
Purpose: Removes networks from firewall zones
Input: JSON-encoded parameters containing network and zone
4. updateipset - IPset Management
Location: hooks/updateipset
Purpose: Manages firewall IP sets for dynamic IP ranges
Actions: Add ports or flush IP sets
Execution Examples
Example 1: Running the Main Firewall Hook
PHP
$fw = \FreePBX::Firewall();
$fw->runHook('firewall');
This triggers the firewall daemon to start as root through the incron mechanism.

Example 2: Adding Networks with Parameters
PHP
$params = array(
    'network' => '192.168.1.0',
    'zone' => 'trusted'
);
$fw->runHook('addrfcnetworks', $params);
The parameters are compressed, base64-encoded, and either embedded in the file or encoded in the filename.

Example 3: From Restore Hook
PHP
// In Restore.php preHook()
if(\FreePBX::Modules()->checkStatus("sysadmin")) {
    $fw->fixCustomRules();  // This internally calls runHook()
}
Sysadmin Integration Points
1. Incron Monitoring
Sysadmin configures incron to watch /var/spool/asterisk/incron/
Rules defined in /etc/incron.d/sysadmin trigger on file create/modify events
2. Root Privilege Escalation
Incron runs as root through Sysadmin
Provides secure privilege escalation for web-accessible code
The web server (asterisk user) cannot directly execute as root
3. Service Management
The firewall daemon (voipfirewalld) runs continuously as root
Sysadmin manages service startup/shutdown
Firewall module communicates with daemon for configuration changes
Error Handling
The module implements comprehensive error handling:

PHP
// Wait for up to 10 seconds to ensure hook completion
$i = 0;
while ($i < 10) {
    if (!file_exists($filename)) {
        return true;  // Hook executed successfully
    }
    sleep(1);
    $i++;
}
throw new \Exception("Hook execution timeout");
Key Security Features
GPG Signature Validation - All hooks verify module integrity before execution
File Hash Checking - Ensures included files haven't been tampered with
Parameter Encoding - Prevents injection attacks through parameter encoding
Incron Isolation - Hooks run in isolated incron environment
Rate Limiting - Sysadmin prevents DOS through file creation limits
Troubleshooting
If hooks fail to execute, check:

Sysadmin Installation: rpm -qa | grep sysadmin
Incron Configuration: cat /etc/incron.d/sysadmin
Spool Directory: ls -la /var/spool/asterisk/incron/
Module Signature: Verify module.sig file exists and is valid
Logs: Check /var/log/asterisk/firewall.log for errors
This architecture provides secure, isolated root execution for the Firewall module without requiring direct SSH access or SUDO configuration, leveraging Sysadmin's inode change notification system.
