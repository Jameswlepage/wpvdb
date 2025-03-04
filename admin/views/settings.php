<?php
// Get current settings
$provider = get_option('wpvdb_provider', 'openai');
$openai_api_key = get_option('wpvdb_openai_api_key', '');
$openai_model = get_option('wpvdb_openai_model', 'text-embedding-3-small');
$automattic_api_key = get_option('wpvdb_automattic_api_key', '');
$automattic_model = get_option('wpvdb_automattic_model', 'text-embedding-ada-002');
$auto_embed_post_types = get_option('wpvdb_auto_embed_post_types', []);
$chunk_size = get_option('wpvdb_chunk_size', 200);
$chunk_overlap = get_option('wpvdb_chunk_overlap', 20);
$summarize_chunks = get_option('wpvdb_summarize_chunks', false);
$include_metadata = get_option('wpvdb_include_metadata', true);
$include_taxonomies = get_option('wpvdb_include_taxonomies', true);
$include_acf = get_option('wpvdb_include_acf', false);
$include_comments = get_option('wpvdb_include_comments', false);
$include_featured_image = get_option('wpvdb_include_featured_image', false);
$include_custom_fields = get_option('wpvdb_include_custom_fields', []);
$exclude_taxonomies = get_option('wpvdb_exclude_taxonomies', []);
$exclude_custom_fields = get_option('wpvdb_exclude_custom_fields', []);
?>

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
        <?php settings_fields('wpvdb_settings'); ?>
        
        <div class="wpvdb-settings-section">
            <h2><?php esc_html_e('API Configuration', 'wpvdb'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wpvdb_provider"><?php esc_html_e('Embedding Provider', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <select name="wpvdb_provider" id="wpvdb_provider">
                            <option value="openai" <?php selected($provider, 'openai'); ?>><?php esc_html_e('OpenAI', 'wpvdb'); ?></option>
                            <option value="automattic" <?php selected($provider, 'automattic'); ?>><?php esc_html_e('Automattic AI', 'wpvdb'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Select the provider for generating embeddings.', 'wpvdb'); ?></p>
                        
                        <input type="hidden" id="wpvdb_current_provider" value="<?php echo esc_attr($provider); ?>">
                        <input type="hidden" id="wpvdb_current_model" value="<?php echo esc_attr($provider === 'openai' ? $openai_model : $automattic_model); ?>">
                    </td>
                </tr>
                
                <tr id="openai_api_key_field" class="api-key-field">
                    <th scope="row">
                        <label for="wpvdb_openai_api_key"><?php esc_html_e('OpenAI API Key', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="wpvdb_openai_api_key" 
                               id="wpvdb_openai_api_key" 
                               value="<?php echo esc_attr($openai_api_key); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Enter your OpenAI API key. You can get one from', 'wpvdb'); ?> 
                            <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a>
                        </p>
                    </td>
                </tr>
                
                <tr id="openai_model_field" class="model-field">
                    <th scope="row">
                        <label for="wpvdb_openai_model"><?php esc_html_e('OpenAI Embedding Model', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <select name="wpvdb_openai_model" id="wpvdb_openai_model">
                            <option value="text-embedding-3-small" <?php selected($openai_model, 'text-embedding-3-small'); ?>>text-embedding-3-small (1536 dimensions)</option>
                            <option value="text-embedding-3-large" <?php selected($openai_model, 'text-embedding-3-large'); ?>>text-embedding-3-large (3072 dimensions)</option>
                            <option value="text-embedding-ada-002" <?php selected($openai_model, 'text-embedding-ada-002'); ?>>text-embedding-ada-002 (1536 dimensions, Legacy)</option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the OpenAI model to use for generating embeddings.', 'wpvdb'); ?>
                            <a href="https://platform.openai.com/docs/guides/embeddings" target="_blank"><?php esc_html_e('Learn more', 'wpvdb'); ?></a>
                        </p>
                    </td>
                </tr>
                
                <tr id="automattic_api_key_field" class="api-key-field">
                    <th scope="row">
                        <label for="wpvdb_automattic_api_key"><?php esc_html_e('Automattic AI API Key', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               name="wpvdb_automattic_api_key" 
                               id="wpvdb_automattic_api_key" 
                               value="<?php echo esc_attr($automattic_api_key); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Enter your Automattic AI API key or', 'wpvdb'); ?> 
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-automattic-connect')); ?>"><?php esc_html_e('connect automatically', 'wpvdb'); ?></a>
                        </p>
                    </td>
                </tr>
                
                <tr id="automattic_model_field" class="model-field">
                    <th scope="row">
                        <label for="wpvdb_automattic_model"><?php esc_html_e('Automattic AI Embedding Model', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <select name="wpvdb_automattic_model" id="wpvdb_automattic_model">
                            <option value="text-embedding-ada-002" <?php selected($automattic_model, 'text-embedding-ada-002'); ?>>text-embedding-ada-002 (1536 dimensions)</option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Select the Automattic AI model to use for generating embeddings.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="wpvdb-settings-section">
            <h2><?php esc_html_e('Content Settings', 'wpvdb'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Auto-Embed Post Types', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Auto-Embed Post Types', 'wpvdb'); ?></legend>
                            
                            <p>
                                <label>
                                    <input type="checkbox" id="wpvdb_auto_embed_toggle_all">
                                    <strong><?php esc_html_e('Toggle All', 'wpvdb'); ?></strong>
                                </label>
                            </p>
                            
                            <?php
                            $post_types = get_post_types(['public' => true], 'objects');
                            foreach ($post_types as $post_type) :
                                $checked = in_array($post_type->name, $auto_embed_post_types) ? 'checked' : '';
                            ?>
                                <p>
                                    <label>
                                        <input type="checkbox" 
                                               name="wpvdb_auto_embed_post_types[]" 
                                               value="<?php echo esc_attr($post_type->name); ?>" 
                                               <?php echo $checked; ?>
                                               class="wpvdb-post-type-checkbox">
                                        <?php echo esc_html($post_type->label); ?>
                                    </label>
                                </p>
                            <?php endforeach; ?>
                            
                            <p class="description">
                                <?php esc_html_e('Select which post types should automatically generate embeddings when published or updated.', 'wpvdb'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_chunk_size"><?php esc_html_e('Chunk Size (words)', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="wpvdb_chunk_size" 
                               id="wpvdb_chunk_size" 
                               value="<?php echo esc_attr($chunk_size); ?>" 
                               min="50" 
                               max="1000" 
                               step="10" 
                               class="small-text">
                        <p class="description">
                            <?php esc_html_e('The approximate number of words per chunk. Smaller chunks are more precise but create more embeddings.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_chunk_overlap"><?php esc_html_e('Chunk Overlap (%)', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="wpvdb_chunk_overlap" 
                               id="wpvdb_chunk_overlap" 
                               value="<?php echo esc_attr($chunk_overlap); ?>" 
                               min="0" 
                               max="50" 
                               class="small-text">
                        <p class="description">
                            <?php esc_html_e('The percentage of overlap between consecutive chunks. Higher overlap helps maintain context between chunks.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_summarize_chunks"><?php esc_html_e('Summarize Chunks', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wpvdb_summarize_chunks" 
                                   id="wpvdb_summarize_chunks" 
                                   value="1" 
                                   <?php checked($summarize_chunks, true); ?>>
                            <?php esc_html_e('Generate summaries for each chunk', 'wpvdb'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, a summary will be generated for each chunk to improve retrieval quality. This requires additional API calls.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="wpvdb-settings-section">
            <h2><?php esc_html_e('Content Inclusion Settings', 'wpvdb'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wpvdb_include_metadata"><?php esc_html_e('Include Post Metadata', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wpvdb_include_metadata" 
                                   id="wpvdb_include_metadata" 
                                   value="1" 
                                   <?php checked($include_metadata, true); ?>>
                            <?php esc_html_e('Include title, excerpt, and author information', 'wpvdb'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, post title, excerpt, and author information will be included in the content for embedding.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_include_taxonomies"><?php esc_html_e('Include Taxonomies', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wpvdb_include_taxonomies" 
                                   id="wpvdb_include_taxonomies" 
                                   value="1" 
                                   <?php checked($include_taxonomies, true); ?>>
                            <?php esc_html_e('Include categories, tags, and custom taxonomies', 'wpvdb'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, categories, tags, and custom taxonomies will be included in the content for embedding.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_exclude_taxonomies"><?php esc_html_e('Exclude Specific Taxonomies', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="wpvdb_exclude_taxonomies" 
                               id="wpvdb_exclude_taxonomies" 
                               value="<?php echo esc_attr(implode(', ', $exclude_taxonomies)); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Enter taxonomy names to exclude, separated by commas (e.g., "post_tag, product_cat").', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_include_acf"><?php esc_html_e('Include ACF Fields', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wpvdb_include_acf" 
                                   id="wpvdb_include_acf" 
                                   value="1" 
                                   <?php checked($include_acf, true); ?>>
                            <?php esc_html_e('Include Advanced Custom Fields content', 'wpvdb'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled and ACF is active, text-based ACF fields will be included in the content for embedding.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_include_comments"><?php esc_html_e('Include Comments', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wpvdb_include_comments" 
                                   id="wpvdb_include_comments" 
                                   value="1" 
                                   <?php checked($include_comments, true); ?>>
                            <?php esc_html_e('Include approved comments', 'wpvdb'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, approved comments will be included in the content for embedding.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_include_featured_image"><?php esc_html_e('Include Featured Image Alt Text', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="wpvdb_include_featured_image" 
                                   id="wpvdb_include_featured_image" 
                                   value="1" 
                                   <?php checked($include_featured_image, true); ?>>
                            <?php esc_html_e('Include featured image alt text and caption', 'wpvdb'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, featured image alt text and caption will be included in the content for embedding.', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_include_custom_fields"><?php esc_html_e('Include Specific Custom Fields', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="wpvdb_include_custom_fields" 
                               id="wpvdb_include_custom_fields" 
                               value="<?php echo esc_attr(implode(', ', $include_custom_fields)); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Enter custom field names to include, separated by commas (e.g., "custom_intro, product_description").', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="wpvdb_exclude_custom_fields"><?php esc_html_e('Exclude Specific Custom Fields', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="wpvdb_exclude_custom_fields" 
                               id="wpvdb_exclude_custom_fields" 
                               value="<?php echo esc_attr(implode(', ', $exclude_custom_fields)); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Enter custom field names to exclude, separated by commas (e.g., "_edit_lock, _edit_last").', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <?php submit_button(__('Save Settings', 'wpvdb')); ?>
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