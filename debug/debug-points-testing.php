<?php
/**
 * SlimWP Points Testing Debug Page
 * 
 * Comprehensive testing tool for the points system including:
 * - Basic point operations testing
 * - Race condition detection
 * - API function coverage
 * - Performance monitoring
 * 
 * SECURITY: Delete this file after testing - admin access only
 */

// Load WordPress
// Find WordPress root directory
$wp_root = null;
$current_dir = __DIR__;

// Go up directories to find wp-config.php
for ($i = 0; $i < 10; $i++) {
    $current_dir = dirname($current_dir);
    if (file_exists($current_dir . '/wp-config.php')) {
        $wp_root = $current_dir;
        break;
    }
    // Also check if this is the WordPress root with wp-load.php
    if (file_exists($current_dir . '/wp-load.php')) {
        $wp_root = $current_dir;
        break;
    }
}

if (!$wp_root) {
    die('WordPress not found. Please ensure this file is in a WordPress plugin directory.');
}

// Load WordPress
if (file_exists($wp_root . '/wp-load.php')) {
    require_once($wp_root . '/wp-load.php');
} else if (file_exists($wp_root . '/wp-config.php')) {
    require_once($wp_root . '/wp-config.php');
    if (file_exists($wp_root . '/wp-settings.php')) {
        require_once($wp_root . '/wp-settings.php');
    }
} else {
    die('Could not load WordPress. wp-load.php or wp-config.php not found.');
}

// Security check - only allow admins
if (!current_user_can('manage_options')) {
    die('Access denied. You must be an administrator to use this debug tool.');
}

// Initialize SlimWP if not already done
if (!class_exists('SlimWP_Points')) {
    require_once(dirname(__FILE__) . '/../slimwp-simple-points.php');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SlimWP Points Testing Debug</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0; padding: 20px; background: #f1f1f1; color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: #0073aa; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .test-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
        .test-section { 
            background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #0073aa;
        }
        .test-section h2 { margin-top: 0; color: #0073aa; }
        .test-section h3 { color: #555; margin: 15px 0 10px 0; }
        
        /* Status indicators */
        .status { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status.success { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
        .status.warning { background: #fff3cd; color: #856404; }
        .status.info { background: #d1ecf1; color: #0c5460; }
        .status.pending { background: #e2e3e5; color: #383d41; }
        .status.running { background: #cce5ff; color: #004085; animation: pulse 1.5s ease-in-out infinite alternate; }
        
        @keyframes pulse { from { opacity: 0.6; } to { opacity: 1; } }
        
        /* Form elements */
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { 
            width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;
            font-size: 14px; transition: border-color 0.2s;
        }
        .form-control:focus { border-color: #0073aa; outline: none; box-shadow: 0 0 0 2px rgba(0,115,170,0.1); }
        
        /* Buttons */
        .btn { 
            padding: 10px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;
            transition: all 0.2s; text-decoration: none; display: inline-block; margin: 2px;
        }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-primary { background: #0073aa; color: white; }
        .btn-primary:hover:not(:disabled) { background: #005a87; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover:not(:disabled) { background: #218838; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover:not(:disabled) { background: #c82333; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-warning:hover:not(:disabled) { background: #e0a800; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover:not(:disabled) { background: #5a6268; }
        
        /* Results */
        .results { 
            margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px;
            border: 1px solid #e9ecef; min-height: 60px; font-family: 'Courier New', monospace;
            font-size: 13px; line-height: 1.4; white-space: pre-wrap; overflow-y: auto; max-height: 300px;
        }
        .results.active { border-color: #0073aa; }
        
        /* User info */
        .user-info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin-bottom: 15px; }
        .balance-display { display: flex; gap: 15px; margin: 10px 0; }
        .balance-item { 
            text-align: center; padding: 10px; background: white; border-radius: 4px; flex: 1;
            border: 2px solid #e9ecef; transition: all 0.3s;
        }
        .balance-item.updated { border-color: #28a745; animation: flash 0.5s; }
        @keyframes flash { 0%, 100% { background: white; } 50% { background: #d4edda; } }
        .balance-number { font-size: 24px; font-weight: bold; margin: 5px 0; }
        .balance-label { font-size: 12px; color: #666; text-transform: uppercase; }
        
        /* Concurrency testing */
        .concurrency-controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .concurrency-status { margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; }
        .progress-bar { 
            width: 100%; height: 6px; background: #e9ecef; border-radius: 3px; overflow: hidden; margin: 10px 0;
        }
        .progress-fill { 
            height: 100%; background: #0073aa; width: 0%; transition: width 0.3s; border-radius: 3px;
        }
        
        /* Security warning */
        .security-warning { 
            background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; 
            border-radius: 4px; margin-bottom: 20px; border-left: 4px solid #dc3545;
        }
        .security-warning h3 { margin-top: 0; color: #721c24; }
        
        /* Responsive */
        @media (max-width: 768px) {
            .test-grid { grid-template-columns: 1fr; }
            .concurrency-controls { justify-content: center; }
            .balance-display { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Security Warning -->
        <div class="security-warning">
            <h3>⚠️ Security Notice</h3>
            <p><strong>Important:</strong> This is a testing tool that should only be used in development environments. Delete this file after testing for security reasons.</p>
        </div>
        
        <!-- Header -->
        <div class="header">
            <h1>🧪 SlimWP Points Testing Debug</h1>
            <p>Comprehensive testing tool for points system validation and race condition detection</p>
        </div>
        
        <!-- System Status Check -->
        <div class="test-section">
            <h2>📊 System Status</h2>
            <?php
            // Debug mode check
            $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
            echo '<div class="status ' . ($debug_enabled ? 'success' : 'error') . '">WP_DEBUG: ' . ($debug_enabled ? '✓ Enabled' : '✗ Disabled (Required for AJAX!)') . '</div>';
            
            // Check if SlimWP is initialized
            $slimwp_available = class_exists('SlimWP_Points');
            $slimwp_instance = $slimwp_available ? SlimWP_Points::get_instance() : null;
            
            // Check database tables
            global $wpdb;
            $points_table = $wpdb->prefix . 'slimwp_user_points_transactions';
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$points_table}'") === $points_table;
            
            // Check current user
            $current_user = wp_get_current_user();
            $is_admin = current_user_can('manage_options');
            
            // Get sample users for testing
            $test_users = get_users(array('number' => 10, 'role__not_in' => array('administrator')));
            if (empty($test_users)) {
                $test_users = get_users(array('number' => 10)); // Include admins if no other users
            }
            
            echo '<div class="status ' . ($slimwp_available ? 'success' : 'error') . '">SlimWP Class: ' . ($slimwp_available ? '✓ Available' : '✗ Not Available') . '</div>';
            echo '<div class="status ' . ($table_exists ? 'success' : 'error') . '">Database Table: ' . ($table_exists ? '✓ Exists (' . $points_table . ')' : '✗ Missing (' . $points_table . ')') . '</div>';
            echo '<div class="status ' . ($is_admin ? 'success' : 'error') . '">Admin Access: ' . ($is_admin ? '✓ Yes (User: ' . $current_user->display_name . ')' : '✗ No') . '</div>';
            echo '<div class="status ' . (!empty($test_users) ? 'success' : 'warning') . '">Test Users: ' . count($test_users) . ' available</div>';
            
            // AJAX URL check
            $ajax_url = admin_url('admin-ajax.php');
            echo '<div class="status info">AJAX URL: ' . $ajax_url . '</div>';
            
            // Nonce check
            $nonce = wp_create_nonce('slimwp_debug_nonce');
            echo '<div class="status info">Debug Nonce: ' . substr($nonce, 0, 10) . '...</div>';
            
            if (!$slimwp_available || !$table_exists) {
                echo '<p class="status error">⚠️ System not ready for testing. Please check your SlimWP installation.</p>';
            }
            ?>
            
            <h3>🔧 AJAX Connection Test</h3>
            <button class="btn btn-primary" onclick="testAjaxConnection()">Test AJAX Connection</button>
            <div class="results" id="ajax-test-results" style="margin-top: 10px; min-height: 40px;">Click the button above to test AJAX connectivity...</div>
        </div>
        
        <?php if ($slimwp_available && $table_exists): ?>
        <div class="test-grid">
            <!-- User Selection & Balance Display -->
            <div class="test-section">
                <h2>👤 Test User Selection</h2>
                <div class="form-group">
                    <label for="test-user">Select Test User:</label>
                    <select id="test-user" class="form-control">
                        <option value="">-- Select User --</option>
                        <?php
                        foreach ($test_users as $user) {
                            $free_balance = $slimwp_instance->get_free_balance($user->ID);
                            $permanent_balance = $slimwp_instance->get_permanent_balance($user->ID);
                            $total_balance = $free_balance + $permanent_balance;
                            echo '<option value="' . $user->ID . '">' . esc_html($user->display_name) . ' (ID: ' . $user->ID . ') - Total: ' . $total_balance . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="user-info" id="user-info" style="display: none;">
                    <h3>Current Balance</h3>
                    <div class="balance-display">
                        <div class="balance-item" id="free-balance">
                            <div class="balance-number" id="free-balance-number">0</div>
                            <div class="balance-label">Free Points</div>
                        </div>
                        <div class="balance-item" id="permanent-balance">
                            <div class="balance-number" id="permanent-balance-number">0</div>
                            <div class="balance-label">Permanent Points</div>
                        </div>
                        <div class="balance-item" id="total-balance">
                            <div class="balance-number" id="total-balance-number">0</div>
                            <div class="balance-label">Total Points</div>
                        </div>
                    </div>
                    <button class="btn btn-secondary" onclick="refreshBalance()">🔄 Refresh Balance</button>
                    <button class="btn btn-warning" onclick="resetTestUser()">🔄 Reset User (Free: 100, Permanent: 50)</button>
                </div>
            </div>
            
            <!-- Basic Point Operations -->
            <div class="test-section">
                <h2>➕ Basic Point Operations</h2>
                
                <h3>Add Points</h3>
                <div class="form-group">
                    <label>Amount:</label>
                    <input type="number" id="add-amount" class="form-control" value="10" min="0" max="10000">
                </div>
                <div class="form-group">
                    <label>Balance Type:</label>
                    <select id="add-balance-type" class="form-control">
                        <option value="free">Free Balance</option>
                        <option value="permanent">Permanent Balance</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <input type="text" id="add-description" class="form-control" value="Test add operation">
                </div>
                <button class="btn btn-primary" onclick="testAddPoints()">➕ Add Points</button>
                
                <h3>Subtract Points</h3>
                <div class="form-group">
                    <label>Amount:</label>
                    <input type="number" id="subtract-amount" class="form-control" value="5" min="0" max="10000">
                </div>
                <div class="form-group">
                    <label>Description:</label>
                    <input type="text" id="subtract-description" class="form-control" value="Test subtract operation">
                </div>
                <button class="btn btn-danger" onclick="testSubtractPoints()">➖ Subtract Points</button>
                
                <h3>Set Balance</h3>
                <div class="form-group">
                    <label>New Balance:</label>
                    <input type="number" id="set-amount" class="form-control" value="50" min="0" max="10000">
                </div>
                <div class="form-group">
                    <label>Balance Type:</label>
                    <select id="set-balance-type" class="form-control">
                        <option value="free">Free Balance</option>
                        <option value="permanent">Permanent Balance</option>
                    </select>
                </div>
                <button class="btn btn-warning" onclick="testSetBalance()">🎯 Set Balance</button>
                
                <div class="results" id="basic-operations-results">Ready for testing...</div>
            </div>
            
            <!-- Race Condition Testing -->
            <div class="test-section">
                <h2>⚡ Race Condition Testing</h2>
                <p>Test concurrent operations to detect database race conditions and ensure transaction integrity.</p>
                
                <div class="concurrency-controls">
                    <div class="form-group">
                        <label>Operations Count:</label>
                        <input type="number" id="concurrent-count" class="form-control" value="5" min="2" max="20" style="width: 80px;">
                    </div>
                    <div class="form-group">
                        <label>Amount per Op:</label>
                        <input type="number" id="concurrent-amount" class="form-control" value="1" min="1" max="100" style="width: 80px;">
                    </div>
                </div>
                
                <button class="btn btn-success" onclick="testConcurrentAdd()">🔄 Concurrent Add</button>
                <button class="btn btn-danger" onclick="testConcurrentSubtract()">🔄 Concurrent Subtract</button>
                <button class="btn btn-warning" onclick="testMixedConcurrent()">🔄 Mixed Operations</button>
                
                <div class="concurrency-status" id="concurrency-status" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div id="progress-text">Preparing tests...</div>
                </div>
                
                <div class="results" id="concurrency-results">Click a test button to start race condition testing...</div>
            </div>
            
            <!-- API Function Coverage -->
            <div class="test-section">
                <h2>🔧 API Function Coverage</h2>
                <p>Test all documented API functions and helper methods.</p>
                
                <button class="btn btn-primary" onclick="testGlobalFunctions()">🌐 Test Global Functions</button>
                <button class="btn btn-primary" onclick="testClassMethods()">🏗️ Test Class Methods</button>
                <button class="btn btn-primary" onclick="testErrorHandling()">❌ Test Error Handling</button>
                <button class="btn btn-success" onclick="runAllAPITests()">🚀 Run All API Tests</button>
                
                <div class="results" id="api-results">Ready to test API functions...</div>
            </div>
            
            <!-- Performance Testing -->
            <div class="test-section">
                <h2>📈 Performance Testing</h2>
                <p>Measure operation timing and database performance.</p>
                
                <div class="form-group">
                    <label>Iterations:</label>
                    <input type="number" id="perf-iterations" class="form-control" value="100" min="10" max="1000">
                </div>
                
                <button class="btn btn-primary" onclick="testPerformanceAdd()">⏱️ Add Performance</button>
                <button class="btn btn-primary" onclick="testPerformanceSubtract()">⏱️ Subtract Performance</button>
                <button class="btn btn-primary" onclick="testPerformanceRead()">⏱️ Read Performance</button>
                <button class="btn btn-success" onclick="runAllPerformanceTests()">🏃 Run All Performance Tests</button>
                
                <div class="results" id="performance-results">Ready to run performance tests...</div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Test Log -->
        <div class="test-section">
            <h2>📋 Complete Test Log</h2>
            <button class="btn btn-secondary" onclick="clearAllLogs()">🗑️ Clear All Logs</button>
            <button class="btn btn-secondary" onclick="exportTestResults()">💾 Export Results</button>
            <div class="results" id="complete-log">All test results will appear here...</div>
        </div>
    </div>

    <script>
    // Global variables
    let currentUserId = null;
    let testLog = [];
    let concurrentOperations = [];
    
    // WordPress AJAX URL
    const ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
    // User selection handler
    document.getElementById('test-user').addEventListener('change', function() {
        currentUserId = this.value;
        if (currentUserId) {
            document.getElementById('user-info').style.display = 'block';
            refreshBalance();
        } else {
            document.getElementById('user-info').style.display = 'none';
        }
    });
    
    // Utility functions
    function logMessage(message, type = 'info') {
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `[${timestamp}] ${message}`;
        testLog.push(logEntry);
        
        // Update complete log
        const logElement = document.getElementById('complete-log');
        logElement.textContent = testLog.join('\n');
        logElement.scrollTop = logElement.scrollHeight;
        
        console.log(logEntry);
    }
    
    function updateResults(elementId, message, isActive = false) {
        const element = document.getElementById(elementId);
        element.textContent = message;
        element.classList.toggle('active', isActive);
    }
    
    function showError(elementId, message) {
        updateResults(elementId, `❌ ERROR: ${message}`);
        logMessage(`ERROR: ${message}`, 'error');
    }
    
    function showSuccess(elementId, message) {
        updateResults(elementId, `✅ SUCCESS: ${message}`);
        logMessage(`SUCCESS: ${message}`, 'success');
    }
    
    function showInfo(elementId, message) {
        updateResults(elementId, `ℹ️ INFO: ${message}`);
        logMessage(`INFO: ${message}`, 'info');
    }
    
    // Balance management
    function refreshBalance() {
        if (!currentUserId) {
            showError('basic-operations-results', 'Please select a test user first');
            return;
        }
        
        showInfo('basic-operations-results', 'Refreshing balance...');
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_get_balance&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers);
            return response.text().then(text => {
                console.log('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                }
            });
        })
        .then(data => {
            console.log('Parsed data:', data);
            if (data.success) {
                updateBalanceDisplay(data.data);
                showSuccess('basic-operations-results', 'Balance refreshed successfully');
            } else {
                showError('basic-operations-results', data.data || 'Failed to refresh balance');
            }
        })
        .catch(error => {
            console.error('Full error:', error);
            showError('basic-operations-results', `Network error: ${error.message}`);
        });
    }
    
    function updateBalanceDisplay(balances) {
        document.getElementById('free-balance-number').textContent = balances.free;
        document.getElementById('permanent-balance-number').textContent = balances.permanent;
        document.getElementById('total-balance-number').textContent = balances.total;
        
        // Add updated animation
        ['free-balance', 'permanent-balance', 'total-balance'].forEach(id => {
            const element = document.getElementById(id);
            element.classList.add('updated');
            setTimeout(() => element.classList.remove('updated'), 500);
        });
    }
    
    function resetTestUser() {
        if (!currentUserId) {
            showError('basic-operations-results', 'Please select a test user first');
            return;
        }
        
        if (!confirm('Reset test user to Free: 100, Permanent: 50?')) return;
        
        showInfo('basic-operations-results', 'Resetting test user...');
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_reset_user&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateBalanceDisplay(data.data);
                showSuccess('basic-operations-results', 'Test user reset successfully');
            } else {
                showError('basic-operations-results', data.data || 'Failed to reset user');
            }
        })
        .catch(error => {
            showError('basic-operations-results', `Network error: ${error.message}`);
        });
    }
    
    // Basic operation tests
    function testAddPoints() {
        if (!currentUserId) {
            showError('basic-operations-results', 'Please select a test user first');
            return;
        }
        
        const amount = document.getElementById('add-amount').value;
        const balanceType = document.getElementById('add-balance-type').value;
        const description = document.getElementById('add-description').value;
        
        if (!amount || amount <= 0) {
            showError('basic-operations-results', 'Please enter a valid amount');
            return;
        }
        
        showInfo('basic-operations-results', `Adding ${amount} ${balanceType} points...`);
        
        const startTime = performance.now();
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_add_points&user_id=${currentUserId}&amount=${amount}&balance_type=${balanceType}&description=${encodeURIComponent(description)}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            const duration = (performance.now() - startTime).toFixed(2);
            if (data.success) {
                updateBalanceDisplay(data.data.balances);
                showSuccess('basic-operations-results', `Added ${amount} ${balanceType} points (${duration}ms)\nNew total: ${data.data.balances.total}`);
            } else {
                showError('basic-operations-results', data.data || 'Failed to add points');
            }
        })
        .catch(error => {
            showError('basic-operations-results', `Network error: ${error.message}`);
        });
    }
    
    function testSubtractPoints() {
        if (!currentUserId) {
            showError('basic-operations-results', 'Please select a test user first');
            return;
        }
        
        const amount = document.getElementById('subtract-amount').value;
        const description = document.getElementById('subtract-description').value;
        
        if (!amount || amount <= 0) {
            showError('basic-operations-results', 'Please enter a valid amount');
            return;
        }
        
        showInfo('basic-operations-results', `Subtracting ${amount} points...`);
        
        const startTime = performance.now();
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_subtract_points&user_id=${currentUserId}&amount=${amount}&description=${encodeURIComponent(description)}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            const duration = (performance.now() - startTime).toFixed(2);
            if (data.success) {
                updateBalanceDisplay(data.data.balances);
                showSuccess('basic-operations-results', `Subtracted ${amount} points (${duration}ms)\nNew total: ${data.data.balances.total}\n${data.data.message || ''}`);
            } else {
                showError('basic-operations-results', data.data || 'Failed to subtract points');
            }
        })
        .catch(error => {
            showError('basic-operations-results', `Network error: ${error.message}`);
        });
    }
    
    function testSetBalance() {
        if (!currentUserId) {
            showError('basic-operations-results', 'Please select a test user first');
            return;
        }
        
        const amount = document.getElementById('set-amount').value;
        const balanceType = document.getElementById('set-balance-type').value;
        
        if (amount === '' || amount < 0) {
            showError('basic-operations-results', 'Please enter a valid amount');
            return;
        }
        
        showInfo('basic-operations-results', `Setting ${balanceType} balance to ${amount}...`);
        
        const startTime = performance.now();
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_set_balance&user_id=${currentUserId}&amount=${amount}&balance_type=${balanceType}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            const duration = (performance.now() - startTime).toFixed(2);
            if (data.success) {
                updateBalanceDisplay(data.data.balances);
                showSuccess('basic-operations-results', `Set ${balanceType} balance to ${amount} (${duration}ms)\nNew total: ${data.data.balances.total}`);
            } else {
                showError('basic-operations-results', data.data || 'Failed to set balance');
            }
        })
        .catch(error => {
            showError('basic-operations-results', `Network error: ${error.message}`);
        });
    }
    
    // Concurrency testing functions will be implemented in the next part
    function testConcurrentAdd() {
        if (!currentUserId) {
            showError('concurrency-results', 'Please select a test user first');
            return;
        }
        
        const count = parseInt(document.getElementById('concurrent-count').value);
        const amount = parseInt(document.getElementById('concurrent-amount').value);
        
        showInfo('concurrency-results', `Starting ${count} concurrent add operations...`);
        runConcurrencyTest('add', count, amount);
    }
    
    function testConcurrentSubtract() {
        if (!currentUserId) {
            showError('concurrency-results', 'Please select a test user first');
            return;
        }
        
        const count = parseInt(document.getElementById('concurrent-count').value);
        const amount = parseInt(document.getElementById('concurrent-amount').value);
        
        showInfo('concurrency-results', `Starting ${count} concurrent subtract operations...`);
        runConcurrencyTest('subtract', count, amount);
    }
    
    function testMixedConcurrent() {
        if (!currentUserId) {
            showError('concurrency-results', 'Please select a test user first');
            return;
        }
        
        const count = parseInt(document.getElementById('concurrent-count').value);
        const amount = parseInt(document.getElementById('concurrent-amount').value);
        
        showInfo('concurrency-results', `Starting ${count} mixed concurrent operations...`);
        runConcurrencyTest('mixed', count, amount);
    }
    
    function runConcurrencyTest(type, count, amount) {
        // Show progress
        document.getElementById('concurrency-status').style.display = 'block';
        const progressFill = document.getElementById('progress-fill');
        const progressText = document.getElementById('progress-text');
        
        progressFill.style.width = '0%';
        progressText.textContent = 'Preparing tests...';
        
        const startTime = performance.now();
        let completed = 0;
        let results = [];
        
        // Create concurrent operations
        const operations = [];
        for (let i = 0; i < count; i++) {
            let operation;
            
            if (type === 'mixed') {
                operation = (i % 2 === 0) ? 'add' : 'subtract';
            } else {
                operation = type;
            }
            
            const opData = {
                action: `slimwp_debug_${operation}_points`,
                user_id: currentUserId,
                amount: amount,
                description: `Concurrent ${operation} #${i + 1}`,
                balance_type: 'free',
                nonce: '<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>'
            };
            
            operations.push(
                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(opData).toString()
                })
                .then(response => response.json())
                .then(data => {
                    completed++;
                    const progress = (completed / count) * 100;
                    progressFill.style.width = `${progress}%`;
                    progressText.textContent = `Completed ${completed}/${count} operations`;
                    
                    return { index: completed, success: data.success, data: data.data };
                })
            );
        }
        
        // Wait for all operations to complete
        Promise.all(operations)
            .then(opResults => {
                const duration = (performance.now() - startTime).toFixed(2);
                const successful = opResults.filter(result => result.success).length;
                const failed = count - successful;
                
                // Get final balance
                refreshBalance();
                
                let resultMessage = `🏁 Concurrency Test Complete (${duration}ms)\n`;
                resultMessage += `Total Operations: ${count}\n`;
                resultMessage += `Successful: ${successful}\n`;
                resultMessage += `Failed: ${failed}\n`;
                resultMessage += `Success Rate: ${((successful / count) * 100).toFixed(1)}%\n`;
                resultMessage += `Average Time: ${(duration / count).toFixed(2)}ms per operation\n`;
                
                if (failed > 0) {
                    resultMessage += `\n⚠️ Some operations failed - check for race conditions or database issues`;
                } else {
                    resultMessage += `\n✅ All operations completed successfully`;
                }
                
                updateResults('concurrency-results', resultMessage);
                logMessage(`Concurrency test (${type}): ${successful}/${count} operations successful in ${duration}ms`);
            })
            .catch(error => {
                showError('concurrency-results', `Concurrency test failed: ${error.message}`);
            })
            .finally(() => {
                document.getElementById('concurrency-status').style.display = 'none';
            });
    }
    
    // API Testing functions
    function testGlobalFunctions() {
        if (!currentUserId) {
            showError('api-results', 'Please select a test user first');
            return;
        }
        
        showInfo('api-results', 'Testing global helper functions...');
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_test_api&test_type=global_functions&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAPIResults('api-results', 'Global Functions Test Results', data.data);
            } else {
                showError('api-results', data.data || 'API test failed');
            }
        })
        .catch(error => {
            showError('api-results', `Network error: ${error.message}`);
        });
    }
    
    function testClassMethods() {
        if (!currentUserId) {
            showError('api-results', 'Please select a test user first');
            return;
        }
        
        showInfo('api-results', 'Testing class methods...');
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_test_api&test_type=class_methods&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAPIResults('api-results', 'Class Methods Test Results', data.data);
            } else {
                showError('api-results', data.data || 'API test failed');
            }
        })
        .catch(error => {
            showError('api-results', `Network error: ${error.message}`);
        });
    }
    
    function testErrorHandling() {
        if (!currentUserId) {
            showError('api-results', 'Please select a test user first');
            return;
        }
        
        showInfo('api-results', 'Testing error handling...');
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_test_api&test_type=error_handling&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAPIResults('api-results', 'Error Handling Test Results', data.data);
            } else {
                showError('api-results', data.data || 'API test failed');
            }
        })
        .catch(error => {
            showError('api-results', `Network error: ${error.message}`);
        });
    }
    
    function runAllAPITests() {
        if (!currentUserId) {
            showError('api-results', 'Please select a test user first');
            return;
        }
        
        showInfo('api-results', 'Running comprehensive API tests...');
        
        const tests = ['global_functions', 'class_methods', 'error_handling'];
        let completedTests = 0;
        let allResults = {};
        
        tests.forEach(testType => {
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=slimwp_debug_test_api&test_type=${testType}&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                completedTests++;
                if (data.success) {
                    allResults[testType] = data.data;
                } else {
                    allResults[testType] = { error: data.data };
                }
                
                if (completedTests === tests.length) {
                    displayAllAPIResults('api-results', allResults);
                }
            })
            .catch(error => {
                completedTests++;
                allResults[testType] = { error: error.message };
                
                if (completedTests === tests.length) {
                    displayAllAPIResults('api-results', allResults);
                }
            });
        });
    }
    
    // Performance testing functions
    function testPerformanceAdd() {
        if (!currentUserId) {
            showError('performance-results', 'Please select a test user first');
            return;
        }
        
        const iterations = document.getElementById('perf-iterations').value;
        showInfo('performance-results', `Running add performance test with ${iterations} iterations...`);
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_performance&test_type=add&iterations=${iterations}&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPerformanceResults('performance-results', data.data);
                refreshBalance(); // Update balance after performance test
            } else {
                showError('performance-results', data.data || 'Performance test failed');
            }
        })
        .catch(error => {
            showError('performance-results', `Network error: ${error.message}`);
        });
    }
    
    function testPerformanceSubtract() {
        if (!currentUserId) {
            showError('performance-results', 'Please select a test user first');
            return;
        }
        
        const iterations = document.getElementById('perf-iterations').value;
        showInfo('performance-results', `Running subtract performance test with ${iterations} iterations...`);
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_performance&test_type=subtract&iterations=${iterations}&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPerformanceResults('performance-results', data.data);
                refreshBalance(); // Update balance after performance test
            } else {
                showError('performance-results', data.data || 'Performance test failed');
            }
        })
        .catch(error => {
            showError('performance-results', `Network error: ${error.message}`);
        });
    }
    
    function testPerformanceRead() {
        if (!currentUserId) {
            showError('performance-results', 'Please select a test user first');
            return;
        }
        
        const iterations = document.getElementById('perf-iterations').value;
        showInfo('performance-results', `Running read performance test with ${iterations} iterations...`);
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=slimwp_debug_performance&test_type=read&iterations=${iterations}&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayPerformanceResults('performance-results', data.data);
            } else {
                showError('performance-results', data.data || 'Performance test failed');
            }
        })
        .catch(error => {
            showError('performance-results', `Network error: ${error.message}`);
        });
    }
    
    function runAllPerformanceTests() {
        if (!currentUserId) {
            showError('performance-results', 'Please select a test user first');
            return;
        }
        
        const iterations = document.getElementById('perf-iterations').value;
        showInfo('performance-results', `Running all performance tests with ${iterations} iterations each...`);
        
        const tests = ['add', 'subtract', 'read'];
        let completedTests = 0;
        let allResults = {};
        
        tests.forEach(testType => {
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=slimwp_debug_performance&test_type=${testType}&iterations=${iterations}&user_id=${currentUserId}&nonce=<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>`
            })
            .then(response => response.json())
            .then(data => {
                completedTests++;
                if (data.success) {
                    allResults[testType] = data.data;
                } else {
                    allResults[testType] = { error: data.data };
                }
                
                if (completedTests === tests.length) {
                    displayAllPerformanceResults('performance-results', allResults);
                    refreshBalance(); // Update balance after all tests
                }
            })
            .catch(error => {
                completedTests++;
                allResults[testType] = { error: error.message };
                
                if (completedTests === tests.length) {
                    displayAllPerformanceResults('performance-results', allResults);
                }
            });
        });
    }
    
    // Display helper functions
    function displayAPIResults(elementId, title, results) {
        let output = `✅ ${title}\n`;
        output += `${'='.repeat(title.length + 2)}\n\n`;
        
        Object.keys(results).forEach(key => {
            if (key === 'execution_time') {
                output += `⏱️ Total Execution Time: ${results[key]}\n`;
            } else if (key === 'exception') {
                output += `❌ Exception: ${results[key]}\n`;
            } else {
                const status = results[key] === 'PASS' ? '✅' : '❌';
                output += `${status} ${key}: ${results[key]}\n`;
            }
        });
        
        updateResults(elementId, output);
        logMessage(`API Test (${title}): Completed`);
    }
    
    function displayAllAPIResults(elementId, allResults) {
        let output = '🚀 COMPREHENSIVE API TEST RESULTS\n';
        output += '=====================================\n\n';
        
        let totalPassed = 0;
        let totalTests = 0;
        
        Object.keys(allResults).forEach(testType => {
            const results = allResults[testType];
            output += `📋 ${testType.replace('_', ' ').toUpperCase()}\n`;
            output += `${'-'.repeat(testType.length + 2)}\n`;
            
            if (results.error) {
                output += `❌ Error: ${results.error}\n\n`;
            } else {
                Object.keys(results).forEach(key => {
                    if (key === 'execution_time') {
                        output += `⏱️ Execution Time: ${results[key]}\n`;
                    } else if (key === 'exception') {
                        output += `❌ Exception: ${results[key]}\n`;
                    } else {
                        totalTests++;
                        if (results[key] === 'PASS') {
                            totalPassed++;
                            output += `✅ ${key}: PASS\n`;
                        } else {
                            output += `❌ ${key}: FAIL\n`;
                        }
                    }
                });
            }
            output += '\n';
        });
        
        output += `📊 SUMMARY\n`;
        output += `----------\n`;
        output += `Total Tests: ${totalTests}\n`;
        output += `Passed: ${totalPassed}\n`;
        output += `Failed: ${totalTests - totalPassed}\n`;
        output += `Success Rate: ${totalTests > 0 ? ((totalPassed / totalTests) * 100).toFixed(1) : 0}%\n`;
        
        updateResults(elementId, output);
        logMessage(`API Tests Complete: ${totalPassed}/${totalTests} passed`);
    }
    
    function displayPerformanceResults(elementId, results) {
        let output = `📈 PERFORMANCE TEST RESULTS\n`;
        output += `===========================\n\n`;
        output += `Operation: ${results.operation}\n`;
        output += `Iterations: ${results.iterations}\n`;
        output += `Successful: ${results.successful}\n`;
        output += `Total Time: ${results.total_time}ms\n`;
        output += `Average Time: ${results.average_time}ms per operation\n`;
        output += `Min Time: ${results.min_time}ms\n`;
        output += `Max Time: ${results.max_time}ms\n`;
        output += `Operations/Second: ${results.operations_per_second}\n`;
        
        if (results.successful < results.iterations) {
            output += `\n⚠️ ${results.iterations - results.successful} operations failed\n`;
        }
        
        updateResults(elementId, output);
        logMessage(`Performance Test (${results.operation}): ${results.operations_per_second} ops/sec`);
    }
    
    function displayAllPerformanceResults(elementId, allResults) {
        let output = '🏃 COMPREHENSIVE PERFORMANCE TEST RESULTS\n';
        output += '==========================================\n\n';
        
        Object.keys(allResults).forEach(testType => {
            const results = allResults[testType];
            output += `📊 ${testType.toUpperCase()} OPERATIONS\n`;
            output += `${'-'.repeat(testType.length + 12)}\n`;
            
            if (results.error) {
                output += `❌ Error: ${results.error}\n\n`;
            } else {
                output += `Iterations: ${results.iterations}\n`;
                output += `Successful: ${results.successful}\n`;
                output += `Total Time: ${results.total_time}ms\n`;
                output += `Average Time: ${results.average_time}ms\n`;
                output += `Operations/Second: ${results.operations_per_second}\n\n`;
            }
        });
        
        updateResults(elementId, output);
        logMessage('All performance tests completed');
    }
    
    // Utility functions
    function clearAllLogs() {
        testLog = [];
        document.getElementById('complete-log').textContent = 'Logs cleared.';
    }
    
    function exportTestResults() {
        const results = testLog.join('\n');
        const blob = new Blob([results], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `slimwp-points-test-results-${new Date().toISOString().slice(0, 19).replace(/[:.]/g, '-')}.txt`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }
    
    // AJAX connection test function
    function testAjaxConnection() {
        updateResults('ajax-test-results', 'Testing AJAX connection...', true);
        
        const testData = {
            action: 'slimwp_debug_get_balance',
            user_id: 1, // Test with user ID 1
            nonce: '<?php echo wp_create_nonce('slimwp_debug_nonce'); ?>'
        };
        
        console.log('Sending AJAX request:', testData);
        console.log('AJAX URL:', ajaxUrl);
        
        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(testData).toString()
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            return response.text().then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    console.log('Parsed JSON:', data);
                    return data;
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Invalid JSON response. Raw response: ' + text.substring(0, 500));
                }
            });
        })
        .then(data => {
            if (data.success) {
                updateResults('ajax-test-results', '✅ AJAX Connection Successful!\nReceived data: ' + JSON.stringify(data.data, null, 2));
                logMessage('AJAX connection test: SUCCESS');
            } else {
                updateResults('ajax-test-results', '❌ AJAX Request Failed: ' + (data.data || 'Unknown error'));
                logMessage('AJAX connection test: FAILED - ' + (data.data || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('AJAX test error:', error);
            updateResults('ajax-test-results', '❌ AJAX Connection Error: ' + error.message);
            logMessage('AJAX connection test: ERROR - ' + error.message);
        });
    }
    
    // Initialize
    logMessage('SlimWP Points Testing Debug Tool initialized');
    </script>
</body>
</html>