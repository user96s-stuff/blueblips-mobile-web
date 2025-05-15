<?php
require_once('utils.php');

// Check if user is logged in
requireLogin();

$client = getTwitterClient();
$error = getError();
$success = false;

// Handle new post submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post'])) {
    $status = trim($_POST['post']);
    
    if (empty($status)) {
        setError("Status cannot be empty");
    } else if (strlen($status) > 140) {
        setError("Status cannot exceed 140 characters");
    } else {
        $result = $client->postTweet($status);
        
        if ($result) {
            // Post was successful, redirect to home page
            header("Location: index.php");
            exit;
        } else {
            setError("Failed to post your status. Please try again.");
        }
    }
}

$pageTitle = "New Post - Flirb Mobile";
include('layout_header.php');
?>

<div class="title">New Post</div>
<div>
    <form action="new.php" method="post">
        <textarea name="post" id="post" cols="30" rows="10" placeholder="What's on your mind? (140 characters max)" maxlength="140"></textarea>
        <div class="r">
            <button type="submit">Post</button>
        </div>
    </form>
</div>

<?php include('layout_footer.php'); ?> 