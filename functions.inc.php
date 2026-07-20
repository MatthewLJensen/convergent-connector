<?php
/**
 * Convergent Dashboard Server - FreePBX Module
 * Functions Include File
 *
 * FreePBX discovers destinations and dialplan by scanning this file for
 * functions named {rawname}_destinations / {rawname}_getdestinfo /
 * {rawname}_get_config (see framework BMO/Destinations.class.php and
 * BMO/DialplanHooks.class.php). Class methods on the BMO object are NOT
 * scanned for these, so the registration must live here.
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

/**
 * Registers this module's destinations so they appear in the destination
 * dropdowns (inbound routes, IVRs, time conditions, ring groups, etc.).
 */
function convergentdashboard_destinations($index = '') {
    return array(
        array(
            'destination' => 'convergent-fax-receive,s,1',
            'description' => _('Receive Fax'),
            'category'    => _('Convergent Fax'),
        ),
    );
}

/**
 * Identifies whether a destination belongs to this module. Used by the
 * destination-usage checker so our destination isn't flagged as orphaned,
 * and to build the "edit" link shown next to it.
 */
function convergentdashboard_getdestinfo($dest) {
    if ($dest == 'convergent-fax-receive,s,1') {
        return array(
            'description' => _('Convergent Fax: Receive Fax'),
            'edit_url'    => 'config.php?display=convergentdashboard',
        );
    }
    return false;
}

/**
 * Dialplan generation, called by retrieve_conf on every fwconsole reload.
 * Everything added to the global $ext object is written into
 * extensions_additional.conf by the framework.
 */
function convergentdashboard_get_config($engine) {
    global $ext;

    if ($engine != 'asterisk') {
        return;
    }

    // Inbound fax receive - the destination registered above points here.
    $c = 'convergent-fax-receive';
    $ext->add($c, 's', '', new ext_noop('=== CONVERGENT INBOUND FAX ==='));
    $ext->add($c, 's', '', new ext_setvar('FAXOPT(ecm)', 'yes'));
    $ext->add($c, 's', '', new ext_setvar('FAXOPT(minrate)', '4800'));
    $ext->add($c, 's', '', new ext_setvar('FAXOPT(maxrate)', '14400'));
    $ext->add($c, 's', '', new ext_setvar('FAXFILE', '${ASTSPOOLDIR}/fax/${UNIQUEID}.tif'));
    $ext->add($c, 's', '', new ext_stopplaytones());
    $ext->add($c, 's', '', new ext_receivefax('${FAXFILE},zf'));
    $ext->add($c, 's', '', new ext_noop('FAX Result: ${FAXSTATUS} - ${FAXSTATUSSTRING}'));
    $ext->add($c, 's', '', new ext_noop('Saved to: ${FAXFILE}'));
    $ext->add($c, 's', '', new ext_setvar('FAX_RESULT_STATUS', '${FAXSTATUS}'));
    $ext->add($c, 's', '', new ext_setvar('FAX_RESULT_ERROR', '${FAXERROR}'));
    $ext->add($c, 's', '', new ext_setvar('FAX_RESULT_STATUSSTRING', '${FAXSTATUSSTRING}'));
    $ext->add($c, 's', '', new ext_setvar('FAX_RESULT_MODE', '${FAXMODE}'));
    $ext->add($c, 's', '', new ext_hangup());

    // Outbound fax send - dialed via AMI Originate, not exposed as a destination.
    $c = 'convergent-fax-send';
    $ext->add($c, 's', '', new ext_noop('=== CONVERGENT OUTBOUND FAX (via s extension) ==='));
    $ext->add($c, 's', '', new ext_noop('Fax ID: ${FAXID}'));
    $ext->add($c, 's', '', new ext_noop('Destination: ${DESTINATION}'));
    $ext->add($c, 's', '', new ext_noop('File: ${FAXFILE}'));
    $ext->add($c, 's', '', new ext_noop('Trunk: ${FAXTRUNK}'));
    $ext->add($c, 's', '', new ext_setvar('FAXOPT(ecm)', 'yes'));
    $ext->add($c, 's', '', new ext_setvar('FAXOPT(headerinfo)', '${FAXHEADER}'));
    $ext->add($c, 's', '', new ext_setvar('FAXOPT(localstationid)', '${CALLERID(num)}'));
    $ext->add($c, 's', '', new ext_setvar('FAXOPT(minrate)', '4800'));
    $ext->add($c, 's', '', new ext_setvar('FAXOPT(maxrate)', '14400'));
    $ext->add($c, 's', '', new ext_wait('1'));
    $ext->add($c, 's', '', new ext_sendfax('${FAXFILE},zf'));
    $ext->add($c, 's', '', new ext_noop('FAX Result: ${FAXSTATUS} - ${FAXSTATUSSTRING}'));
    $ext->add($c, 's', '', new ext_noop('Pages: ${FAXPAGES}, Rate: ${FAXBITRATE}, Mode: ${FAXMODE}'));
    $ext->add($c, 's', '', new ext_setvar('FAX_RESULT_STATUS', '${FAXSTATUS}'));
    $ext->add($c, 's', '', new ext_setvar('FAX_RESULT_ERROR', '${FAXERROR}'));
    $ext->add($c, 's', '', new ext_setvar('FAX_RESULT_STATUSSTRING', '${FAXSTATUSSTRING}'));
    $ext->add($c, 's', '', new ext_setvar('FAX_RESULT_MODE', '${FAXMODE}'));
    $ext->add($c, 's', '', new ext_hangup());
}
