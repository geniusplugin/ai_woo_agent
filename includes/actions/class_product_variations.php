<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Product_Variations {
    public function handle_request() {
        global $wpdb;

        if (empty($_GET['product_name'])) {
            $this->send_success(array('info', 'Product name is required'));
        }

        $product_name = $this->normalize_string(sanitize_text_field($_GET['product_name']));
        $limit = !empty($_GET['limit']) ? min(1, absint($_GET['limit'])) : 1;

        // First find matching products - FIXED PREPARE CALL
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->prefix}posts 
             WHERE post_type = 'product' 
             AND post_status = 'publish'
             AND LOWER(post_title) LIKE %s
             LIMIT %d",  // Added LIMIT placeholder
            '%' . $wpdb->esc_like($product_name) . '%',
            $limit
        ));

        if (empty($products)) {
            $this->send_success(array('info', 'No products found matching your query'));
        }

        $response = [];
        foreach ($products as $product) {
            if ($this->is_variable_product($product->ID)) {
                $variations = $this->get_variations($product->ID);
                $attributes = $this->get_attributes($product->ID);

                $response[] = [
                    'product' => [
                        'id' => $product->ID,
                        'name' => $product->post_title,
                        'link' => get_permalink($product->ID),
                        'attributes' => $attributes
                    ],
                    'variations' => $variations
                ];
            }
        }

        if (empty($response)) {
            $this->send_success(array('info', 'The found product has no variations'));
        }

        $this->send_success([
            'matches' => count($response),
            'results' => $response
        ]);
    }

    private function is_variable_product($product_id) {
        $terms = wp_get_post_terms($product_id, 'product_type');
        return !is_wp_error($terms) && !empty($terms) && $terms[0]->slug === 'variable';
    }

    private function get_variations($parent_id) {
        global $wpdb;
        
        $variations = $wpdb->get_results($wpdb->prepare(
            "SELECT v.ID, v.post_title,
                    pm_price.meta_value as price,
                    pm_regular_price.meta_value as regular_price,
                    pm_sale_price.meta_value as sale_price,
                    pm_stock.meta_value as stock,
                    pm_sku.meta_value as sku,
                    pm_attributes.meta_value as attributes,
                    pm_image.meta_value as image_id
             FROM {$wpdb->prefix}posts v
             LEFT JOIN {$wpdb->prefix}postmeta pm_price ON v.ID = pm_price.post_id AND pm_price.meta_key = '_price'
             LEFT JOIN {$wpdb->prefix}postmeta pm_regular_price ON v.ID = pm_regular_price.post_id AND pm_regular_price.meta_key = '_regular_price'
             LEFT JOIN {$wpdb->prefix}postmeta pm_sale_price ON v.ID = pm_sale_price.post_id AND pm_sale_price.meta_key = '_sale_price'
             LEFT JOIN {$wpdb->prefix}postmeta pm_stock ON v.ID = pm_stock.post_id AND pm_stock.meta_key = '_stock'
             LEFT JOIN {$wpdb->prefix}postmeta pm_sku ON v.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'
             LEFT JOIN {$wpdb->prefix}postmeta pm_attributes ON v.ID = pm_attributes.post_id AND pm_attributes.meta_key = '_product_attributes'
             LEFT JOIN {$wpdb->prefix}postmeta pm_image ON v.ID = pm_image.post_id AND pm_image.meta_key = '_thumbnail_id'
             WHERE v.post_type = 'product_variation'
             AND v.post_status = 'publish'
             AND v.post_parent = %d
             LIMIT 15",
            $parent_id
        ));

        return array_map(function($variation) {
            $attrs = maybe_unserialize($variation->attributes);
            $formatted_attrs = [];
            
            if (is_array($attrs)) {
                foreach ($attrs as $key => $value) {
                    $clean_key = str_replace('attribute_', '', $key);
                    $formatted_attrs[$clean_key] = $value;
                }
            }

            $image_url = $variation->image_id ? wp_get_attachment_url($variation->image_id) : '';

            return [
                'id' => $variation->ID,
                'name' => $variation->post_title,
                'price' => $variation->price,
                'regular_price' => $variation->regular_price,
                'sale_price' => $variation->sale_price,
                'stock' => $variation->stock,
                'sku' => $variation->sku,
                'image' => $image_url,
                'attributes' => $formatted_attrs,
                'in_stock' => $variation->stock > 0,
                'on_sale' => !empty($variation->sale_price) && $variation->sale_price < $variation->regular_price
            ];
        }, $variations);
    }

    private function get_attributes($product_id) {
        $attributes = [];
        $product_attributes = maybe_unserialize(get_post_meta($product_id, '_product_attributes', true));
        
        if (is_array($product_attributes)) {
            foreach ($product_attributes as $attr_key => $attr_data) {
                if (empty($attr_data['is_visible']) || empty($attr_data['is_variation'])) continue;
                
                $attribute_name = wc_attribute_label($attr_key);
                $options = [];
                
                if ($attr_data['is_taxonomy']) {
                    $terms = wp_get_post_terms($product_id, $attr_key);
                    if (!is_wp_error($terms)) {
                        $options = wp_list_pluck($terms, 'name');
                    }
                } else {
                    $options = array_map('trim', explode('|', $attr_data['value']));
                }
                
                if (!empty($options)) {
                    $attributes[$attribute_name] = $options;
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