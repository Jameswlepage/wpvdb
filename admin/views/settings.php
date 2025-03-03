<div class="wrap wpvdb-settings">
    <h1><?php esc_html_e('Vector Database Settings', 'wpvdb'); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('wpvdb_settings');
        do_settings_sections('wpvdb_settings');
        ?>
        
        <div class="wpvdb-settings-section">
            <h2><?php esc_html_e('API Configuration', 'wpvdb'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wpvdb_api_key"><?php esc_html_e('OpenAI API Key', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="wpvdb_api_key" 
                               name="wpvdb_settings[api_key]" 
                               value="<?php echo esc_attr(get_option('wpvdb_settings')['api_key'] ?? ''); ?>" 
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Enter your OpenAI API key to enable embedding generation', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpvdb_default_model"><?php esc_html_e('Default Embedding Model', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <select id="wpvdb_default_model" name="wpvdb_settings[default_model]">
                            <?php 
                            $default_model = get_option('wpvdb_settings')['default_model'] ?? 'text-embedding-3-small';
                            $models = [
                                'text-embedding-3-small' => 'text-embedding-3-small (Recommended)',
                                'text-embedding-3-large' => 'text-embedding-3-large (Higher Accuracy)',
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
                        <p class="description">
                            <?php esc_html_e('Select the default embedding model to use', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="wpvdb-settings-section">
            <h2><?php esc_html_e('Content Processing', 'wpvdb'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wpvdb_chunk_size"><?php esc_html_e('Chunk Size', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="wpvdb_chunk_size" 
                               name="wpvdb_settings[chunk_size]" 
                               value="<?php echo esc_attr(get_option('wpvdb_settings')['chunk_size'] ?? 1000); ?>" 
                               min="100" 
                               max="8000" 
                               step="100" 
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Maximum number of characters per content chunk (100-8000)', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpvdb_chunk_overlap"><?php esc_html_e('Chunk Overlap', 'wpvdb'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="wpvdb_chunk_overlap" 
                               name="wpvdb_settings[chunk_overlap]" 
                               value="<?php echo esc_attr(get_option('wpvdb_settings')['chunk_overlap'] ?? 200); ?>" 
                               min="0" 
                               max="1000" 
                               step="50" 
                               class="regular-text">
                        <p class="description">
                            <?php esc_html_e('Number of characters to overlap between chunks', 'wpvdb'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="wpvdb-settings-section">
            <h2><?php esc_html_e('Auto-Embedding', 'wpvdb'); ?></h2>
            
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
                                       <?php checked(get_option('wpvdb_settings')['auto_embed'] ?? false); ?>>
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
                            $enabled_post_types = get_option('wpvdb_settings')['post_types'] ?? ['post', 'page'];
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
        </div>
        
        <?php submit_button(); ?>
    </form>
</div> 