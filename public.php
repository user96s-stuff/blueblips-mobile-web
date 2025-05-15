<?php
require_once('utils.php');

$client = getTwitterClient();
$error = getError();

// Get posts with optional pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$maxId = null;

// Handle pagination using max_id
if ($page > 1 && isset($_GET['max_id'])) {
    $maxId = $_GET['max_id'];
}

// Get public timeline posts
$posts = $client->getPublicTimeline($perPage, $maxId);

// Check for rate limiting
if (is_array($posts) && isset($posts['errors'])) {
    foreach ($posts['errors'] as $errorItem) {
        if (isset($errorItem['code']) && $errorItem['code'] == 88) {
            setError("You've reached the Flirb rate limit. Please wait a moment and try again later.");
            $posts = array(); // Clear posts so we don't try to display them
            break;
        }
    }
}

// Get the lowest ID for pagination
$oldestId = null;
if (!empty($posts) && is_array($posts)) {
    $oldestId = end($posts)['id_str'];
    reset($posts); // Reset array pointer after end()
}

$pageTitle = "Public Timeline - Flirb Mobile";
include('layout_header.php');
?>

<div class="title">Public Timeline</div>

<?php if (empty($posts)): ?>
    <div style="padding: 5px;">No posts to display.</div>
<?php else: ?>
    <ul>
        <?php foreach ($posts as $post): ?>
            <li>
                <a href="profile.php?username=<?php echo h($post['user']['screen_name']); ?>">
                    <?php echo h($post['user']['screen_name']); ?>
                    <?php if ($post['user']['verified']): ?>
                        <img src="/img/verified.gif" alt="Verified">
                    <?php endif; ?>
                </a> 
                <?php echo formatTweet($post['text']); ?>
                <small><?php echo formatDate($post['created_at']); ?></small>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <div class="r">
        <?php if ($page > 1): ?>
            <a href="public.php?page=<?php echo $page - 1; ?>">Previous</a>
        <?php endif; ?>
        
        <?php if (!empty($oldestId)): ?>
            <a href="public.php?page=<?php echo $page + 1; ?>&max_id=<?php echo $oldestId; ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include('layout_footer.php'); ?> 