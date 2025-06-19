<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Page_Search {
    public function handle_request() {
        global $wpdb;

        if (empty($_GET['search_terms'])) {
            $this->send_success(array('info',   'Search terms are required'));
        }

        $search_terms = sanitize_text_field($_GET['search_terms']);
        $terms = array_filter(
            explode(' ', $search_terms),
            function($term) {
                return strlen($term) > 2; // Ignore short words
            }
        );

        if (empty($terms)) {
            $this->send_success(array('info',   'No valid search terms provided'));
        }

        $results = $this->search_content($terms);
        $this->send_success([
            'matches' => count($results),
            'results' => $results
        ]);
    }

    private function search_content($terms) {
        global $wpdb;
    
        // Prepare LIKE conditions for each term
        $like_conditions = array_map(function($term) use ($wpdb) {
            $like_term = '%' . $wpdb->esc_like($term) . '%';
            return $wpdb->prepare("(p.post_content LIKE %s OR p.post_title LIKE %s)", $like_term, $like_term);
        }, $terms);
    
        // Prepare relevance calculation for each term
        $relevance_parts = array_map(function($term) use ($wpdb) {
            $term_length = strlen($term);
            return $wpdb->prepare(
                "(LENGTH(LOWER(p.post_title)) - LENGTH(REPLACE(LOWER(p.post_title), %s, ''))) / %d", // Removed extra (
                strtolower($term),
                $term_length
            );
        }, $terms);
        
    
        // Build the main query
        $query = "SELECT p.ID, p.post_title, p.post_content, p.post_type,
                    (
                        SELECT COUNT(*) 
                        FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE tr.object_id = p.ID AND tt.taxonomy = 'category'
                    ) as category_count,
                    (
                        SELECT COUNT(*) 
                        FROM {$wpdb->term_relationships} tr
                        JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        WHERE tr.object_id = p.ID AND tt.taxonomy = 'post_tag'
                    ) as tag_count
             FROM {$wpdb->posts} p
             WHERE p.post_status = 'publish'
             AND p.post_type IN ('post', 'page')
             AND (" . implode(' OR ', $like_conditions) . ")
             ORDER BY (" . implode(' + ', $relevance_parts) . ") DESC,
                    category_count DESC,
                    tag_count DESC
             LIMIT 2";
    
        $posts = $wpdb->get_results($query);
    
        return array_map(function($post) {
            // Strip shortcodes and tags
            $content = strip_shortcodes(strip_tags($post->post_content));
            
            // Get the first 800 characters with whole words
            if (strlen($content) > 800) {
                $content = substr($content, 0, 800);
                $content = substr($content, 0, strrpos($content, ' ')) . '...';
            }
    
            return [
                'id' => $post->ID,
                'title' => $post->post_title,
                'type' => $post->post_type,
                'excerpt' => $content,
                'link' => get_permalink($post->ID)
            ];
        }, $posts);
    }
    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}