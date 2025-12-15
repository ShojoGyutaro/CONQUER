<?php
session_start();
require_once 'config/database.php';

// Check admin authentication
if(!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$trainerId = $_GET['id'] ?? 0;

if($trainerId) {
    try {
        $pdo = Database::getInstance()->getConnection();
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Get user_id from trainer
        $stmt = $pdo->prepare("SELECT user_id FROM trainers WHERE id = ?");
        $stmt->execute([$trainerId]);
        $trainer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($trainer) {
            // Delete from trainers table
            $stmt = $pdo->prepare("DELETE FROM trainers WHERE id = ?");
            $stmt->execute([$trainerId]);
            
            // Update user type to inactive member or delete (choose one)
            // Option 1: Delete user completely
            // $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            // Option 2: Deactivate user
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
            $stmt->execute([$trainer['user_id']]);
            
            $pdo->commit();
            $_SESSION['success_message'] = "Trainer deleted successfully!";
        }
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error deleting trainer: " . $e->getMessage();
    }
}

header('Location: admin-trainers.php');
exit();