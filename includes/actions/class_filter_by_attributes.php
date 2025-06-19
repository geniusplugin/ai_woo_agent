<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Filter_By_Attributes {
    public function handle_request() {
        // First try standard taxonomy-based attribute system
        $taxonomy_results = $this->filter_by_taxonomy_attributes();
        
        // If no results, try the post_excerpt method
        if (empty($taxonomy_results['products'])) {
            $excerpt_results = $this->filter_by_post_excerpt();
            if (!empty($excerpt_results['products'])) {
                $excerpt_results['method'] = 'post_excerpt_fallback';
                $this->send_success($excerpt_results);
            }
        } else {
            $taxonomy_results['method'] = 'standard_taxonomy';
            $this->send_success($taxonomy_results);
        }
        
        // If both methods failed
        $this->send_success(array('info', 'No products found matching your criteria'));
    }

    private function filter_by_taxonomy_attributes() {
        global $wpdb;

        $color = isset($_GET['color']) ? sanitize_text_field($_GET['color']) : null;
        $size = isset($_GET['size']) ? sanitize_text_field($_GET['size']) : null;
        $limit = !empty($_GET['limit']) ? absint($_GET['limit']) : 10;

        $query = "SELECT DISTINCT p.ID, p.post_title
                 FROM {$wpdb->prefix}posts p
                 INNER JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
                 INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
                 WHERE p.post_type IN ('product', 'product_variation')
                 AND p.post_status = 'publish'";

        $conditions = [];
        $params = [];

        if ($color) {
            $conditions[] = "tt.taxonomy = 'pa_color' AND t.slug = %s";
            $params[] = $color;
        }

        if ($size) {
            $conditions[] = "tt.taxonomy = 'pa_size' AND t.slug = %s";
            $params[] = $size;
        }

        if (!empty($conditions)) {
            $query .= " AND (" . implode(' AND ', $conditions) . ")";
        }

        $query .= " ORDER BY p.post_date DESC LIMIT %d";
        $params[] = $limit;

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $products = $wpdb->get_results($query);

        $results = [];
        foreach ($products as $product) {
            $results[] = [
                'id' => $product->ID,
                'name' => $product->post_title,
                'link' => get_permalink($product->ID)
            ];
        }

        return ['products' => $results];
    }

    private function filter_by_post_excerpt() {
        global $wpdb;

        $color = isset($_GET['color']) ? sanitize_text_field($_GET['color']) : null;
        $size = isset($_GET['size']) ? sanitize_text_field($_GET['size']) : null;
        $limit = !empty($_GET['limit']) ? absint($_GET['limit']) : 10;

        $query = "SELECT ID, post_title, post_excerpt 
                 FROM {$wpdb->prefix}posts
                 WHERE post_type IN ('product', 'product_variation')
                 AND post_status = 'publish'";

        $conditions = [];
        $params = [];

        if ($color) {
            $conditions[] = "post_excerpt LIKE %s";
            $params[] = '%Color: '.$wpdb->esc_like($color).'%';
        }

        if ($size) {
            $conditions[] = "post_excerpt LIKE %s";
            $params[] = '%Size: '.$wpdb->esc_like($size).'%';
        }

        if (!empty($conditions)) {
            $query .= " AND (" . implode(' AND ', $conditions) . ")";
        }

        $query .= " ORDER BY post_date DESC LIMIT %d";
        $params[] = $limit;

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $products = $wpdb->get_results($query);

        $results = [];
        foreach ($products as $product) {
            $attributes = [];
            $pairs = explode(',', $product->post_excerpt);
            foreach ($pairs as $pair) {
                $parts = explode(':', trim($pair));
                if (count($parts) === 2) {
                    $key = trim($parts[0]);
                    $value = trim($parts[1]);
                    $attributes[strtolower($key)] = $value;
                }
            }

            $results[] = [
                'id' => $product->ID,
                'name' => $product->post_title,
                'attributes' => $attributes,
                'link' => get_permalink($product->ID)
            ];
        }

        return ['products' => $results];
    }

    private function send_error($code, $message) {
        http_response_code(404);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}