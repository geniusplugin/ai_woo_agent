<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Coupons_List {
    public function handle_request() {
        global $wpdb;

        $limit = !empty($_GET['limit']) ? min(10, absint($_GET['limit'])) : 10;

        $coupons = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title as code,
                    pm_amount.meta_value as amount,
                    pm_type.meta_value as discount_type,
                    pm_expiry.meta_value as expiry_date,
                    pm_minimum.meta_value as minimum_amount,
                    pm_usage.meta_value as usage_count,
                    pm_limit.meta_value as usage_limit
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'coupon_amount'
             LEFT JOIN {$wpdb->postmeta} pm_type ON p.ID = pm_type.post_id AND pm_type.meta_key = 'discount_type'
             LEFT JOIN {$wpdb->postmeta} pm_expiry ON p.ID = pm_expiry.post_id AND pm_expiry.meta_key = 'date_expires'
             LEFT JOIN {$wpdb->postmeta} pm_minimum ON p.ID = pm_minimum.post_id AND pm_minimum.meta_key = 'minimum_amount'
             LEFT JOIN {$wpdb->postmeta} pm_usage ON p.ID = pm_usage.post_id AND pm_usage.meta_key = 'usage_count'
             LEFT JOIN {$wpdb->postmeta} pm_limit ON p.ID = pm_limit.post_id AND pm_limit.meta_key = 'usage_limit'
             WHERE p.post_type = 'shop_coupon'
             AND p.post_status = 'publish'
             ORDER BY p.post_date DESC
             LIMIT %d",
            $limit
        ));

        // Ensure we always have an array, even if empty
        $coupons = is_array($coupons) ? $coupons : [];

        $formatted_coupons = array_map(function($coupon) {
            return [
                'code' => $coupon->code ?? '',
                'amount' => $coupon->amount ?? '0',
                'discount_type' => $this->get_discount_type_label($coupon->discount_type ?? ''),
                'expiry_date' => !empty($coupon->expiry_date) ? date('Y-m-d', $coupon->expiry_date) : null,
                'minimum_amount' => $coupon->minimum_amount ?? '0',
                'usage_count' => $coupon->usage_count ?? '0',
                'usage_limit' => $coupon->usage_limit ?? '',
                'status' => $this->get_coupon_status($coupon)
            ];
        }, $coupons);

        $this->send_success([
            'count' => count($formatted_coupons),
            'coupons' => $formatted_coupons
        ]);
    }

    private function get_discount_type_label($type) {
        $types = [
            'fixed_cart' => 'Cart Discount',
            'percent' => 'Percentage Discount',
            'fixed_product' => 'Product Discount',
            'percent_product' => 'Product Percentage Discount'
        ];
        return $types[$type] ?? $type;
    }

    private function get_coupon_status($coupon) {
        if (!empty($coupon->usage_limit) && ($coupon->usage_count >= $coupon->usage_limit)) {
            return 'used_up';
        }
        if (!empty($coupon->expiry_date) && ($coupon->expiry_date < time())) {
            return 'expired';
        }
        return 'active';
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}