<?php
/**
 * Convergent Dashboard Server - Logs View
 */

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Get recent status log
$statusLog = $convergent->getStatusLog(50);
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-history"></i> Service Action History</h3>
    </div>
    <div class="panel-body">
        <?php if (empty($statusLog)): ?>
        <div class="alert alert-info">
            <i class="fa fa-info-circle"></i> No service actions have been logged yet.
        </div>
        <?php else: ?>
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th width="180">Time</th>
                    <th width="100">Status</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($statusLog as $log): ?>
                <tr>
                    <td><?php echo htmlspecialchars($log['logged_at']); ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span class="label label-success">Success</span>
                        <?php elseif ($log['status'] === 'failed'): ?>
                            <span class="label label-danger">Failed</span>
                        <?php else: ?>
                            <span class="label label-default"><?php echo htmlspecialchars($log['status']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($log['message']); ?></td>
                    <td>
                        <?php if (!empty($log['details'])): ?>
                            <button type="button" class="btn btn-xs btn-default btn-show-details"
                                    data-details="<?php echo htmlspecialchars($log['details']); ?>">
                                <i class="fa fa-info-circle"></i> View
                            </button>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Live Service Logs -->
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-file-text-o"></i> Service Logs (journalctl)
            <button type="button" class="btn btn-xs btn-default pull-right" id="btn-refresh-journal">
                <i class="fa fa-refresh"></i> Refresh
            </button>
        </h3>
    </div>
    <div class="panel-body">
        <div class="form-inline" style="margin-bottom: 15px;">
            <label for="log-lines">Show last</label>
            <select id="log-lines" class="form-control">
                <option value="50">50</option>
                <option value="100" selected>100</option>
                <option value="200">200</option>
                <option value="500">500</option>
            </select>
            <label>lines</label>

            <div class="checkbox" style="margin-left: 20px;">
                <label>
                    <input type="checkbox" id="log-follow"> Auto-refresh (every 5s)
                </label>
            </div>
        </div>

        <pre id="journal-output" style="max-height: 500px; overflow-y: auto; background: #1e1e1e; color: #d4d4d4; padding: 15px; font-size: 12px;">
Loading logs...
        </pre>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Action Details</h4>
            </div>
            <div class="modal-body">
                <pre id="details-content" style="max-height: 400px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show details modal
document.querySelectorAll('.btn-show-details').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var details = this.getAttribute('data-details');
        document.getElementById('details-content').textContent = details;
        $('#detailsModal').modal('show');
    });
});

// Load journal logs
function loadJournalLogs() {
    var lines = document.getElementById('log-lines').value;
    var serviceName = '<?php echo htmlspecialchars($config['config']['service_name']['value'] ?? 'convergent-monitoring'); ?>';

    // Make AJAX call to get logs
    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: {
            module: 'convergentdashboard',
            command: 'get_journal',
            lines: lines,
            service: serviceName
        },
        success: function(response) {
            if (response.logs) {
                document.getElementById('journal-output').textContent = response.logs;
                // Scroll to bottom
                var output = document.getElementById('journal-output');
                output.scrollTop = output.scrollHeight;
            }
        },
        error: function() {
            document.getElementById('journal-output').textContent = 'Error loading logs. Try refreshing the page.';
        }
    });
}

// Initial load
loadJournalLogs();

// Refresh button
document.getElementById('btn-refresh-journal').addEventListener('click', loadJournalLogs);

// Lines dropdown change
document.getElementById('log-lines').addEventListener('change', loadJournalLogs);

// Auto-refresh
var refreshInterval = null;
document.getElementById('log-follow').addEventListener('change', function() {
    if (this.checked) {
        refreshInterval = setInterval(loadJournalLogs, 5000);
    } else if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
});
</script>
