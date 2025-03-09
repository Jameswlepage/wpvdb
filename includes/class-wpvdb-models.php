<?php
namespace WPVDB;

defined('ABSPATH') || exit;

/**
 * Model registry for WPVDB
 * 
 * Centralized registry of embedding models with their capabilities and metadata
 */
class Models {
    /**
     * Get all registered embedding models
     *
     * @return array Array of models by provider
     */
    public static function get_available_models() {
        $default_models = [
            'openai' => [
                'text-embedding-3-small' => [
                    'name' => 'text-embedding-3-small',
                    'label' => 'Ada 3 Small (1536 dimensions)',
                    'dimensions' => 1536,
                    'provider' => 'openai'
                ],
                'text-embedding-3-large' => [
                    'name' => 'text-embedding-3-large',
                    'label' => 'Ada 3 Large (3072 dimensions)',
                    'dimensions' => 3072,
                    'provider' => 'openai'
                ],
                'text-embedding-ada-002' => [
                    'name' => 'text-embedding-ada-002',
                    'label' => 'Ada 2 (Legacy)',
                    'dimensions' => 1536,
                    'provider' => 'openai'
                ]
            ],
            'automattic' => [
                'a8cai-embeddings-small-1' => [
                    'name' => 'a8cai-embeddings-small-1',
                    'label' => 'Automattic Small',
                    'dimensions' => 512,
                    'provider' => 'automattic'
                ],
                'a8cai-embeddings-large-1' => [
                    'name' => 'a8cai-embeddings-large-1',
                    'label' => 'Automattic Large',
                    'dimensions' => 1024,
                    'provider' => 'automattic'
                ]
            ],
            'specter' => [
                'specter2' => [
                    'name' => 'specter2',
                    'label' => 'SPECTER2',
                    'dimensions' => 768,
                    'provider' => 'specter'
                ]
            ]
        ];
        
        // Allow plugins to register additional models or modify existing ones
        return apply_filters('wpvdb_available_models', $default_models);
    }
    
    /**
     * Get models for a specific provider
     *
     * @param string $provider Provider name
     * @return array Models for the provider
     */
    public static function get_provider_models($provider) {
        $models = self::get_available_models();
        return isset($models[$provider]) ? $models[$provider] : [];
    }
    
    /**
     * Get a specific model by provider and name
     *
     * @param string $provider Provider name
     * @param string $model_name Model name
     * @return array|null Model details or null if not found
     */
    public static function get_model($provider, $model_name) {
        $provider_models = self::get_provider_models($provider);
        return isset($provider_models[$model_name]) ? $provider_models[$model_name] : null;
    }
    
    /**
     * Get default model for a provider
     * 
     * @param string $provider Provider name
     * @return string Default model name
     */
    public static function get_default_model_for_provider($provider) {
        switch ($provider) {
            case 'openai':
                return 'text-embedding-3-small';
            case 'automattic':
                return 'a8cai-embeddings-small-1';
            case 'specter':
                return 'specter2';
            default:
                // For custom providers, return the first available model
                $provider_models = self::get_provider_models($provider);
                return !empty($provider_models) ? array_key_first($provider_models) : '';
        }
    }
}