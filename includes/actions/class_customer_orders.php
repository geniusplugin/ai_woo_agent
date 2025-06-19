<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Customer_Orders {
    public function handle_request() {
        global $wpdb;

        if (empty($_GET['email'])) {
            $this->send_success(array('info', 'Customer email required'));
        }
        if (empty($_GET['order_id'])) {
            $this->send_success(array('info', 'Order ID required'));
        }

        $email = sanitize_email($_GET['email']);
        $order_id = absint($_GET['order_id']);
        $limit = !empty($_GET['limit']) ? absint($_GET['limit']) : 5;

        // First verify the order belongs to this customer
        $valid_order = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}wc_orders 
             WHERE id = %d AND billing_email = %s",
            $order_id,
            $email
        ));

        if (!$valid_order) {
            $this->send_success(array('info', 'Order not found for this customer'));
        }

        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                id as ID,
                status as post_status,
                date_created_gmt as post_date,
                total_amount as total,
                payment_method
             FROM {$wpdb->prefix}wc_orders
             WHERE billing_email = %s
             ORDER BY date_created_gmt DESC
             LIMIT %d",
            $email,
            $limit
        ));

        $this->send_success(['orders' => $orders]);
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}