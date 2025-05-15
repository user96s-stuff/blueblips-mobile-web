<?php
require_once('utils.php');

$client = getTwitterClient();
$error = getError();

// Get username from query string or use current user's screen name
$username = isset($_GET['username']) ? $_GET['username'] : $client->getScreenName();

// Get pagination parameters
$cursor = isset($_GET['cursor']) ? $_GET['cursor'] : -1;
$perPage = 10;

// Get user profile
$user = $client->getUserProfile($username);
if (!$user) {
    setError("User not found");
}

// Get following (friends in Twitter API terminology)
$following = $client->getFollowing($username, $perPage, $cursor);

// Check for rate limiting
if (is_array($following) && isset($following['errors'])) {
    foreach ($following['errors'] as $errorItem) {
        if (isset($errorItem['code']) && $errorItem['code'] == 88) {
            setError("You've reached the Flirb rate limit. Please wait a moment and try again later.");
            $following = array(); // Clear following list so we don't try to display it
            break;
        }
    }
}

// Get pagination cursors
$nextCursor = isset($following['next_cursor_str']) ? $following['next_cursor_str'] : '';
$prevCursor = isset($following['previous_cursor_str']) ? $following['previous_cursor_str'] : '';

$pageTitle = "Following - " . (isset($user['name']) ? $user['name'] : $username);
include('layout_header.php');
?>

<div class="title"><?php echo isset($user['name']) ? h($user['name']) : h($username); ?> is following</div>

<?php if (!$user): ?>
    <div style="padding: 5px;">User not found.</div>
<?php elseif (empty($following) || empty($following['users'])): ?>
    <div style="padding: 5px;">No following users to display.</div>
<?php else: ?>
    <ul>
        <?php foreach ($following['users'] as $followedUser): ?>
            <li>
                <a href="profile.php?username=<?php echo h($followedUser['screen_name']); ?>">
                    <?php echo h($followedUser['name']); ?> (@<?php echo h($followedUser['screen_name']); ?>)
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <div class="r">
        <?php if ($prevCursor != 0 && $prevCursor != ''): ?>
            <a href="following.php?username=<?php echo h($username); ?>&cursor=<?php echo $prevCursor; ?>">Previous</a>
        <?php endif; ?>
        
        <?php if ($nextCursor != 0 && $nextCursor != ''): ?>
            <a href="following.php?username=<?php echo h($username); ?>&cursor=<?php echo $nextCursor; ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<br>
<div><a href="profile.php?username=<?php echo h($username); ?>">Back to Profile</a></div>

<?php include('layout_footer.php'); ?> 