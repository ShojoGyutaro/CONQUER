<?php
session_start();
require_once 'config/database.php';

if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $pdo = Database::getInstance()->getConnection();
    $classId = $_POST['id'];
    
    // Update class status to cancelled
    $stmt = $pdo->prepare("UPDATE classes SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$classId]);
    
    // Also cancel all bookings for this class
    $bookingStmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE class_id = ? AND status = 'confirmed'");
    $bookingStmt->execute([$classId]);
    
    echo json_encode(['success' => true, 'message' => 'Class cancelled successfully']);
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}