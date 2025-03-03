<?php
namespace WPVDB;

defined('ABSPATH') || exit;

class Settings {
    
    /**
     * Initialize settings
     */
    public static function init() {
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }
    
    /**
     * Register plugin settings
     */
    public static function register_settings() {
        // API Settings section
        add_settings_section(
            'wpvdb_api_settings',
            __('API Settings', 'wpvdb'),
            [__CLASS__, 'render_api_settings_section'],
            'wpvdb_settings'
        );
        
        // Model defaults section
        add_settings_section(
            'wpvdb_model_settings',
            __('Model Settings', 'wpvdb'),
            [__CLASS__, 'render_model_settings_section'],
            'wpvdb_settings'
        );
        
        // Processing section
        add_settings_section(
            'wpvdb_processing_settings',
            __('Processing Settings', 'wpvdb'),
            [__CLASS__, 'render_processing_settings_section'],
            'wpvdb_settings'
        );
        
        // Register settings
        register_setting('wpvdb_settings', 'wpvdb_api_key', [
            'type' => 'string',
            'sanitize_callback' => [__CLASS__, 'encrypt_api_key'],
            'show_in_rest' => false,
        ]);
        
        register_setting('wpvdb_settings', 'wpvdb_api_base', [
            'type' => 'string',
            'default' => 'https://api.openai.com/v1/',
            'show_in_rest' => false,
        ]);
        
        register_setting('wpvdb_settings', 'wpvdb_default_model', [
            'type' => 'string',
            'default' => 'text-embedding-3-small',
            'show_in_rest' => false,
        ]);
        
        register_setting('wpvdb_settings', 'wpvdb_chunk_size', [
            'type' => 'integer',
            'default' => 200,
            'show_in_rest' => false,
        ]);
        
        register_setting('wpvdb_settings', 'wpvdb_auto_embed_post_types', [
            'type' => 'array',
            'default' => ['post'],
            'show_in_rest' => false,
        ]);
        
        register_setting('wpvdb_settings', 'wpvdb_enable_summarization', [
            'type' => 'boolean',
            'default' => false,
            'show_in_rest' => false,
        ]);
    }
    
    /**
     * Render API settings section
     */
    public static function render_api_settings_section() {
        echo '<p>' . __('Configure your embedding provider API settings.', 'wpvdb') . '</p>';
    }
    
    /**
     * Render model settings section
     */
    public static function render_model_settings_section() {
        echo '<p>' . __('Configure embedding model settings.', 'wpvdb') . '</p>';
    }
    
    /**
     * Render processing settings section
     */
    public static function render_processing_settings_section() {
        echo '<p>' . __('Configure text processing and chunking settings.', 'wpvdb') . '</p>';
    }
    
    /**
     * Encrypt API key for secure storage
     */
    public static function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // Use WordPress core functionality for encryption if available
        if (function_exists('wp_encrypt')) {
            return wp_encrypt($api_key);
        }
        
        // Otherwise store securely with a one-way indicator that it's set
        // (not the actual key - just a placeholder)
        return '*****' . substr(md5($api_key), 0, 8);
    }
    
    /**
     * Get API key with fallback to filter
     */
    public static function get_api_key() {
        $api_key = get_option('wpvdb_api_key', '');
        
        // If no key in options, check filter
        if (empty($api_key)) {
            $api_key = apply_filters('wpvdb_default_api_key', '');
        }
        
        return $api_key;
    }
    
    /**
     * Get API base URL
     */
    public static function get_api_base() {
        return get_option('wpvdb_api_base', 'https://api.openai.com/v1/');
    }
    
    /**
     * Get default embedding model
     */
    public static function get_default_model() {
        return get_option('wpvdb_default_model', 'text-embedding-3-small');
    }
    
    /**
     * Get chunk size setting
     */
    public static function get_chunk_size() {
        return get_option('wpvdb_chunk_size', 200);
    }
    
    /**
     * Get auto-embed post types
     */
    public static function get_auto_embed_post_types() {
        return get_option('wpvdb_auto_embed_post_types', ['post']);
    }
    
    /**
     * Check if summarization is enabled
     */
    public static function is_summarization_enabled() {
        return get_option('wpvdb_enable_summarization', false);
    }
} 