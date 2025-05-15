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

// Get followers
$followers = $client->getFollowers($username, $perPage, $cursor);

// Check for rate limiting
if (is_array($followers) && isset($followers['errors'])) {
    foreach ($followers['errors'] as $errorItem) {
        if (isset($errorItem['code']) && $errorItem['code'] == 88) {
            setError("You've reached the Flirb rate limit. Please wait a moment and try again later.");
            $followers = array(); // Clear followers so we don't try to display them
            break;
        }
    }
}

// Get pagination cursors
$nextCursor = isset($followers['next_cursor_str']) ? $followers['next_cursor_str'] : '';
$prevCursor = isset($followers['previous_cursor_str']) ? $followers['previous_cursor_str'] : '';

$pageTitle = "Followers of " . (isset($user['name']) ? $user['name'] : $username);
include('layout_header.php');
?>

<div class="title">Followers of <?php echo isset($user['name']) ? h($user['name']) : h($username); ?></div>

<?php if (!$user): ?>
    <div style="padding: 5px;">User not found.</div>
<?php elseif (empty($followers) || empty($followers['users'])): ?>
    <div style="padding: 5px;">No followers to display.</div>
<?php else: ?>
    <ul>
        <?php foreach ($followers['users'] as $follower): ?>
            <li>
                <a href="profile.php?username=<?php echo h($follower['screen_name']); ?>">
                    <?php echo h($follower['name']); ?> (@<?php echo h($follower['screen_name']); ?>)
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    
    <div class="r">
        <?php if ($prevCursor != 0 && $prevCursor != ''): ?>
            <a href="followers.php?username=<?php echo h($username); ?>&cursor=<?php echo $prevCursor; ?>">Previous</a>
        <?php endif; ?>
        
        <?php if ($nextCursor != 0 && $nextCursor != ''): ?>
            <a href="followers.php?username=<?php echo h($username); ?>&cursor=<?php echo $nextCursor; ?>">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<br>
<div><a href="profile.php?username=<?php echo h($username); ?>">Back to Profile</a></div>

<?php include('layout_footer.php'); ?> 