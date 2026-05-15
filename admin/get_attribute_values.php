<?php
session_start();
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

if (!isset($_GET['attribute_id']) || !is_numeric($_GET['attribute_id'])) {
    echo json_encode(['values' => []]);
    exit;
}

$attribute_id = (int)$_GET['attribute_id'];

$stmt = $db->prepare("SELECT value, sort_order FROM attribute_values WHERE attribute_id = ? ORDER BY sort_order");
$stmt->bind_param('i', $attribute_id);
$stmt->execute();
$result = $stmt->get_result();

$values = [];
while ($row = $result->fetch_assoc()) {
    $values[] = ['value' => $row['value']];
}

echo json_encode(['values' => $values]);
?>