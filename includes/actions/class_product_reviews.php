<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Product_Reviews {
    public function handle_request() {
        global $wpdb;

        // Handle different types of requests
        if (isset($_GET['product_name'])) {
            $this->get_reviews_by_product();
        } elseif (isset($_GET['min_rating'])) {
            $this->get_products_by_rating();
        } else {
            $this->send_success(array('info',  'Specify either product_name or min_rating'));
        }
    }

    private function get_reviews_by_product() {
        global $wpdb;

        $product_name = sanitize_text_field($_GET['product_name']);
        $limit = !empty($_GET['limit']) ? min(3, absint($_GET['limit'])) : 3;


        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_content, p.post_excerpt
             FROM {$wpdb->prefix}posts p
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             AND LOWER(p.post_title) LIKE %s
             LIMIT 1",
            '%' . $wpdb->esc_like(strtolower($product_name)) . '%'
        ));

        if (empty($products)) {
            $this->send_success(['reviews' => [], 'message' => 'No products found']);
            return;
        }

        $product = $products[0];
        $reviews = $wpdb->get_results($wpdb->prepare(
            "SELECT c.comment_ID, c.comment_author, c.comment_content, 
                    c.comment_date, cm.meta_value as rating
             FROM {$wpdb->prefix}comments c
             LEFT JOIN {$wpdb->prefix}commentmeta cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'rating'
             WHERE c.comment_post_ID = %d
             AND c.comment_type = 'review'
             AND c.comment_approved = '1'
             ORDER BY c.comment_date DESC
             LIMIT %d",
            $product->ID,
            $limit
        ));

        $average_rating = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(cm.meta_value)
             FROM {$wpdb->prefix}commentmeta cm
             INNER JOIN {$wpdb->prefix}comments c ON cm.comment_id = c.comment_ID
             WHERE c.comment_post_ID = %d
             AND cm.meta_key = 'rating'
             AND c.comment_approved = '1'",
            $product->ID
        ));

        $this->send_success([
            'product' => [
                'id' => $product->ID,
                'name' => $product->post_title,
                'description' => wp_strip_all_tags($product->post_content),
                'short_description' => wp_strip_all_tags($product->post_excerpt),
                'review_count' => count($reviews),
                'average_rating' => round((float)$average_rating, 2)
            ],
            'reviews' => array_map([$this, 'format_review'], $reviews)
        ]);
    }

    private function get_products_by_rating() {
        global $wpdb;

        $min_rating = floatval($_GET['min_rating']);
        $limit = isset($_GET['limit']) ? absint($_GET['limit']) : 3;
        if($limit > 3) $limit = 3;
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, 
                    AVG(cm.meta_value) as avg_rating,
                    COUNT(c.comment_ID) as review_count
             FROM {$wpdb->prefix}posts p
             INNER JOIN {$wpdb->prefix}comments c ON p.ID = c.comment_post_ID AND c.comment_type = 'review'
             INNER JOIN {$wpdb->prefix}commentmeta cm ON c.comment_ID = cm.comment_id AND cm.meta_key = 'rating'
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             GROUP BY p.ID
             HAVING avg_rating >= %f
             ORDER BY avg_rating DESC, review_count DESC
             LIMIT %d",
            $min_rating,
            $limit
        ));

        $this->send_success([
            'products' => array_map([$this, 'format_rated_product'], $products)
        ]);
    }

    private function format_review($review) {
        return [
            'id' => $review->comment_ID,
            'author' => $review->comment_author,
            'content' => $review->comment_content,
            'date' => $review->comment_date,
            'rating' => (int)$review->rating
        ];
    }

    private function format_rated_product($product) {
        return [
            'id' => $product->ID,
            'name' => $product->post_title,
            'average_rating' => round((float)$product->avg_rating, 2),
            'review_count' => (int)$product->review_count,
            'link' => get_permalink($product->ID)
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