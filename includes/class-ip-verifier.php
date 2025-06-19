<?php
if (!defined('ai_woo-agent')) exit;

class AI_IP_Verifier {
    public static function is_ip_authorized() {
       
        
        $request_ip = $_SERVER['REMOTE_ADDR'];
        $allowed_ip = get_transient('ai_allowed_ip');
        
        if (false === $allowed_ip) {
            $allowed_ip = self::fetch_allowed_ip();
            set_transient('ai_allowed_ip', $allowed_ip, AI_CACHE_EXPIRE);
        }
        return $request_ip === $allowed_ip;
    }

    private static function fetch_allowed_ip() {
        $response = wp_remote_get(AI_IP_CHECK_URL, ['timeout' => 5]);
        
        if (is_wp_error($response)) {
            error_log('AI Agent IP fetch failed: ' . $response->get_error_message());
            return false;
        }

        $ip = trim(wp_remote_retrieve_body($response));
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : false;
    }
}