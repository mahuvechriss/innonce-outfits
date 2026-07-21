<?php
require_once __DIR__ . '/../config.php';
requireWorker();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST required']);
    exit;
}

$lat = (float)($_POST['latitude'] ?? 0);
$lng = (float)($_POST['longitude'] ?? 0);

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'error' => 'Invalid coordinates']);
    exit;
}

$db->prepare("DELETE FROM worker_locations WHERE worker_id = ?")->execute([$_SESSION['user_id']]);
$db->prepare("INSERT INTO worker_locations (worker_id, latitude, longitude) VALUES (?, ?, ?)")->execute([$_SESSION['user_id'], $lat, $lng]);

echo json_encode(['success' => true, 'latitude' => $lat, 'longitude' => $lng]);
