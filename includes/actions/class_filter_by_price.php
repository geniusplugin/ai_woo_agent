<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_filter_by_price {
    public function handle_request() {
        global $wpdb;
    
        $params = $this->validate_parameters();
    
        $query = 
            "SELECT p.ID, p.post_title, pm_price.meta_value as price
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'";
    
        if ($params['category']) {
            $query .= $wpdb->prepare(
                " AND EXISTS (
                    SELECT 1 FROM {$wpdb->prefix}term_relationships tr
                    INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                    INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
                    WHERE tr.object_id = p.ID
                    AND tt.taxonomy = 'product_cat'
                    AND t.slug = %s
                )",
                sanitize_title($params['category'])
            );
        }
    
        if ($params['min_price']) {
            $query .= $wpdb->prepare(" AND CAST(pm_price.meta_value AS DECIMAL(10,2)) >= %f", $params['min_price']);
        }
        if ($params['max_price']) {
            $query .= $wpdb->prepare(" AND CAST(pm_price.meta_value AS DECIMAL(10,2)) <= %f", $params['max_price']);
        }
    
        $query .= " ORDER BY CAST(pm_price.meta_value AS DECIMAL(10,2)) " . ($params['order'] === 'asc' ? 'ASC' : 'DESC');
        $query .= $wpdb->prepare(" LIMIT %d", $params['limit']);
    
        $products = $wpdb->get_results($query);
    
        $result = [];
        foreach ($products as $product) {
            $result[] = [
                'id' => $product->ID,
                'title' => get_the_title($product->ID),
                'price' => (float)$product->price,
                'url' => get_permalink($product->ID)
            ];
        }
    
        $this->send_success(['products' => $result]);
    }
    

    private function validate_parameters() {
        $params = [
            'min_price' => isset($_GET['min_price']) ? (float)$_GET['min_price'] : null,
            'max_price' => isset($_GET['max_price']) ? (float)$_GET['max_price'] : null,
            'order' => isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc', 'desc']) 
                        ? strtolower($_GET['order']) 
                        : 'asc',
            'limit' => !empty($_GET['limit']) ? min(5, absint($_GET['limit'])) : 5,
            'category' => !empty($_GET['category']) ? sanitize_text_field($_GET['category']) : ''
        ];

        if (!$params['min_price'] && !$params['max_price'] && empty($params['category'])) {
            $this->send_success(array('info',  'At least one filter (price range or category) required'));
        }

        return $params;
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}
