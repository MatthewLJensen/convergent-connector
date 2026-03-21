<?php
/**
 * Convergent Dashboard Server - FreePBX Module
 * Uninstall Script
 *
 * Removes database tables created by this module
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

global $db;

// Drop configuration table
$sql = "DROP TABLE IF EXISTS `convergent_config`";
$result = $db->query($sql);
if (DB::IsError($result)) {
    freepbx_log(FPBX_LOG_ERROR, "Failed to drop convergent_config table: " . $result->getMessage());
}

// Drop status log table
$sql = "DROP TABLE IF EXISTS `convergent_status_log`";
$result = $db->query($sql);
if (DB::IsError($result)) {
    freepbx_log(FPBX_LOG_ERROR, "Failed to drop convergent_status_log table: " . $result->getMessage());
}

freepbx_log(FPBX_LOG_NOTICE, "Convergent Dashboard Server module uninstalled");
