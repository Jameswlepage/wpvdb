<?php
namespace WPVDB;

defined('ABSPATH') || exit;

/**
 * Queue handler for vector embeddings 
 * Uses Action Scheduler if available, falls back to WP Cron
 */
class WPVDB_Queue {
    
    /**
     * Action hook name
     */
    const PROCESS_SINGLE_ACTION = 'wpvdb_process_embedding';
    
    /**
     * Option name for fallback queue
     */
    const FALLBACK_QUEUE_OPTION = 'wpvdb_embedding_queue';
    
    /**
     * Add item to queue
     *
     * @param mixed $data Data to add to queue
     * @return $this
     */
    public function push_to_queue($data) {
        // Use Action Scheduler if available
        if (function_exists('wpvdb_has_action_scheduler') && wpvdb_has_action_scheduler()) {
            // Note: we use the global function, not namespaced
            as_schedule_single_action(
                time(), // Run as soon as possible
                self::PROCESS_SINGLE_ACTION,
                [$data],
                'wpvdb' // Group name
            );
        } else {
            // Fallback to WP Cron
            $this->add_to_fallback_queue($data);
        }
        
        return $this;
    }
    
    /**
     * Add item to fallback queue
     * 
     * @param mixed $data Data to add to queue
     * @return void
     */
    private function add_to_fallback_queue($data) {
        $queue = get_option(self::FALLBACK_QUEUE_OPTION, []);
        $queue[] = $data;
        update_option(self::FALLBACK_QUEUE_OPTION, $queue);
        
        // Make sure we have a cron event scheduled
        if (!wp_next_scheduled('wpvdb_process_fallback_queue')) {
            wp_schedule_event(time(), 'hourly', 'wpvdb_process_fallback_queue');
        }
    }
    
    /**
     * Process items in the fallback queue
     * 
     * @param int $limit Number of items to process
     * @return void
     */
    public function process_fallback_queue($limit = 5) {
        $queue = get_option(self::FALLBACK_QUEUE_OPTION, []);
        
        // Nothing to do
        if (empty($queue)) {
            return;
        }
        
        $processed = 0;
        $updated_queue = $queue;
        
        foreach ($queue as $key => $item) {
            if ($processed >= $limit) {
                break;
            }
            
            self::process_item($item);
            
            // Remove from queue
            unset($updated_queue[$key]);
            $processed++;
        }
        
        // Reindex array
        $updated_queue = array_values($updated_queue);
        
        // Update queue in database
        update_option(self::FALLBACK_QUEUE_OPTION, $updated_queue);
    }
    
    /**
     * Save queue - not needed with Action Scheduler
     * Kept for API compatibility
     *
     * @return $this
     */
    public function save() {
        return $this;
    }
    
    /**
     * Dispatch queue - not needed with Action Scheduler
     * Kept for API compatibility
     *
     * @return $this
     */
    public function dispatch() {
        return $this;
    }
    
    /**
     * Process a single queue item (called by Action Scheduler or fallback)
     * 
     * @param array $item Queue item
     * @return bool Success status
     */
    public static function process_item($item) {
        // Extract data from item
        $post_id = isset($item['post_id']) ? absint($item['post_id']) : 0;
        $model = isset($item['model']) ? sanitize_text_field($item['model']) : Settings::get_default_model();
        
        if (!$post_id) {
            Core::log_error('Invalid post ID in queue task', ['item' => $item]);
            return false;
        }
        
        // Get post content
        $post = get_post($post_id);
        
        if (!$post || !is_object($post)) {
            Core::log_error('Post not found', ['post_id' => $post_id]);
            return false;
        }
        
        // Validate post content
        if (!isset($post->post_title) || !isset($post->post_content)) {
            Core::log_error('Post missing required fields', ['post_id' => $post_id]);
            return false;
        }
        
        // Get API key
        $api_key = Settings::get_api_key();
        
        if (empty($api_key)) {
            Core::log_error('No API key configured', ['post_id' => $post_id]);
            return false;
        }
        
        // Get API base
        $api_base = Settings::get_api_base();
        
        // Combine content (title + content)
        $title = !empty($post->post_title) ? $post->post_title : '';
        $content = !empty($post->post_content) ? wp_strip_all_tags($post->post_content) : '';
        $text = $title . "\n\n" . $content;
        
        // Ensure we have actual content to embed
        if (trim($text) === '') {
            Core::log_error('Post has no content to embed', ['post_id' => $post_id]);
            return false;
        }
        
        // Generate and store embeddings for the post
        return self::process_post($post, $model);
    }
    
    /**
     * Process a post - extract content, chunk, and generate embeddings
     *
     * @param \WP_Post $post
     * @param string $model
     * @return bool Success status
     */
    private static function process_post($post, $model) {
        // Get API key
        $api_key = Settings::get_api_key();
        if (empty($api_key)) {
            Core::log_error('No API key available for embedding generation', ['post_id' => $post->ID]);
            return false;
        }
        
        // Get API base
        $api_base = Settings::get_api_base();
        
        // Combine content (title + content)
        $text = $post->post_title . "\n\n" . wp_strip_all_tags($post->post_content);
        
        // Chunk the text
        $chunks = apply_filters('wpvdb_chunk_text', [], $text);
        
        if (empty($chunks)) {
            Core::log_error('No chunks generated for post', ['post_id' => $post->ID]);
            return false;
        }
        
        $successful_chunks = 0;
        
        foreach ($chunks as $index => $chunk) {
            // Get summary if enabled
            $summary = '';
            if (Settings::is_summarization_enabled()) {
                $summary = apply_filters('wpvdb_ai_summarize_chunk', '', $chunk);
            }
            
            // Get embedding
            $embedding_result = Core::get_embedding($chunk, $model, $api_base, $api_key);
            if (is_wp_error($embedding_result)) {
                Core::log_error('Failed to generate embedding', [
                    'post_id' => $post->ID,
                    'chunk_index' => $index,
                    'error' => $embedding_result->get_error_message(),
                ]);
                continue;
            }
            
            // Call the method correctly as a static method
            $result = REST::insert_embedding_row(
                $post->ID,
                'chunk-' . $index,
                $chunk,
                $summary,
                $embedding_result
            );
            
            if (is_wp_error($result)) {
                Core::log_error('Failed to insert embedding', [
                    'post_id' => $post->ID,
                    'chunk_index' => $index,
                    'error' => $result->get_error_message(),
                ]);
                continue;
            }
            
            $successful_chunks++;
        }
        
        // Update post meta with embedding information
        update_post_meta($post->ID, '_wpvdb_embedded', true);
        update_post_meta($post->ID, '_wpvdb_chunks_count', $successful_chunks);
        update_post_meta($post->ID, '_wpvdb_embedded_date', current_time('mysql'));
        update_post_meta($post->ID, '_wpvdb_embedded_model', $model);
        
        return $successful_chunks > 0;
    }
}