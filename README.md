** Best WooCommerce & WordPress Full AI Agent Workflows Automation **

Empower your AI Agent with cutting-edge automation and real-time intelligence:

    🧠 Learns autonomously from past, current, and future data — zero setup required
    📱 Interacts via text, email, voice — from any device
    🤖 Executes tasks, automates workflows, and handles customer queries
    ⏰ Provides 24/7 support — nights, weekends, peak hours — without interruption
    💡 This is the free version of the WooCommerce AI Workflows Suite.

🚀 Key Features
🆓 Free Version

    🔌 No training required — connects instantly to your WooCommerce store
    🌍 Supports 180+ languages
    📦 Instant product responses: info, images, videos, order status, and more

💼 Enterprise Version

    🏢 Manages all WordPress components (core, plugins, themes)
    ⚡ Unlimited automation tasks
    🧠 Recognizes AI-driven triggers and actions

✅ Built-In Free Tasks
🛒 Order Management

    Get customer orders
    Check order status
    (Enterprise only) Add order note

📦 Product Management

    List products by category
    Search products
    View product variations
    Find related products
    Check inventory
    Get product popularity and best sellers
    Read product reviews

🏪 Store Operations

    List coupons
    Filter products by attributes or price
    Search store pages
    Retrieve tax and shipping info

💼 Enterprise-Only Features
👤 Customer Management

    Update customer email or phone
    Change billing/shipping address
    Update customer metadata

📦 Advanced Product Management

    Update stock or price
    Add product to cart
    Change product description
    Add product attribute terms
    Modify product categories

⚙️ Advanced Automation

    Generate coupon codes
    Update order metadata
    WordPress automation dashboard
    Add custom tasks
    Manage core, plugins, and themes
    Unlimited AI task reasoning
    Secure admin-level workflows

🧩 Extending with Custom Actions (Enterprise)

Add unlimited custom endpoints by following this pattern:
1. Create the endpoint URL:

?ai_action=your_action_name&param1=value1&param2=value2

This URL only needs to be added in your Genius Plugin AI Agent Dashboard.
2. Create the action file:

/wp-content/plugins/ai_woo_agent/includes/actions/class_your_action_name.php

3. Implement the class (examples below):
📂 Example 1: Theme Customizer Action
```php
<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Theme_Settings {
    public function handle_request() {
        if (empty($_GET['setting_name'])) {
            $this->send_success(['info' => 'Setting name required']);
        }

        $value = get_theme_mod($_GET['setting_name']);
        $this->send_success(['value' => $value]);
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}
```

🔌 Example 2: Plugin Data Fetcher
```php
<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Plugin_Stats {
    public function handle_request() {
        global $wpdb;

        $results = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}some_plugin_table 
            LIMIT " . absint($_GET['limit'] ?? 10)
        );

        $this->send_success(array_map([$this, 'format_row'], $results));
    }

    private function format_row($row) {
        return [
            'id' => $row->id,
            'title' => sanitize_text_field($row->title),
        ];
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}
```

🛠️ Implementation Notes

    Endpoint: ?ai_action=plugin_stats&limit=5
    Success format: $this->send_success($formatted_data)
    Error/info format: $this->send_success(['info' => 'Message'])
    File name must match class: class_plugin_stats.php (case-sensitive)

🔍 Real-World Custom Action Examples
1. WordPress Core

Action: ?ai_action=user_cleanup&inactive_days=90
AI Command: "Find users who haven't logged in for 3 months"
Outcome: Cleans up inactive user accounts
2. WooCommerce Admin

Action: ?ai_action=abandoned_carts&hours=48
AI Command: "Show high-value abandoned carts from the last 2 days"
Outcome: Identifies carts > $100 for recovery campaigns
3. Theme Optimization

Action: ?ai_action=theme_assets&type=css
AI Command: "List all unminified CSS files in theme"
Outcome: Flags performance issues for optimization

📘 Learn More:
Visit GeniusPlugin.com → [Best AI Agent](https://www.geniusplugin.com)
