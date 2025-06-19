<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Related_Products {
    public function handle_request() {
        global $wpdb;

        if (empty($_GET['product_name'])) {
            $this->send_success(array('info', 'product_name parameter is required'));
        }

        $product_name = sanitize_text_field($_GET['product_name']);
        $limit = !empty($_GET['limit']) ? min(3, absint($_GET['limit'])) : 3;
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'related';

        // First find the product
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT ID FROM {$wpdb->prefix}posts 
             WHERE post_type = 'product' 
             AND post_status = 'publish'
             AND LOWER(post_title) LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like(strtolower($product_name)) . '%'
        ));

        if (!$product) {
            $this->send_success(['products' => [], 'message' => 'No matching product found']);
            return;
        }

        switch ($type) {
            case 'cross_sells':
                $products = $this->get_cross_sells($product->ID, $limit);
                break;
            case 'upsells':
                $products = $this->get_upsells($product->ID, $limit);
                break;
            default: // related
                $products = $this->get_related($product->ID, $limit);
        }

        $this->send_success([
            'original_product' => [
                'id' => $product->ID,
                'name' => get_the_title($product->ID)
            ],
            'products' => $products
        ]);
    }

    private function get_cross_sells($product_id, $limit) {
        return $this->get_linked_products($product_id, $limit, '_crosssell_ids');
    }

    private function get_upsells($product_id, $limit) {
        return $this->get_linked_products($product_id, $limit, '_upsell_ids');
    }

    private function get_linked_products($product_id, $limit, $meta_key) {
        global $wpdb;

        $ids = get_post_meta($product_id, $meta_key, true);
        if (empty($ids)) return [];

        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, pm_price.meta_value as price
             FROM {$wpdb->prefix}posts p
             LEFT JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             WHERE p.ID IN ($ids_placeholder)
             AND p.post_type = 'product'
             AND p.post_status = 'publish'
             LIMIT %d",
            array_merge($ids, [$limit])
        ));
    }

    private function get_related($product_id, $limit) {
        global $wpdb;

        // Get related by shared categories
        return $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, pm_price.meta_value as price
             FROM {$wpdb->prefix}posts p
             INNER JOIN (
                 SELECT tr.object_id
                 FROM {$wpdb->prefix}term_relationships tr
                 INNER JOIN {$wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 WHERE tt.taxonomy = 'product_cat'
                 AND tr.object_id != %d
                 AND tr.term_taxonomy_id IN (
                     SELECT term_taxonomy_id 
                     FROM {$wpdb->prefix}term_relationships 
                     WHERE object_id = %d
                 )
                 GROUP BY tr.object_id
             ) as shared_cats ON p.ID = shared_cats.object_id
             LEFT JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             ORDER BY RAND()
             LIMIT %d",
            $product_id, $product_id, $limit
        ));
    }

    private function format_products($products) {
        return array_map(function($product) {
            return [
                'id' => $product->ID,
                'name' => $product->post_title,
                'price' => $product->price,
                'image' => get_the_post_thumbnail_url($product->ID, 'thumbnail'),
                'link' => get_permalink($product->ID)
            ];
        }, $products);
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        $data['products'] = $this->format_products($data['products'] ?? []);
        die(json_encode(['data' => $data]));
    }
}