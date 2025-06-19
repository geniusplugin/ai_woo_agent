<?php
/*
Plugin Name: AI WooCommerce Agent
Description: Modular WooCommerce data access for AI
Version: 2.0
*/

if (!defined('ABSPATH')) exit;

// Configuration
define('AI_IP_CHECK_URL', 'https://www.myplugin.pro/auth_wp_api.php');
define('AI_CACHE_EXPIRE', 3600);
define('AI_PLUGIN_DIR', __DIR__);

// Error logging
ini_set('log_errors', 1);
ini_set('error_log', AI_PLUGIN_DIR . '/.error.log');

// Register actions
add_action('parse_request', function($wp) {
    if (!isset($_GET['ai_action'])) return;
    
    header('Content-Type: application/json');
    
    // Verify IP first
    define('ai_woo-agent', 'ai_woo-agent');
    require_once __DIR__ . '/includes/class-ip-verifier.php';

    if (!AI_IP_Verifier::is_ip_authorized()) {
        http_response_code(403);
        die(/*json_encode(['error' => 'IP_NOT_AUTHORIZED'])*/);
    }

    // Dynamic action handler loading
    $action = sanitize_key($_GET['ai_action']);
    $action_file = __DIR__ . "/includes/actions/class_{$action}.php";
    
    if (!file_exists($action_file)) {
        http_response_code(400);
         die(/*json_encode(['error' => 'INVALID_ACTION '.$action])*/);
    }


    require_once $action_file;
    $action_class = 'AI_Action_' . str_replace(' ', '_', ucwords(str_replace('_', ' ', $action)));
    
    if (!class_exists($action_class)) {
        http_response_code(500);
         die(/*json_encode(['error' => 'ACTION_NOT_CONFIGURED'])*/);
    }

    $handler = new $action_class();
    $handler->handle_request();
    exit;
});