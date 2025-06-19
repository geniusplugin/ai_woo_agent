<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Tax_Shipping_Info {
    public function handle_request() {
        $response = [
            'tax' => $this->get_tax_info(),
            'shipping' => $this->get_shipping_info()
        ];
        
        $this->send_success($response);
    }

    private function get_tax_info() {
        return [
            'prices_include_tax' => get_option('woocommerce_prices_include_tax') === 'yes',
            'tax_based_on' => get_option('woocommerce_tax_based_on'),
            'default_country_rate' => $this->get_tax_rates(),
            'tax_display_cart' => get_option('woocommerce_tax_display_cart'),
            'tax_display_shop' => get_option('woocommerce_tax_display_shop')
        ];
    }

    private function get_tax_rates() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT tax_rate_country, tax_rate_state, tax_rate 
             FROM {$wpdb->prefix}woocommerce_tax_rates
             WHERE tax_rate_priority = 1
             ORDER BY tax_rate_order"
        );
    }

    private function get_shipping_info() {
        // Get shipping zones
        $zones = WC_Shipping_Zones::get_zones();
        $shipping_methods = [];
        
        foreach ($zones as $zone) {
            $zone_info = [
                'zone_name' => $zone['zone_name'],
                'zone_locations' => $zone['zone_locations'],
                'methods' => []
            ];
            
            foreach ($zone['shipping_methods'] as $method) {
                $zone_info['methods'][] = [
                    'id' => $method->id,
                    'title' => $method->title,
                    'enabled' => $method->enabled === 'yes',
                    'settings' => $method->instance_settings
                ];
            }
            
            $shipping_methods[] = $zone_info;
        }

        return [
            'calculation_method' => get_option('woocommerce_shipping_cost_requires_address'),
            'shipping_methods' => $shipping_methods,
            'free_shipping_minimum' => get_option('woocommerce_free_shipping_min_amount'),
            'default_shipping_class' => get_option('woocommerce_default_shipping_class')
        ];
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}