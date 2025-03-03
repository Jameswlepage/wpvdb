<div class="wrap wpvdb-status">
    <h1><?php esc_html_e('Vector Database Status', 'wpvdb'); ?></h1>
    
    <div class="wpvdb-card">
        <h2><?php esc_html_e('System Information', 'wpvdb'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <th><?php esc_html_e('PHP Version', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['php_version']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('WordPress Version', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['wp_version']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('WP Memory Limit', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['wp_memory_limit']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Max Execution Time', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['max_execution_time']); ?> <?php esc_html_e('seconds', 'wpvdb'); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('POST Max Size', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['post_max_size']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Max Input Vars', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['max_input_vars']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('cURL Version', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($system_info['curl_version']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('OpenAI API Key', 'wpvdb'); ?></th>
                    <td>
                        <?php if ($system_info['openai_api_key_set']) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color:green;"></span> 
                            <?php esc_html_e('API key is set', 'wpvdb'); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-warning" style="color:red;"></span> 
                            <?php esc_html_e('API key is not set', 'wpvdb'); ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=wpvdb-settings')); ?>">
                                <?php esc_html_e('Configure now', 'wpvdb'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="wpvdb-card">
        <h2><?php esc_html_e('Database Information', 'wpvdb'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <th><?php esc_html_e('Database Version', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($db_info['db_version']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Table Prefix', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($db_info['prefix']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Charset', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($db_info['charset']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Collation', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($db_info['collate']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Embeddings Table', 'wpvdb'); ?></th>
                    <td>
                        <?php if ($db_info['table_exists']) : ?>
                            <span class="dashicons dashicons-yes-alt" style="color:green;"></span> 
                            <?php esc_html_e('Table exists', 'wpvdb'); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-warning" style="color:red;"></span> 
                            <?php esc_html_e('Table does not exist', 'wpvdb'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <?php if ($db_info['table_exists']) : ?>
    <div class="wpvdb-card">
        <h2><?php esc_html_e('Database Statistics', 'wpvdb'); ?></h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <th><?php esc_html_e('Total Embeddings', 'wpvdb'); ?></th>
                    <td><?php echo esc_html(number_format_i18n($db_stats['total_embeddings'])); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Total Documents', 'wpvdb'); ?></th>
                    <td><?php echo esc_html(number_format_i18n($db_stats['total_docs'])); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Storage Used', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($db_stats['storage_used']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Average Embedding Size', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($db_stats['avg_embedding_size']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Largest Embedding', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($db_stats['largest_embedding']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Average Chunk Content Size', 'wpvdb'); ?></th>
                    <td><?php echo esc_html($db_stats['avg_chunk_content_size']); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="wpvdb-card">
        <h2><?php esc_html_e('Table Structure', 'wpvdb'); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Field', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Type', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Null', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Key', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Default', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Extra', 'wpvdb'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($table_structure as $column) : ?>
                <tr>
                    <td><?php echo esc_html($column->Field); ?></td>
                    <td><?php echo esc_html($column->Type); ?></td>
                    <td><?php echo esc_html($column->Null); ?></td>
                    <td><?php echo esc_html($column->Key); ?></td>
                    <td><?php echo $column->Default !== null ? esc_html($column->Default) : '<em>NULL</em>'; ?></td>
                    <td><?php echo esc_html($column->Extra); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="wpvdb-card">
        <h2><?php esc_html_e('Database Tools', 'wpvdb'); ?></h2>
        <div class="wpvdb-tools-grid">
            <div class="wpvdb-tool-card">
                <h3><?php esc_html_e('Export Embeddings', 'wpvdb'); ?></h3>
                <p><?php esc_html_e('Export all embeddings to a JSON file for backup or migration.', 'wpvdb'); ?></p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpvdb-status&action=export_embeddings'), 'wpvdb_export_embeddings')); ?>" class="button">
                    <?php esc_html_e('Export', 'wpvdb'); ?>
                </a>
            </div>
            
            <div class="wpvdb-tool-card">
                <h3><?php esc_html_e('Optimize Table', 'wpvdb'); ?></h3>
                <p><?php esc_html_e('Optimize the database table to improve performance.', 'wpvdb'); ?></p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpvdb-status&action=optimize_table'), 'wpvdb_optimize_table')); ?>" class="button">
                    <?php esc_html_e('Optimize', 'wpvdb'); ?>
                </a>
            </div>
            
            <div class="wpvdb-tool-card warning">
                <h3><?php esc_html_e('Clear All Embeddings', 'wpvdb'); ?></h3>
                <p><?php esc_html_e('Delete all embeddings from the database. This action cannot be undone!', 'wpvdb'); ?></p>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpvdb-status&action=clear_embeddings'), 'wpvdb_clear_embeddings')); ?>" class="button button-danger" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete all embeddings? This action cannot be undone!', 'wpvdb'); ?>');">
                    <?php esc_html_e('Clear All', 'wpvdb'); ?>
                </a>
            </div>
        </div>
    </div>
    <?php else : ?>
    <div class="wpvdb-card notice-warning">
        <p>
            <span class="dashicons dashicons-warning"></span>
            <?php esc_html_e('The embeddings table does not exist. Please activate the plugin or run the installer to create the necessary database tables.', 'wpvdb'); ?>
        </p>
        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpvdb-status&action=create_tables'), 'wpvdb_create_tables')); ?>" class="button button-primary">
            <?php esc_html_e('Create Tables Now', 'wpvdb'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
.wpvdb-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-bottom: 20px;
    padding: 15px;
}

.wpvdb-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    grid-gap: 20px;
}

.wpvdb-tool-card {
    background: #f8f9fa;
    border: 1px solid #e2e4e7;
    padding: 15px;
    border-radius: 3px;
}

.wpvdb-tool-card.warning {
    background: #fff8e5;
    border-color: #f0c33c;
}

.button-danger {
    background: #dc3232;
    border-color: #b32d2e;
    color: white;
}

.button-danger:hover {
    background: #b32d2e;
    color: white;
}
</style> 