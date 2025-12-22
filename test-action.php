<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    die("Please login first");
}
?>

<!DOCTYPE html>
<html>
<body>
    <h2>Test Form Action</h2>
    
    <h3>Current directory: <?php echo __DIR__; ?></h3>
    
    <form method="POST" action="process-payment.php">
        <input type="hidden" name="test" value="test123">
        <button type="submit">Test Submit to process-payment.php</button>
    </form>
    
    <hr>
    
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <input type="hidden" name="test" value="test456">
        <button type="submit">Test Submit to same page</button>
    </form>
    
    <?php
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h3>Form submitted!</h3>";
        echo "<pre>";
        print_r($_POST);
        echo "</pre>";
    }
    ?>
</body>
</html>