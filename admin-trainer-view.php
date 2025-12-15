<?php
session_start();
require_once 'config/database.php';

// Check admin authentication
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$trainerId = $_GET['id'] ?? 0;

try {
    $pdo = Database::getInstance()->getConnection();
    
    $query = "
        SELECT 
            t.*, 
            u.full_name, 
            u.email, 
            u.username,
            u.is_active,
            u.created_at
        FROM trainers t
        JOIN users u ON t.user_id = u.id
        WHERE t.id = ? AND u.user_type = 'trainer'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$trainerId]);
    $trainer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$trainer) {
        header('Location: admin-trainers.php');
        exit();
    }
    
} catch(PDOException $e) {
    error_log("Fetch trainer error: " . $e->getMessage());
    header('Location: admin-trainers.php');
    exit();
}
?>
<!-- Add HTML for viewing trainer details -->