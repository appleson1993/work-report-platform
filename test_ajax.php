<?php
require_once 'config/database.php';
require_once 'config/functions.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $data = [
            'received_action' => $action,
            'post_data' => $_POST,
            'ajax_flag' => isset($_POST['ajax']) ? $_POST['ajax'] : 'not set'
        ];
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Only POST allowed']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
