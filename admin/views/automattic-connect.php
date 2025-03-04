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
                    <p><?php esc_html_e('Connect with a single click to automatically set up your Automattic AI API key.', 'wpvdb'); ?></p>
                    
                    <button id="wpvdb-one-click-connect" class="button button-primary">
                        <?php esc_html_e('Connect to Automattic AI', 'wpvdb'); ?>
                    </button>
                    
                    <div id="wpvdb-connection-status" class="hidden">
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
    margin: 40px auto;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.wpvdb-connect-card {
    background: #fff;
    border-radius: 4px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    padding: 30px;
}

.wpvdb-connect-header {
    background: #f9f9f9;
    padding: 20px;
    border-bottom: 1px solid #eee;
    text-align: center;
}

.wpvdb-connect-header img {
    width: 50px;
    height: auto;
    margin-bottom: 15px;
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
    gap: 30px;
    margin-top: 20px;
}

.wpvdb-connect-method {
    flex: 1;
    background: #f9f9f9;
    border-radius: 6px;
    padding: 20px;
    border: 1px solid #eee;
}

.wpvdb-connect-method h3 {
    margin-top: 0;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
}

.wpvdb-connect-method p {
    margin-bottom: 20px;
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
    margin-bottom: 5px;
    font-weight: 600;
}

.wpvdb-form-group input[type="text"],
.wpvdb-form-group input[type="password"] {
    width: 100%;
    padding: 8px;
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

#wpvdb-connection-status {
    margin-top: 15px;
    padding: 10px;
    border-radius: 4px;
    display: none;
}

#wpvdb-connection-status.hidden {
    display: none;
}

#wpvdb-connection-status.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

#wpvdb-connection-status.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style> 