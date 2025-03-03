<div class="wrap wpvdb-dashboard">
    <h1><?php esc_html_e('Vector Database Dashboard', 'wpvdb'); ?></h1>
    
    <div class="wpvdb-stats-cards">
        <div class="wpvdb-card">
            <h2><?php esc_html_e('Total Embeddings', 'wpvdb'); ?></h2>
            <div class="wpvdb-stat"><?php echo esc_html(number_format_i18n($total_embeddings)); ?></div>
        </div>
        
        <div class="wpvdb-card">
            <h2><?php esc_html_e('Total Documents', 'wpvdb'); ?></h2>
            <div class="wpvdb-stat"><?php echo esc_html(number_format_i18n($total_docs)); ?></div>
        </div>
        
        <div class="wpvdb-card">
            <h2><?php esc_html_e('Storage Used', 'wpvdb'); ?></h2>
            <div class="wpvdb-stat"><?php echo esc_html($storage_used); ?></div>
        </div>
    </div>
    
    <div class="wpvdb-actions">
        <h2><?php esc_html_e('Quick Actions', 'wpvdb'); ?></h2>
        
        <div class="wpvdb-action-buttons">
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-embeddings')); ?>" class="button button-primary">
                <?php esc_html_e('Manage Embeddings', 'wpvdb'); ?>
            </a>
            
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-settings')); ?>" class="button">
                <?php esc_html_e('Configure Settings', 'wpvdb'); ?>
            </a>
            
            <button id="wpvdb-bulk-embed" class="button">
                <?php esc_html_e('Bulk Embed Content', 'wpvdb'); ?>
            </button>
        </div>
    </div>
    
    <div id="wpvdb-bulk-embed-modal" class="wpvdb-modal" style="display:none;">
        <div class="wpvdb-modal-content">
            <span class="wpvdb-modal-close">&times;</span>
            <h2><?php esc_html_e('Bulk Embed Content', 'wpvdb'); ?></h2>
            
            <form id="wpvdb-bulk-embed-form">
                <div class="wpvdb-form-group">
                    <label for="wpvdb-post-type"><?php esc_html_e('Post Type', 'wpvdb'); ?></label>
                    <select id="wpvdb-post-type" name="post_type">
                        <?php 
                        $post_types = get_post_types(['public' => true], 'objects');
                        foreach ($post_types as $pt) {
                            echo '<option value="' . esc_attr($pt->name) . '">' . esc_html($pt->label) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                
                <div class="wpvdb-form-group">
                    <label for="wpvdb-limit"><?php esc_html_e('Limit', 'wpvdb'); ?></label>
                    <input type="number" id="wpvdb-limit" name="limit" min="1" max="100" value="10">
                </div>
                
                <div class="wpvdb-form-group">
                    <label for="wpvdb-model"><?php esc_html_e('Embedding Model', 'wpvdb'); ?></label>
                    <select id="wpvdb-model" name="model">
                        <option value="text-embedding-3-small">text-embedding-3-small</option>
                        <option value="text-embedding-3-large">text-embedding-3-large</option>
                        <option value="text-embedding-ada-002">text-embedding-ada-002 (Legacy)</option>
                    </select>
                </div>
                
                <div class="wpvdb-form-actions">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Start Processing', 'wpvdb'); ?></button>
                    <button type="button" class="button wpvdb-modal-cancel"><?php esc_html_e('Cancel', 'wpvdb'); ?></button>
                </div>
            </form>
            
            <div id="wpvdb-bulk-embed-results" style="display:none;">
                <div class="wpvdb-progress">
                    <div class="wpvdb-progress-bar" style="width: 0%;"></div>
                </div>
                <p class="wpvdb-status-message"></p>
            </div>
        </div>
    </div>
</div> 