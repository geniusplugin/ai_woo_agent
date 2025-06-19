<?php
if (!defined('ai_woo-agent')) exit;

class AI_Action_Check_Install {
    public function handle_request() {

        $this->send_success([
            'success' => "success"
        ]);
    }

    private function send_success($data) {
        die(json_encode(['data' => $data]));
    }
}