<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Inventory_Check {
    public function handle_request() {
        global $wpdb;

        if (empty($_GET['product_id'])) {
            $this->send_success(['info' => 'Product identifier required']);
        }

        $identifier = sanitize_text_field($_GET['product_id']);
        $product = null;

        // Search by name first
        $product = $this->search_by_name($identifier);
        
        // If not found and identifier is numeric, search by ID
        if (!$product && is_numeric($identifier)) {
            $product = $this->search_by_id(absint($identifier));
        }
        
        // If still not found, search by SKU
        if (!$product) {
            $product = $this->search_by_sku($identifier);
        }

        if (!$product) {
            $this->send_success(['info' => 'Product not found']);
        }

        $this->send_success([
            'product' => $product,
            'in_stock' => $product->stock > 0,
            'found_by' => $product->found_by // Added to show which method worked
        ]);
    }

    private function search_by_name($name) {
        global $wpdb;
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT p.ID, p.post_title, 
                    pm_stock.meta_value as stock,
                    pm_price.meta_value as price,
                    pm_backorders.meta_value as backorders,
                    pm_sku.meta_value as sku,
                    pm_thumbnail.meta_value as thumbnail_id,
                    'name' as found_by
            FROM {$wpdb->prefix}posts p
            LEFT JOIN {$wpdb->prefix}postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$wpdb->prefix}postmeta pm_backorders ON p.ID = pm_backorders.post_id AND pm_backorders.meta_key = '_backorders'
            LEFT JOIN {$wpdb->prefix}postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->prefix}postmeta pm_thumbnail ON p.ID = pm_thumbnail.post_id AND pm_thumbnail.meta_key = '_thumbnail_id'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND p.post_title LIKE %s
            LIMIT 1",
            '%' . $wpdb->esc_like($name) . '%'
        ));
        
        return $product;
    }

    private function search_by_id($id) {
        global $wpdb;
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT p.ID, p.post_title, 
                    pm_stock.meta_value as stock,
                    pm_price.meta_value as price,
                    pm_backorders.meta_value as backorders,
                    pm_sku.meta_value as sku,
                    pm_thumbnail.meta_value as thumbnail_id,
                    'id' as found_by
            FROM {$wpdb->prefix}posts p
            LEFT JOIN {$wpdb->prefix}postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$wpdb->prefix}postmeta pm_backorders ON p.ID = pm_backorders.post_id AND pm_backorders.meta_key = '_backorders'
            LEFT JOIN {$wpdb->prefix}postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->prefix}postmeta pm_thumbnail ON p.ID = pm_thumbnail.post_id AND pm_thumbnail.meta_key = '_thumbnail_id'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND p.ID = %d",
            $id
        ));
        
        return $product;
    }

    private function search_by_sku($sku) {
        global $wpdb;
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT p.ID, p.post_title, 
                    pm_stock.meta_value as stock,
                    pm_price.meta_value as price,
                    pm_backorders.meta_value as backorders,
                    pm_sku.meta_value as sku,
                    pm_thumbnail.meta_value as thumbnail_id,
                    'sku' as found_by
            FROM {$wpdb->prefix}posts p
            LEFT JOIN {$wpdb->prefix}postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$wpdb->prefix}postmeta pm_backorders ON p.ID = pm_backorders.post_id AND pm_backorders.meta_key = '_backorders'
            LEFT JOIN {$wpdb->prefix}postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->prefix}postmeta pm_thumbnail ON p.ID = pm_thumbnail.post_id AND pm_thumbnail.meta_key = '_thumbnail_id'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND pm_sku.meta_value = %s
            LIMIT 1",
            $sku
        ));
        
        return $product;
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}