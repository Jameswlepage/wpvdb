<?php
namespace WPVDB;

defined('ABSPATH') || exit;

class Settings {
    
    /**
     * Initialize settings
     */
    public static function init() {
        // No action needed - settings are registered in Admin class
    }
    
    /**
     * Encrypt API key for secure storage
     */
    public static function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // WordPress core doesn't have a built-in encryption function
        // Store securely with a one-way indicator that it's set
        // (not the actual key - just a placeholder)
        return '*****' . substr(md5($api_key), 0, 8);
    }
    
    /**
     * Get API key with fallback to filter
     */
    public static function get_api_key() {
        $settings = get_option('wpvdb_settings', []);
        $provider = isset($settings['active_provider']) ? $settings['active_provider'] : 'openai';
        
        // Check for constants defined in wp-config.php first
        if ($provider === 'openai' && defined('WPVDB_OPENAI_API_KEY')) {
            return \constant('WPVDB_OPENAI_API_KEY');
        }
        
        if ($provider === 'automattic' && defined('WPVDB_AUTOMATTIC_API_KEY')) {
            return \constant('WPVDB_AUTOMATTIC_API_KEY');
        }
        
        $api_key = isset($settings[$provider]['api_key']) ? $settings[$provider]['api_key'] : '';
        
        // If no key in options, check filter
        if (empty($api_key)) {
            $api_key = apply_filters('wpvdb_default_api_key', '');
        }
        
        return $api_key;
    }
    
    /**
     * Get API key for a specific provider
     *
     * @param string $provider Provider name (openai, automattic, etc.)
     * @return string API key or empty string if not found
     */
    public static function get_api_key_for_provider($provider) {
        $settings = get_option('wpvdb_settings', []);
        
        if (!is_array($settings)) {
            return '';
        }
        
        // Check in the provider-specific settings
        if (isset($settings[$provider]['api_key']) && !empty($settings[$provider]['api_key'])) {
            return $settings[$provider]['api_key'];
        }
        
        // Check in the active provider setting
        if (isset($settings['active_provider']) && $settings['active_provider'] === $provider) {
            return isset($settings['api_key']) ? $settings['api_key'] : '';
        }
        
        // Default to empty
        return '';
    }
    
    /**
     * Get API base URL
     */
    public static function get_api_base() {
        $settings = get_option('wpvdb_settings', []);
        $provider = isset($settings['active_provider']) ? $settings['active_provider'] : 'openai';
        
        // Get the API base from the provider registry
        $api_base = Providers::get_api_base($provider);
        
        // If not found in registry, check settings
        if (empty($api_base)) {
            // For Automattic, check the specific endpoint setting
            if ($provider === 'automattic') {
                return get_option('wpvdb_automattic_endpoint', 'https://ai-api.wp.com/embeddings');
            }
            
            // For other providers, use the general api_base setting or default to OpenAI
            return isset($settings['api_base']) ? $settings['api_base'] : 'https://api.openai.com/v1/';
        }
        
        return $api_base;
    }
    
    /**
     * Get API base URL for a specific provider
     *
     * @param string $provider Provider name (openai, automattic, etc.)
     * @return string API base URL or empty string if not found
     */
    public static function get_api_base_for_provider($provider) {
        $settings = get_option('wpvdb_settings', []);
        
        if (!is_array($settings)) {
            return '';
        }
        
        // Check in the provider-specific settings
        if (isset($settings[$provider]['api_base']) && !empty($settings[$provider]['api_base'])) {
            return $settings[$provider]['api_base'];
        }
        
        // Check if we have default API bases
        switch ($provider) {
            case 'openai':
                return 'https://api.openai.com/v1/';
            case 'automattic':
                return 'https://api.automattic.com/ai/v1/';
            default:
                return '';
        }
    }
    
    /**
     * Get default embedding model
     */
    public static function get_default_model() {
        $settings = get_option('wpvdb_settings', []);
        $provider = isset($settings['active_provider']) ? $settings['active_provider'] : 'openai';
        
        // Check settings first
        if (isset($settings[$provider]['default_model']) && !empty($settings[$provider]['default_model'])) {
            return $settings[$provider]['default_model'];
        }
        
        // Otherwise get default from Models registry
        return Models::get_default_model_for_provider($provider);
    }
    
    /**
     * Get default model for a specific provider
     *
     * @param string $provider Provider name (openai, automattic, etc.)
     * @return string Model name or empty string if not found
     */
    public static function get_model_for_provider($provider) {
        $settings = get_option('wpvdb_settings', []);
        
        if (!is_array($settings)) {
            return Models::get_default_model_for_provider($provider);
        }
        
        // Check in the provider-specific settings
        if (isset($settings[$provider]['default_model']) && !empty($settings[$provider]['default_model'])) {
            return $settings[$provider]['default_model'];
        }
        
        // Otherwise get default from Models registry
        return Models::get_default_model_for_provider($provider);
    }

    /**
     * Get active provider from settings
     * 
     * @return string Active provider name
     */
    public static function get_active_provider() {
        $settings = get_option('wpvdb_settings', []);
        return isset($settings['active_provider']) && !empty($settings['active_provider']) 
            ? $settings['active_provider'] 
            : 'openai';
    }

    /**
     * Get active model from settings
     * 
     * @return string Active model name
     */
    public static function get_active_model() {
        $settings = get_option('wpvdb_settings', []);
        
        if (isset($settings['active_model']) && !empty($settings['active_model'])) {
            return $settings['active_model'];
        }
        
        $active_provider = self::get_active_provider();
        return self::get_model_for_provider($active_provider);
    }
    
    /**
     * Get chunk size setting
     */
    public static function get_chunk_size() {
        $settings = get_option('wpvdb_settings', []);
        return isset($settings['chunk_size']) ? $settings['chunk_size'] : 1000;
    }
    
    /**
     * Get auto-embed post types
     */
    public static function get_auto_embed_post_types() {
        $settings = get_option('wpvdb_settings', []);
        return isset($settings['post_types']) && is_array($settings['post_types']) 
            ? $settings['post_types'] 
            : ['post'];
    }
    
    /**
     * Check if summarization is enabled
     * 
     * @return bool Whether summarization is enabled
     */
    public static function is_summarization_enabled() {
        $settings = get_option('wpvdb_settings', []);
        return isset($settings['enable_summarization']) && $settings['enable_summarization'] === '1';
    }
    
    /**
     * Get the batch size for queue processing
     * 
     * @return int Batch size (between 1 and 50)
     */
    public static function get_batch_size() {
        $settings = get_option('wpvdb_settings', []);
        $batch_size = isset($settings['queue_batch_size']) ? absint($settings['queue_batch_size']) : 10;
        
        // Ensure a reasonable value
        if ($batch_size < 1) {
            $batch_size = 1;
        } else if ($batch_size > 50) {
            $batch_size = 50;
        }
        
        return $batch_size;
    }
    
    /**
     * Check if there is a pending provider/model change
     * 
     * @return bool Whether there is a pending change
     */
    public static function has_pending_provider_change() {
        // CRITICAL FIX: Get settings directly from database, bypassing cache
        $settings = get_option('wpvdb_settings', []);
        
        // Debug log for troubleshooting
        error_log('WPVDB CRITICAL: has_pending_provider_change check. Settings: ' . print_r($settings, true));
        
        if (!is_array($settings)) {
            error_log('WPVDB CRITICAL: Invalid settings format - not an array');
            return false;
        }
        
        $has_pending = (!empty($settings['pending_provider']) || !empty($settings['pending_model']));
        error_log('WPVDB CRITICAL: Has pending provider change: ' . ($has_pending ? 'YES' : 'NO'));
        
        return $has_pending;
    }
    
    /**
     * Get pending change details
     * 
     * @return array|false Pending change details or false if no pending change
     */
    public static function get_pending_change_details() {
        $settings = get_option('wpvdb_settings', []);
        
        if (!is_array($settings)) {
            return false;
        }
        
        if (empty($settings['pending_provider']) && empty($settings['pending_model'])) {
            return false;
        }
        
        return [
            'active_provider' => isset($settings['active_provider']) ? $settings['active_provider'] : '',
            'active_model' => isset($settings['active_model']) ? $settings['active_model'] : '',
            'pending_provider' => isset($settings['pending_provider']) ? $settings['pending_provider'] : '',
            'pending_model' => isset($settings['pending_model']) ? $settings['pending_model'] : '',
        ];
    }
} 