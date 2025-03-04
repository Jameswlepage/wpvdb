<?php

namespace WPVDB;

class WPVDB {
    public function init() {
        // All includes are handled in plugin.php, so we don't need the includes method
        
        // Initialize classes
        Admin::init();
        Settings::init();
        
        // Register REST routes
        add_action('rest_api_init', [REST::class, 'register_routes']);
        
        // Register action to process embeddings
        add_action('wpvdb_process_embedding', [WPVDB_Queue::class, 'process_item'], 10, 1);
        
        // Add action to run queue immediately
        add_action('wpvdb_run_queue_now', [$this, 'run_queue_immediately']);
    }
    
    /**
     * Run the embedding queue immediately
     * 
     * This is used when we want to process the queue right away rather than waiting for cron
     */
    public function run_queue_immediately() {
        if (function_exists('as_get_scheduled_actions')) {
            $actions = as_get_scheduled_actions([
                'hook' => 'wpvdb_process_embedding',
                'status' => \ActionScheduler_Store::STATUS_PENDING,
                'per_page' => 1
            ]);
            
            if (!empty($actions)) {
                $action = reset($actions);
                $action_id = $action->get_id();
                
                // Run the action now
                do_action('wpvdb_process_embedding', $action_id);
            }
        }
    }
} 