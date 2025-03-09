<?php
namespace WPVDB;

defined('ABSPATH') || exit;

/**
 * Provider registry for WPVDB
 * 
 * Centralized registry of embedding providers with their capabilities and metadata
 */
class Providers {
    /**
     * Get all registered providers
     *
     * @return array Providers with their details
     */
    public static function get_available_providers() {
        $default_providers = [
            'openai' => [
                'name' => 'openai',
                'label' => 'OpenAI',
                'api_base' => 'https://api.openai.com/v1/',
                'api_key_constant' => 'WPVDB_OPENAI_API_KEY',
                'description' => __('OpenAI provides state-of-the-art embedding models like text-embedding-3-small.', 'wpvdb')
            ],
            'automattic' => [
                'name' => 'automattic',
                'label' => 'Automattic AI',
                'api_base' => 'https://public-api.wordpress.com/wpcom/v2/ai-api-proxy/v1/embeddings/text',
                'api_key_constant' => 'WPVDB_AUTOMATTIC_API_KEY',
                'description' => __('Automattic AI offers embedding models optimized for WordPress content.', 'wpvdb')
            ],
            'specter' => [
                'name' => 'specter',
                'label' => 'SPECTER',
                'api_base' => 'http://localhost:8000/v1/',
                'api_key_constant' => '',  // No API key needed for local server
                'description' => __('SPECTER2 is a research model for scientific document embeddings, running locally.', 'wpvdb')
            ]
        ];
        
        // Allow plugins to register additional providers
        return apply_filters('wpvdb_available_providers', $default_providers);
    }
    
    /**
     * Get a specific provider by name
     *
     * @param string $provider_name Provider name
     * @return array|null Provider details or null if not found
     */
    public static function get_provider($provider_name) {
        $providers = self::get_available_providers();
        return isset($providers[$provider_name]) ? $providers[$provider_name] : null;
    }
    
    /**
     * Get provider name by its label
     * 
     * @param string $label Provider label
     * @return string|null Provider name or null if not found
     */
    public static function get_provider_by_label($label) {
        $providers = self::get_available_providers();
        foreach ($providers as $name => $provider) {
            if (isset($provider['label']) && $provider['label'] === $label) {
                return $name;
            }
        }
        return null;
    }
    
    /**
     * Get API base URL for a provider
     * 
     * @param string $provider_name Provider name
     * @return string API base URL or empty string if not found
     */
    public static function get_api_base($provider_name) {
        $provider = self::get_provider($provider_name);
        return $provider && isset($provider['api_base']) ? $provider['api_base'] : '';
    }
}