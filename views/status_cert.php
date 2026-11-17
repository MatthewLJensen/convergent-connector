<?php
/**
 * Convergent Dashboard Server - Status tab: SSL Certificate panel.
 * Rendered by Convergentdashboard::renderStatusSection('cert') and loaded
 * into the Status tab over AJAX. Expects $certificateInfo (getCertificateInfo()).
 */

// Included by renderStatusSection() during an authenticated ajax.php request,
// which defines CONVERGENT_STATUS_SECTION first.
if (!defined('FREEPBX_IS_AUTH') && !defined('CONVERGENT_STATUS_SECTION')) { die('No direct script access allowed'); }

$cert         = $certificateInfo['cert']   ?? ['path' => ''];
$certStatus   = $certificateInfo['status'] ?? false;
$certCode     = $certificateInfo['code']   ?? null;
$certMessage  = $certificateInfo['message'] ?? '';
$expiryStatus = $cert['expiry_status']     ?? null;
$daysLeft     = $cert['days_until_expiry'] ?? null;

if ($certStatus) {
    if ($expiryStatus === 'expired' || $expiryStatus === 'critical') {
        $certPanelClass = 'panel-danger';
        $certLabelClass = 'label-danger';
        $certRowClass   = 'danger';
    } elseif ($expiryStatus === 'warning') {
        $certPanelClass = 'panel-warning';
        $certLabelClass = 'label-warning';
        $certRowClass   = 'warning';
    } else {
        $certPanelClass = 'panel-success';
        $certLabelClass = 'label-success';
        $certRowClass   = 'success';
    }
} else {
    $certPanelClass = 'panel-warning';
    $certLabelClass = 'label-default';
    $certRowClass   = '';
}
?>
<div class="panel <?php echo $certPanelClass; ?>">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-lock"></i> SSL Certificate
            <?php if ($certStatus): ?>
                <span class="label <?php echo $certLabelClass; ?> pull-right">
                    <?php if ($daysLeft !== null && $daysLeft < 0): ?>
                        Expired
                    <?php elseif ($daysLeft !== null): ?>
                        <?php echo (int) $daysLeft; ?> days remaining
                    <?php endif; ?>
                </span>
            <?php else: ?>
                <span class="label label-default pull-right">Not Available</span>
            <?php endif; ?>
        </h3>
    </div>
    <div class="panel-body">
        <?php if (!$certStatus): ?>
            <div class="alert alert-warning" style="margin-bottom: 15px;">
                <i class="fa fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($certMessage); ?>
                <?php if ($certCode === 'not_configured'): ?>
                    <br>Visit the Settings tab and click <strong>Re-detect All Credentials</strong>, or set custom paths manually.
                <?php elseif ($certCode === 'unreadable'): ?>
                    <br>The asterisk user cannot read <code><?php echo htmlspecialchars($cert['path'] ?? ''); ?></code>. Check file permissions or re-detect.
                <?php elseif ($certCode === 'unparseable'): ?>
                    <br><code><?php echo htmlspecialchars($cert['path'] ?? ''); ?></code> could not be parsed as PEM.
                <?php elseif ($certCode === 'missing'): ?>
                    <br>Path: <code><?php echo htmlspecialchars($cert['path'] ?? ''); ?></code>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <table class="table table-bordered">
                <tr>
                    <th width="200">Path</th>
                    <td><code><?php echo htmlspecialchars($cert['path']); ?></code></td>
                </tr>
                <tr>
                    <th>Subject CN</th>
                    <td><?php echo htmlspecialchars($cert['subject_cn'] ?: '(none)'); ?></td>
                </tr>
                <tr>
                    <th>Issuer</th>
                    <td>
                        <?php echo htmlspecialchars($cert['issuer_cn'] ?: '(unknown)'); ?>
                        <?php if (!empty($cert['is_lets_encrypt'])): ?>
                            <span class="label label-info" style="margin-left: 6px;">Let's Encrypt</span>
                        <?php endif; ?>
                        <?php if (!empty($cert['self_signed'])): ?>
                            <span class="label label-warning" style="margin-left: 6px;">Self-Signed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Valid From</th>
                    <td><?php echo htmlspecialchars($cert['valid_from']); ?></td>
                </tr>
                <tr>
                    <th>Valid To</th>
                    <td><?php echo htmlspecialchars($cert['valid_to']); ?></td>
                </tr>
                <tr class="<?php echo $certRowClass; ?>">
                    <th>Days Until Expiry</th>
                    <td>
                        <span class="label <?php echo $certLabelClass; ?>" style="font-size: 1.1em; padding: 6px 12px;">
                            <?php if ($daysLeft < 0): ?>
                                Expired <?php echo abs((int) $daysLeft); ?> days ago
                            <?php else: ?>
                                <?php echo (int) $daysLeft; ?> days
                            <?php endif; ?>
                        </span>
                    </td>
                </tr>
                <?php if (!empty($cert['sans'])): ?>
                <tr>
                    <th>Subject Alt Names</th>
                    <td>
                        <?php foreach ($cert['sans'] as $san): ?>
                            <span class="label label-default" style="margin-right: 4px; display: inline-block; margin-bottom: 2px;">
                                <?php echo htmlspecialchars($san); ?>
                            </span>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
        <?php endif; ?>

        <div style="margin-top: 15px;">
            <button type="button" class="btn btn-primary" id="btn-update-certificates">
                <i class="fa fa-refresh"></i> Update All Certificates
            </button>
            <button type="button" class="btn btn-default" id="btn-refresh-cert-info" style="margin-left: 8px;">
                <i class="fa fa-sync"></i> Refresh
            </button>
            <span class="text-muted" style="margin-left: 12px;">
                Runs <code>fwconsole certificates --updateall</code> to renew managed certificates.
            </span>
            <div id="cert-action-result" class="alert" style="margin-top: 15px; display: none;"></div>
        </div>
    </div>
</div>
