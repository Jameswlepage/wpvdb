<?php
namespace WPVDB;

defined('ABSPATH') || exit;

class Query {

    /**
     * Hook into 'pre_get_posts' or a similar filter to do custom vector searching if requested.
     */
    public static function init() {
        add_filter('pre_get_posts', [__CLASS__, 'maybe_vector_search']);
    }

    /**
     * If query->get('vdb_vector_query') is set, we do a custom vector-based search.
     * This is a demonstration: a real implementation would combine or replace the standard search logic.
     *
     * For example, a developer might do:
     * $query = new WP_Query(['vdb_vector_query' => 'some text', 'posts_per_page' => 10]);
     *
     * We'll find the top matching doc_ids from pivot table, then limit WP_Query to those posts.
     */
    public static function maybe_vector_search($query) {
        // Only run in front-end or REST contexts, and only if vdb_vector_query is set.
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        if (!$query->is_main_query()) {
            return;
        }
        $vdb_query = $query->get('vdb_vector_query');
        if (empty($vdb_query)) {
            return;
        }

        // For simplicity, embed and do a fallback search. Then get the doc_ids, presumably post_id was stored as doc_id.
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpvdb_embeddings';

        // We'll do a direct call to the REST method or replicate logic from REST::handle_query.
        $api_key = apply_filters('wpvdb_default_api_key', '');
        if (!$api_key) {
            // If there's no stored key, we can't generate embeddings. We skip.
            return;
        }

        // Make sure we have a valid model name
        $model = Settings::get_default_model();
        if (empty($model)) {
            $model = 'text-embedding-3-small'; // Default fallback
        }
        
        // Get API base URL with fallback
        $api_base = Settings::get_api_base();
        if (empty($api_base)) {
            $api_base = 'https://api.openai.com/v1/';
        }

        $embedding_result = Core::get_embedding($vdb_query, $model, $api_base, $api_key);
        if (is_wp_error($embedding_result)) {
            return; // skip
        }

        $embedding = $embedding_result;
        $has_vector = Activation::has_native_vector_support();

        $limit = $query->get('posts_per_page') ?: 10;

        $doc_ids = [];

        if ($has_vector) {
            // Use vector index approach
            $vecHex = REST::convert_array_to_vector_hex($embedding);
            // doc_id presumably is the post ID, so let's do a limited query
            $sql = $wpdb->prepare("
                SELECT doc_id,
                    VEC_DISTANCE(embedding, x%s) as distance
                FROM $table_name
                GROUP BY doc_id
                ORDER BY VEC_DISTANCE(embedding, x%s)
                LIMIT %d
            ", $vecHex, $vecHex, $limit * 3 // fetch more candidates than needed
            );
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if ($rows) {
                foreach ($rows as $r) {
                    $doc_ids[] = (int) $r['doc_id'];
                }
            }
        } else {
            // Fallback: do in PHP
            $all_rows = $wpdb->get_results("SELECT doc_id, embedding FROM $table_name", ARRAY_A);
            $distances = [];
            foreach ($all_rows as $r) {
                $stored_emb = json_decode($r['embedding'], true);
                if (!is_array($stored_emb)) {
                    continue;
                }
                $d = REST::cosine_distance($embedding, $stored_emb);
                $distances[] = [
                    'doc_id'   => (int) $r['doc_id'],
                    'distance' => $d,
                ];
            }
            usort($distances, function($a, $b){return $a['distance'] <=> $b['distance'];});
            $distances = array_slice($distances, 0, $limit * 3);
            $doc_ids   = wp_list_pluck($distances, 'doc_id');
        }

        if (empty($doc_ids)) {
            // No matches, so force query to return no posts
            $query->set('post__in', [0]);
            return;
        }

        // If there are doc_ids, limit the WP query to only those
        $doc_ids = array_unique($doc_ids);
        $query->set('post__in', $doc_ids);
        $query->set('orderby', 'post__in');
    }
}