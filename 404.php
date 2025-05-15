<?php
require_once('utils.php');

// Set appropriate HTTP status code
http_response_code(404);

$client = getTwitterClient();
$pageTitle = "Page Not Found - Flirb Mobile";
include('layout_header.php');
?>

<div class="title">404 - Page Not Found</div>

<div style="padding: 15px; text-align: center;">
    <p>Sorry, the page you were looking for doesn't exist.</p>
    <p style="margin-top: 15px;">The page may have been moved or deleted, or you might have typed the wrong URL.</p>
</div>

<div style="padding: 15px; text-align: center;">
    <a href="index.php">Return to Home</a>
    <?php if ($client->isLoggedIn()): ?>
        <br><br>
        <a href="profile.php">Go to Your Profile</a>
    <?php else: ?>
        <br><br>
        <a href="login.php">Login</a>
    <?php endif; ?>
</div>

<?php include('layout_footer.php'); ?>