<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Product_Popularity {
    public function handle_request() {
        global $wpdb;

        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'sales';
        $limit = !empty($_GET['limit']) ? min(3, absint($_GET['limit'])) : 3;

        // Validate type
        $valid_types = ['sales', 'views'];
        if (!in_array($type, $valid_types)) {
            $this->send_success(array('info',  'Invalid type. Use "sales" or "views"'));
        }

        $products = $this->get_popular_products($type, $limit);
        $this->send_success(['products' => $products]);
    }

    private function get_popular_products($type, $limit) {
        global $wpdb;

        $meta_key = ($type === 'sales') ? 'total_sales' : '_product_views';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_content, p.post_excerpt,
                    pm.meta_value as popularity,
                    pm_price.meta_value as price,
                    pm_thumb.meta_value as thumbnail_id
             FROM {$wpdb->prefix}posts p
             LEFT JOIN {$wpdb->prefix}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = %s
             LEFT JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             LEFT JOIN {$wpdb->prefix}postmeta pm_thumb ON p.ID = pm_thumb.post_id AND pm_thumb.meta_key = '_thumbnail_id'
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
             LIMIT %d",
            $meta_key,
            $limit
        ));

        return array_map(function($product) {
            return [
                'id' => $product->ID,
                'name' => $product->post_title,
                'description' => wp_strip_all_tags($product->post_content),
                'short_description' => wp_strip_all_tags($product->post_excerpt),
                'popularity' => (int)$product->popularity,
                'price' => $product->price,
                'image' => $product->thumbnail_id ? wp_get_attachment_url($product->thumbnail_id) : '',
                'link' => get_permalink($product->ID)
            ];
        }, $results);
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}