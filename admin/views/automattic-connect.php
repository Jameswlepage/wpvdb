<div class="wpvdb-automattic-connect">
    <h1><?php esc_html_e('Connect to Automattic AI', 'wpvdb'); ?></h1>
    
    <div class="wpvdb-connect-container">
        <div class="wpvdb-connect-card">
            <div class="wpvdb-connect-header">
                <img src="<?php echo esc_url(WPVDB_PLUGIN_URL . 'assets/images/automattic-logo.svg'); ?>" alt="Automattic" onerror="this.src='<?php echo esc_url(admin_url('images/wordpress-logo.svg')); ?>'; this.style.width='64px';" />
                <h2><?php esc_html_e('Connect to Automattic AI', 'wpvdb'); ?></h2>
            </div>
            
            <p class="wpvdb-connect-description">
                <?php esc_html_e('Automattic AI provides powerful embedding capabilities for your content. Connect your account to use these features.', 'wpvdb'); ?>
            </p>
            
            <div class="wpvdb-connect-methods">
                <div class="wpvdb-connect-method wpvdb-connect-one-click">
                    <h3><?php esc_html_e('One-Click Connection', 'wpvdb'); ?></h3>
                    <p><?php esc_html_e('Connect your Automattic account with a single click. This is the easiest method.', 'wpvdb'); ?></p>
                    <button id="wpvdb-automattic-one-click" class="button button-primary button-hero">
                        <?php esc_html_e('Connect with Automattic', 'wpvdb'); ?>
                    </button>
                    <div id="wpvdb-connection-status" class="wpvdb-connection-status" style="display: none;">
                        <span class="spinner is-active"></span>
                        <span class="status-text"><?php esc_html_e('Connecting...', 'wpvdb'); ?></span>
                    </div>
                </div>
                
                <div class="wpvdb-connect-divider">
                    <span><?php esc_html_e('OR', 'wpvdb'); ?></span>
                </div>
                
                <div class="wpvdb-connect-method wpvdb-connect-manual">
                    <h3><?php esc_html_e('Manual API Key', 'wpvdb'); ?></h3>
                    <p><?php esc_html_e('If you already have an Automattic AI API key, you can enter it directly.', 'wpvdb'); ?></p>
                    
                    <form id="wpvdb-manual-connect-form" method="post" action="options.php">
                        <?php settings_fields('wpvdb_settings'); ?>
                        <input type="hidden" name="wpvdb_settings[provider]" value="automattic">
                        <input type="hidden" name="wpvdb_settings[automattic][default_model]" value="automattic-embeddings-001">
                        
                        <div class="wpvdb-form-group">
                            <label for="wpvdb_automattic_api_key"><?php esc_html_e('API Key', 'wpvdb'); ?></label>
                            <input type="password" 
                                id="wpvdb_automattic_api_key" 
                                name="wpvdb_settings[automattic][api_key]" 
                                value="<?php echo esc_attr($settings['automattic']['api_key'] ?? ''); ?>" 
                                class="regular-text"
                                required>
                            <p class="description">
                                <?php esc_html_e('Enter your Automattic AI API key', 'wpvdb'); ?>
                            </p>
                        </div>
                        
                        <div class="wpvdb-form-actions">
                            <button type="submit" class="button button-primary">
                                <?php esc_html_e('Connect', 'wpvdb'); ?>
                            </button>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-settings')); ?>" class="button">
                                <?php esc_html_e('Cancel', 'wpvdb'); ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="wpvdb-connect-footer">
                <p>
                    <?php esc_html_e('Don\'t have an Automattic AI account?', 'wpvdb'); ?>
                    <a href="https://automattic.com/ai" target="_blank"><?php esc_html_e('Sign up here', 'wpvdb'); ?></a>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.wpvdb-automattic-connect {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.wpvdb-connect-container {
    max-width: 800px;
    margin: 30px auto;
}

.wpvdb-connect-card {
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 30px;
}

.wpvdb-connect-header {
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #eee;
    padding-bottom: 20px;
}

.wpvdb-connect-header img {
    width: 50px;
    height: auto;
    margin-right: 15px;
}

.wpvdb-connect-header h2 {
    margin: 0;
    font-size: 24px;
    color: #1e1e1e;
}

.wpvdb-connect-description {
    font-size: 16px;
    color: #50575e;
    margin-bottom: 30px;
}

.wpvdb-connect-methods {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.wpvdb-connect-method {
    padding: 25px;
    border-radius: 4px;
    background: #f9f9f9;
}

.wpvdb-connect-one-click {
    background: #f0f6fc;
    border-left: 4px solid #2271b1;
}

.wpvdb-connect-manual {
    background: #f8f8f8;
    border-left: 4px solid #ddd;
}

.wpvdb-connect-method h3 {
    margin-top: 0;
    color: #1e1e1e;
}

.wpvdb-connect-divider {
    position: relative;
    text-align: center;
    margin: 0;
}

.wpvdb-connect-divider:before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #ddd;
    z-index: 1;
}

.wpvdb-connect-divider span {
    position: relative;
    background: #f0f0f1;
    padding: 0 15px;
    font-size: 14px;
    color: #757575;
    z-index: 2;
}

.wpvdb-form-group {
    margin-bottom: 20px;
}

.wpvdb-form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
}

.wpvdb-form-group input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.wpvdb-form-actions {
    margin-top: 20px;
}

.wpvdb-form-actions .button {
    margin-right: 10px;
}

.wpvdb-connect-footer {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    color: #757575;
    text-align: center;
}

.wpvdb-connection-status {
    margin-top: 15px;
    padding: 10px;
    background: #f8f8f8;
    border-radius: 4px;
    display: flex;
    align-items: center;
}

.wpvdb-connection-status .spinner {
    float: none;
    margin-right: 10px;
}

.wpvdb-connection-status.success {
    background-color: #ecf8ed;
    color: #0a5f0a;
}

.wpvdb-connection-status.error {
    background-color: #fcebec;
    color: #a00;
}
</style>

<script>
jQuery(document).ready(function($) {
    // One-click connection flow
    $('#wpvdb-automattic-one-click').on('click', function() {
        var $button = $(this);
        var $status = $('#wpvdb-connection-status');
        
        // Disable button and show connecting status
        $button.prop('disabled', true);
        $status.show();
        
        // Simulate API connection (replace with actual API connection)
        setTimeout(function() {
            // Mock successful connection
            var success = Math.random() > 0.2; // 80% success rate for demo
            
            if (success) {
                // Update status to success
                $status.removeClass('error').addClass('success');
                $status.find('.status-text').text('<?php esc_attr_e('Connected successfully!', 'wpvdb'); ?>');
                
                // Save the settings
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'wpvdb_automattic_connect',
                        nonce: wpvdb.nonce,
                        connect_method: 'one_click'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Redirect to settings page after a delay
                            setTimeout(function() {
                                window.location.href = '<?php echo esc_url(admin_url('admin.php?page=wpvdb-settings&automattic_connected=1')); ?>';
                            }, 1500);
                        }
                    }
                });
            } else {
                // Update status to error
                $status.removeClass('success').addClass('error');
                $status.find('.status-text').text('<?php esc_attr_e('Connection failed. Please try again or use manual connection.', 'wpvdb'); ?>');
                
                // Re-enable button
                $button.prop('disabled', false);
            }
        }, 2000);
    });
});
</script> 