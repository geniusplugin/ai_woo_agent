<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Product_Search {
    
    public function handle_request() {
        global $wpdb;
    
        if (empty($_GET['query'])) {
            $this->send_success(array('info',  'Query parameter required'));
        }
    
        $query_raw = $this->normalize_string($_GET['query']);
        $terms = array_filter(explode(' ', $query_raw));
        $where_clauses = [];
        $params = [];
    
        foreach ($terms as $term) {
            $where_clauses[] = "LOWER(p.post_title) LIKE %s";
            $params[] = '%' . $wpdb->esc_like($term) . '%';
        }
    
        $where_sql = implode(' AND ', $where_clauses);
        //$limit = !empty($_GET['limit']) ? min(2, absint($_GET['limit'])) : 2;
        $limit = 3; // force limit whatever GET limit is - A must for combined words search
        $sql = "
            SELECT p.ID, p.post_title, p.post_content, p.post_excerpt,
                pm_price.meta_value as price,
                pm_sku.meta_value as sku,
                pm_stock.meta_value as stock,
                pm_thumbnail.meta_value as thumbnail_id,
                pm_attributes.meta_value as attributes
            FROM {$wpdb->prefix}posts p
            LEFT JOIN {$wpdb->prefix}postmeta pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = '_price'
            LEFT JOIN {$wpdb->prefix}postmeta pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
            LEFT JOIN {$wpdb->prefix}postmeta pm_stock ON p.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
            LEFT JOIN {$wpdb->prefix}postmeta pm_thumbnail ON p.ID = pm_thumbnail.post_id AND pm_thumbnail.meta_key = '_thumbnail_id'
            LEFT JOIN {$wpdb->prefix}postmeta pm_attributes ON p.ID = pm_attributes.post_id AND pm_attributes.meta_key = '_product_attributes'
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            AND $where_sql
            LIMIT %d
        ";
    
        $params[] = $limit;
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
    
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
            'sku' => $product->sku,
            'stock' => $product->stock,
            'image' => $image_url,
            'link' => get_permalink($product->ID),
            'attributes' => $this->get_attributes($product)
        ];
    }

    private function get_attributes($product) {
        $attributes = [];
        if (!empty($product->attributes)) {
            $product_attributes = maybe_unserialize($product->attributes);
            
            if (is_array($product_attributes)) {
                foreach ($product_attributes as $attr_key => $attr_data) {
                    $attribute_name = wc_attribute_label($attr_key);
                    
                    if (!empty($attr_data['is_taxonomy'])) {
                        $terms = wp_get_post_terms($product->ID, $attr_key);
                        if (!is_wp_error($terms) && !empty($terms)) {
                            $attributes[$attribute_name] = wp_list_pluck($terms, 'name');
                        }
                    } else {
                        if (!empty($attr_data['value'])) {
                            $attributes[$attribute_name] = array_map('trim', explode('|', $attr_data['value']));
                        }
                    }
                }
            }
        }
        return $attributes;
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