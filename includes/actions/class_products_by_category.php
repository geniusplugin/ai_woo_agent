<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Products_By_Category {
    public function handle_request() {
        global $wpdb;

        if (empty($_GET['category'])) {
            $this->send_success(array('info', 'Category parameter required'));
        }

        $category = $this->normalize_string($_GET['category']);
        $limit = !empty($_GET['limit']) ? min(3, absint($_GET['limit'])) : 3;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_content, p.post_excerpt,
                    pm_price.meta_value as price,
                    pm_stock.meta_value as stock,
                    pm_thumbnail.meta_value as thumbnail_id,
                    t.name as category_name
            FROM {$wpdb->prefix}posts p
            INNER JOIN {$wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->prefix}terms t ON tt.term_id = t.term_id
            LEFT JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$wpdb->prefix}postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$wpdb->prefix}postmeta pm_thumbnail ON p.ID = pm_thumbnail.post_id AND pm_thumbnail.meta_key = '_thumbnail_id'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_cat'
            AND LOWER(t.name) LIKE %s
            LIMIT %d",
            '%' . $wpdb->esc_like($category) . '%',
            $limit
        ));

        $this->send_success(array_map([$this, 'format_product'], $results));
    }

    private function format_product($product) {
        $image_url = $product->thumbnail_id ? wp_get_attachment_url($product->thumbnail_id) : '';
        
        return [
            'id' => $product->ID,
            'name' => $product->post_title,
            'description' => wp_strip_all_tags($product->post_content),
            'short_description' => wp_strip_all_tags($product->post_excerpt),
            'price' => $product->price,
            'stock' => $product->stock,
            'image' => $image_url,
            'category' => $product->category_name,
            'link' => get_permalink($product->ID)
        ];
    }

    private function normalize_string($str) {
        return function_exists('mb_strtolower') ? mb_strtolower($str, 'UTF-8') : strtolower($str);
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}