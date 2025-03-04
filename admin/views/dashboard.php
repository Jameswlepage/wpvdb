<div class="wrap wpvdb-dashboard">
    <h1><?php esc_html_e('Vector Database Dashboard', 'wpvdb'); ?></h1>
    
    <div class="postbox-container" style="width: 100%;">
        <div class="metabox-holder">
            <!-- Stats Overview -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e('Database Overview', 'wpvdb'); ?></h2>
                </div>
                <div class="inside">
                    <div class="wpvdb-stats-grid">
                        <div class="wpvdb-stat-item">
                            <h3><?php esc_html_e('Total Embeddings', 'wpvdb'); ?></h3>
                            <div class="wpvdb-stat-value"><?php echo esc_html(number_format_i18n($total_embeddings)); ?></div>
                        </div>
                        
                        <div class="wpvdb-stat-item">
                            <h3><?php esc_html_e('Total Documents', 'wpvdb'); ?></h3>
                            <div class="wpvdb-stat-value"><?php echo esc_html(number_format_i18n($total_docs)); ?></div>
                        </div>
                        
                        <div class="wpvdb-stat-item">
                            <h3><?php esc_html_e('Storage Used', 'wpvdb'); ?></h3>
                            <div class="wpvdb-stat-value"><?php echo esc_html($storage_used); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Search Widget -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e('Semantic Search', 'wpvdb'); ?></h2>
                </div>
                <div class="inside">
                    <p><?php esc_html_e('Search your content using AI-powered semantic search:', 'wpvdb'); ?></p>
                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                        <input type="hidden" name="page" value="wpvdb-embeddings">
                        <div class="wpvdb-search-form">
                            <input type="search" 
                                   name="s" 
                                   placeholder="<?php esc_attr_e('Enter your search query...', 'wpvdb'); ?>"
                                   class="regular-text">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Search', 'wpvdb'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Quick Actions Widget -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle"><?php esc_html_e('Quick Actions', 'wpvdb'); ?></h2>
                </div>
                <div class="inside">
                    <div class="wpvdb-action-buttons">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-embeddings')); ?>" class="button">
                            <span class="dashicons dashicons-database-view"></span>
                            <?php esc_html_e('Manage Embeddings', 'wpvdb'); ?>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-settings')); ?>" class="button">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php esc_html_e('Configure Settings', 'wpvdb'); ?>
                        </a>
                        
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-embeddings#bulk-embed')); ?>" class="button">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Bulk Embed Content', 'wpvdb'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* WordPress Core-like styling */
.wpvdb-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin: 10px 0;
}

.wpvdb-stat-item {
    text-align: center;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
}

.wpvdb-stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #2271b1;
    margin: 10px 0;
}

.wpvdb-stat-item h3 {
    margin: 0;
    font-size: 14px;
    color: #50575e;
}

.wpvdb-search-form {
    display: flex;
    margin: 10px 0;
}

.wpvdb-search-form input[type="search"] {
    flex: 1;
    margin-right: 10px;
}

.wpvdb-action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.wpvdb-action-buttons .button {
    display: flex;
    align-items: center;
}

.wpvdb-action-buttons .button .dashicons {
    margin-right: 5px;
}
</style> 