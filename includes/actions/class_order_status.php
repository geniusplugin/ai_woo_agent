<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Order_Status {
    public function handle_request() {
        global $wpdb;

       
        // Validate input
        if (empty($_GET['order_id']) || empty($_GET['email'])) {
            $this->send_success(array('info', 'Order ID and email are required'));
        }

        $order_id = (int)$_GET['order_id'];
        $email = sanitize_email($_GET['email']);

        // Get order data from HPOS
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                id as order_id,
                status as post_status,
                type as post_type,
                total_amount as total,
                payment_method,
                billing_email as customer_email
             FROM {$wpdb->prefix}wc_orders
             WHERE id = %d",
            $order_id
        ));

        if (!$order) {
            $this->send_success(array('info', 'Order not found in system'));
        }

        // Verify email match
        if (strtolower($order->customer_email) !== strtolower($email)) {
            $this->send_success(array('info', 'Order does not belong to this email'));
        }


        $this->send_success([
            'order' => $order
        ]);
    }

    private function send_error($code, $message) {
        http_response_code(400);
        die(json_encode(['error' => $code, 'message' => $message]));
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}