<div class="wrap wpvdb-settings">
    <?php if ($has_pending_change): ?>
    <div class="notice notice-warning inline">
        <p>
            <strong><?php esc_html_e('Provider Change Pending', 'wpvdb'); ?></strong>
        </p>
        <p>
            <?php esc_html_e('You have requested to change the embedding provider or model. This change requires re-indexing all content before it takes effect.', 'wpvdb'); ?>
        </p>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-status')); ?>" class="button button-primary">
                <?php esc_html_e('Go to Status Page', 'wpvdb'); ?>
            </a>
        </p>
    </div>
    <?php endif; ?>

    <h1><?php esc_html_e('Vector Database Settings', 'wpvdb'); ?></h1>
    
    <form method="post" action="options.php" id="wpvdb-settings-form">
        <?php
        settings_fields('wpvdb_settings');
        do_settings_sections('wpvdb_settings');
        ?>
        
        <div class="wpvdb-settings-section">
            <h2><?php esc_html_e('AI Provider', 'wpvdb'); ?></h2>
            <p class="description"><?php esc_html_e('Select which AI provider to use for embedding generation.', 'wpvdb'); ?></p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Provider', 'wpvdb'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="wpvdb_settings[provider]" value="openai" <?php checked($provider, 'openai'); ?>>
                                <?php esc_html_e('OpenAI', 'wpvdb'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="wpvdb_settings[provider]" value="automattic" <?php checked($provider, 'automattic'); ?>>
                                <?php esc_html_e('Automattic AI', 'wpvdb'); ?>
                            </label>
                        </fieldset>
                        
                        <?php if (!empty($settings['active_provider'])): ?>
                        <p class="description provider-info">
                            <?php 
                            $active_provider = $settings['active_provider'] === 'openai' ? 'OpenAI' : 'Automattic AI';
                            $active_model = $settings['active_model'];
                            printf(
                                __('Active provider: <strong>%1$s</strong> / <code>%2$s</code>', 'wpvdb'),
                                esc_html($active_provider),
                                esc_html($active_model)
                            );
                            ?>
                        </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php
        // Define available sections
        $sections = [
            'provider' => __('Provider Settings', 'wpvdb'),
            'content' => __('Content Settings', 'wpvdb'),
            'auto-embed' => __('Auto-Embedding', 'wpvdb'),
        ];
        
        // Default to first section if none specified
        if (empty($section)) {
            $section = 'provider';
        }
        
        // Generate the section navigation
        echo '<div class="wpvdb-section-nav">';
        $i = 0;
        foreach ($sections as $section_id => $section_label) {
            if ($i > 0) {
                echo '<span class="divider">|</span>';
            }
            
            $url = admin_url(sprintf(
                'admin.php?page=wpvdb-settings&section=%s',
                esc_attr($section_id)
            ));
            
            $class = ($section === $section_id) ? 'current' : '';
            printf(
                '<a href="%s" class="%s">%s</a>',
                esc_url($url),
                esc_attr($class),
                esc_html($section_label)
            );
            
            $i++;
        }
        echo '</div>';
        ?>
        
        <div class="wpvdb-tab-content">
            <?php if ($section === 'provider'): ?>
                <!-- Provider Settings Section -->
                <div class="provider-panel" id="openai-panel" <?php echo $provider !== 'openai' ? 'style="display:none;"' : ''; ?>>
                    <h3><?php esc_html_e('OpenAI Settings', 'wpvdb'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="wpvdb_openai_api_key"><?php esc_html_e('API Key', 'wpvdb'); ?></label>
                            </th>
                            <td>
                                <?php if (defined('WPVDB_OPENAI_API_KEY')): ?>
                                    <p>
                                        <span class="dashicons dashicons-yes-alt" style="color:green;"></span>
                                        <strong><?php esc_html_e('API key is defined in wp-config.php as WPVDB_OPENAI_API_KEY', 'wpvdb'); ?></strong>
                                    </p>
                                    <p class="description">
                                        <?php esc_html_e('The constant definition will override any value entered here.', 'wpvdb'); ?>
                                    </p>
                                    <input type="hidden" 
                                        name="wpvdb_settings[openai][api_key]" 
                                        value="<?php echo esc_attr($settings['openai']['api_key'] ?? ''); ?>">
                                <?php else: ?>
                                    <input type="password" 
                                        id="wpvdb_openai_api_key" 
                                        name="wpvdb_settings[openai][api_key]" 
                                        value="<?php echo esc_attr($settings['openai']['api_key'] ?? ''); ?>" 
                                        class="regular-text">
                                    <p class="description">
                                        <?php esc_html_e('Enter your OpenAI API key to enable embedding generation', 'wpvdb'); ?>
                                    </p>
                                    <p class="description">
                                        <?php esc_html_e('For more security, you can define this in wp-config.php instead:', 'wpvdb'); ?>
                                        <code>define('WPVDB_OPENAI_API_KEY', 'your-api-key-here');</code>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wpvdb_openai_model"><?php esc_html_e('Default Embedding Model', 'wpvdb'); ?></label>
                            </th>
                            <td>
                                <select id="wpvdb_openai_model" name="wpvdb_settings[openai][default_model]">
                                    <?php 
                                    $default_model = $settings['openai']['default_model'] ?? 'text-embedding-3-small';
                                    $models = [
                                        'text-embedding-3-small' => 'text-embedding-3-small (1536 dimensions)',
                                        'text-embedding-3-large' => 'text-embedding-3-large (3072 dimensions)',
                                        'text-embedding-ada-002' => 'text-embedding-ada-002 (Legacy)'
                                    ];
                                    
                                    foreach ($models as $model_id => $model_name) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($model_id),
                                            selected($default_model, $model_id, false),
                                            esc_html($model_name)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="provider-panel" id="automattic-panel" <?php echo $provider !== 'automattic' ? 'style="display:none;"' : ''; ?>>
                    <h3><?php esc_html_e('Automattic AI Settings', 'wpvdb'); ?></h3>
                    
                    <?php if (!empty($settings['automattic']['api_key'])): ?>
                    <div class="wpvdb-connection-info">
                        <div class="connection-status connected">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Connected to Automattic AI', 'wpvdb'); ?>
                        </div>
                        <div class="connection-actions">
                            <button type="button" id="wpvdb-disconnect-automattic" class="button">
                                <?php esc_html_e('Disconnect', 'wpvdb'); ?>
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="wpvdb-connection-info">
                        <div class="connection-status disconnected">
                            <span class="dashicons dashicons-no"></span>
                            <?php esc_html_e('Not connected', 'wpvdb'); ?>
                        </div>
                        <div class="connection-actions">
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-automattic-connect')); ?>" class="button button-primary">
                                <?php esc_html_e('Connect to Automattic AI', 'wpvdb'); ?>
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="wpvdb_automattic_api_key"><?php esc_html_e('API Key (Hidden)', 'wpvdb'); ?></label>
                            </th>
                            <td>
                                <?php if (defined('WPVDB_AUTOMATTIC_API_KEY')): ?>
                                    <p>
                                        <span class="dashicons dashicons-yes-alt" style="color:green;"></span>
                                        <strong><?php esc_html_e('API key is defined in wp-config.php as WPVDB_AUTOMATTIC_API_KEY', 'wpvdb'); ?></strong>
                                    </p>
                                    <p class="description">
                                        <?php esc_html_e('The constant definition will override any value entered here.', 'wpvdb'); ?>
                                    </p>
                                    <input type="hidden" 
                                        name="wpvdb_settings[automattic][api_key]" 
                                        value="<?php echo esc_attr($settings['automattic']['api_key'] ?? ''); ?>">
                                <?php else: ?>
                                    <input type="hidden" 
                                        id="wpvdb_automattic_api_key" 
                                        name="wpvdb_settings[automattic][api_key]" 
                                        value="<?php echo esc_attr($settings['automattic']['api_key'] ?? ''); ?>">
                                    <p class="description">
                                        <?php esc_html_e('API key is securely stored', 'wpvdb'); ?>
                                    </p>
                                    <p class="description">
                                        <?php esc_html_e('For more security, you can define this in wp-config.php instead:', 'wpvdb'); ?>
                                        <code>define('WPVDB_AUTOMATTIC_API_KEY', 'your-api-key-here');</code>
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wpvdb_automattic_model"><?php esc_html_e('Default Embedding Model', 'wpvdb'); ?></label>
                            </th>
                            <td>
                                <select id="wpvdb_automattic_model" name="wpvdb_settings[automattic][default_model]">
                                    <?php 
                                    $default_model = $settings['automattic']['default_model'] ?? 'automattic-embeddings-001';
                                    $models = [
                                        'automattic-embeddings-001' => 'automattic-embeddings-001'
                                    ];
                                    
                                    foreach ($models as $model_id => $model_name) {
                                        printf(
                                            '<option value="%s" %s>%s</option>',
                                            esc_attr($model_id),
                                            selected($default_model, $model_id, false),
                                            esc_html($model_name)
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            <?php elseif ($section === 'content'): ?>
                <!-- Content Settings Section -->
                <h3><?php esc_html_e('Content Processing Settings', 'wpvdb'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wpvdb_chunk_size"><?php esc_html_e('Chunk Size (words)', 'wpvdb'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                id="wpvdb_chunk_size" 
                                name="wpvdb_settings[chunk_size]" 
                                value="<?php echo esc_attr($settings['chunk_size'] ?? 1000); ?>" 
                                class="small-text">
                            <p class="description">
                                <?php esc_html_e('Maximum number of words per content chunk. Recommended: 1000 for most embedding models.', 'wpvdb'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="wpvdb_chunk_overlap"><?php esc_html_e('Chunk Overlap (words)', 'wpvdb'); ?></label>
                        </th>
                        <td>
                            <input type="number" 
                                id="wpvdb_chunk_overlap" 
                                name="wpvdb_settings[chunk_overlap]" 
                                value="<?php echo esc_attr($settings['chunk_overlap'] ?? 200); ?>" 
                                class="small-text">
                            <p class="description">
                                <?php esc_html_e('Number of words to overlap between chunks. Recommended: 200 (20% of chunk size).', 'wpvdb'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            <?php elseif ($section === 'auto-embed'): ?>
                <!-- Auto-Embedding Section -->
                <h3><?php esc_html_e('Auto-Embedding', 'wpvdb'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Enable Auto-Embedding', 'wpvdb'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php esc_html_e('Enable Auto-Embedding', 'wpvdb'); ?></span>
                                </legend>
                                <label for="wpvdb_auto_embed">
                                    <input type="checkbox" 
                                        id="wpvdb_auto_embed" 
                                        name="wpvdb_settings[auto_embed]" 
                                        value="1" 
                                        <?php checked($settings['auto_embed'] ?? false); ?>>
                                    <?php esc_html_e('Automatically generate embeddings when content is published or updated', 'wpvdb'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Post Types', 'wpvdb'); ?></th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">
                                    <span><?php esc_html_e('Post Types', 'wpvdb'); ?></span>
                                </legend>
                                <?php 
                                $enabled_post_types = $settings['post_types'] ?? ['post', 'page'];
                                $post_types = get_post_types(['public' => true], 'objects');
                                
                                foreach ($post_types as $post_type) {
                                    printf(
                                        '<label><input type="checkbox" name="wpvdb_settings[post_types][]" value="%s" %s> %s</label><br>',
                                        esc_attr($post_type->name),
                                        in_array($post_type->name, $enabled_post_types) ? 'checked' : '',
                                        esc_html($post_type->label)
                                    );
                                }
                                ?>
                            </fieldset>
                            <p class="description">
                                <?php esc_html_e('Select which post types should be automatically embedded', 'wpvdb'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<style>
/* WooCommerce-like admin styling */
.wpvdb-settings-tabs {
    margin-top: 20px;
}

.wpvdb-tabs {
    display: flex;
    margin: 0;
    padding: 0;
    border-bottom: 1px solid #ccc;
    background: #f7f7f7;
}

.wpvdb-tabs li {
    margin: 0;
    padding: 0;
    list-style: none;
}

.wpvdb-tabs li a {
    display: block;
    padding: 10px 15px;
    text-decoration: none;
    color: #0073aa;
    font-weight: 500;
    border-bottom: 3px solid transparent;
}

.wpvdb-tabs li.active a {
    background: #fff;
    border-bottom-color: #0073aa;
    color: #000;
}

.wpvdb-tab-content {
    background: #fff;
    border: 1px solid #ccc;
    border-top: none;
    padding: 20px;
}

.wpvdb-tab-pane {
    display: none;
}

.wpvdb-tab-pane.active {
    display: block;
}

/* Provider radio buttons styling */
.wpvdb-settings [type="radio"] {
    margin-right: 8px;
}

.wpvdb-settings label {
    margin-right: 15px;
    vertical-align: middle;
}

.provider-info {
    margin-top: 10px !important;
    padding: 5px 10px;
    background: #f0f6fc;
    border-left: 4px solid #0073aa;
}

/* Connection status styling */
.wpvdb-connection-info {
    margin-bottom: 15px;
}

.connection-status {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 4px;
    font-weight: 500;
    margin-bottom: 10px;
}

.connection-status .dashicons {
    margin-right: 5px;
}

.connection-status.connected {
    background-color: #ecf8ed;
    color: #0a5f0a;
}

.connection-status.disconnected {
    background-color: #fcebec;
    color: #a00;
}

.connection-actions {
    margin-top: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle provider panels based on selection
    $('input[name="wpvdb_settings[provider]"]').on('change', function() {
        var provider = $(this).val();
        $('.provider-panel').hide();
        $('#' + provider + '-panel').show();
    });
    
    // Disconnect Automattic AI
    $('#wpvdb-disconnect-automattic').on('click', function() {
        if (confirm('<?php esc_attr_e('Are you sure you want to disconnect your Automattic AI account? This will disable embedding features until you reconnect.', 'wpvdb'); ?>')) {
            // Clear the API key and submit the form
            $('#wpvdb_automattic_api_key').val('');
            $('#wpvdb-settings-form').submit();
        }
    });
    
    // Validate provider change
    $('#wpvdb-settings-form').on('submit', function(e) {
        var newProvider = $('input[name="wpvdb_settings[provider]"]:checked').val();
        var newModel = '';
        
        if (newProvider === 'openai') {
            newModel = $('#wpvdb_openai_model').val();
        } else if (newProvider === 'automattic') {
            newModel = $('#wpvdb_automattic_model').val();
        }
        
        <?php if (!empty($settings['active_provider']) && !empty($settings['active_model'])): ?>
        // We have active settings, check if they're changing
        var activeProvider = '<?php echo esc_js($settings['active_provider']); ?>';
        var activeModel = '<?php echo esc_js($settings['active_model']); ?>';
        
        if (newProvider !== activeProvider || 
            (newProvider === 'openai' && newModel !== activeModel) ||
            (newProvider === 'automattic' && newModel !== activeModel)) {
            
            // Check if we need confirmation
            $.ajax({
                url: wpvdb.ajax_url,
                method: 'POST',
                data: {
                    action: 'wpvdb_validate_provider_change',
                    nonce: wpvdb.nonce
                },
                success: function(response) {
                    if (response.success && response.data.requires_reindex) {
                        if (confirm(wpvdb.i18n.confirm_provider_change)) {
                            // User confirmed, proceed with the form submission
                            $('#wpvdb-settings-form')[0].submit();
                        }
                    } else {
                        // No need for confirmation, submit the form
                        $('#wpvdb-settings-form')[0].submit();
                    }
                },
                error: function() {
                    // Error occurred, submit the form anyway
                    $('#wpvdb-settings-form')[0].submit();
                }
            });
            
            // Prevent form submission until we get the AJAX response
            e.preventDefault();
        }
        <?php endif; ?>
    });
});
</script> 