<div class="wrap wpvdb-embeddings">
    <h1><?php esc_html_e('Vector Database Embeddings', 'wpvdb'); ?></h1>
    
    <div class="wpvdb-toolbar">
        <div class="wpvdb-search">
            <form method="get">
                <input type="hidden" name="page" value="wpvdb-embeddings">
                <input type="search" 
                       name="s" 
                       value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>" 
                       placeholder="<?php esc_attr_e('Search embeddings...', 'wpvdb'); ?>">
                <button type="submit" class="button"><?php esc_html_e('Search', 'wpvdb'); ?></button>
            </form>
        </div>
        
        <div class="wpvdb-actions">
            <button id="wpvdb-bulk-embed" class="button button-primary">
                <?php esc_html_e('Bulk Generate Embeddings', 'wpvdb'); ?>
            </button>
        </div>
    </div>
    
    <?php if (empty($embeddings)) : ?>
        <div class="wpvdb-no-data">
            <p><?php esc_html_e('No embeddings found. Use the "Bulk Generate Embeddings" button to create embeddings for your content.', 'wpvdb'); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Document', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Chunk', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Preview', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Summary', 'wpvdb'); ?></th>
                    <th><?php esc_html_e('Actions', 'wpvdb'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($embeddings as $embedding) : 
                    $post_title = get_the_title($embedding->doc_id);
                    $post_edit_link = get_edit_post_link($embedding->doc_id);
                ?>
                    <tr>
                        <td><?php echo esc_html($embedding->id); ?></td>
                        <td>
                            <?php if ($post_edit_link) : ?>
                                <a href="<?php echo esc_url($post_edit_link); ?>" target="_blank">
                                    <?php echo esc_html($post_title ?: __('(No title)', 'wpvdb')); ?> 
                                    <span class="dashicons dashicons-external"></span>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($post_title ?: __('(No title)', 'wpvdb')); ?>
                            <?php endif; ?>
                            <div class="row-actions">
                                <span class="id"><?php printf(__('Post ID: %d', 'wpvdb'), $embedding->doc_id); ?></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($embedding->chunk_id); ?></td>
                        <td>
                            <div class="wpvdb-preview">
                                <?php echo esc_html($embedding->preview); ?>...
                                <button class="wpvdb-view-full button-link" 
                                       data-id="<?php echo esc_attr($embedding->id); ?>">
                                    <?php esc_html_e('View Full', 'wpvdb'); ?>
                                </button>
                            </div>
                        </td>
                        <td><?php echo esc_html($embedding->summary); ?></td>
                        <td>
                            <button class="wpvdb-delete-embedding button button-link-delete" 
                                   data-id="<?php echo esc_attr($embedding->id); ?>">
                                <?php esc_html_e('Delete', 'wpvdb'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1) : ?>
            <div class="wpvdb-pagination">
                <?php
                echo paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo; Previous'),
                    'next_text' => __('Next &raquo;'),
                    'total' => $total_pages,
                    'current' => $page,
                ]);
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <div id="wpvdb-full-content-modal" class="wpvdb-modal" style="display:none;">
        <div class="wpvdb-modal-content">
            <span class="wpvdb-modal-close">&times;</span>
            <h2><?php esc_html_e('Full Content', 'wpvdb'); ?></h2>
            <div class="wpvdb-full-content"></div>
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