<?php
require_once('utils.php');

$client = getTwitterClient();
$error = getError();

// Get username from query string or use current user's screen name
$username = isset($_GET['username']) ? $_GET['username'] : $client->getScreenName();
$isLoggedIn = $client->isLoggedIn();

// Handle follow/unfollow actions
if ($isLoggedIn && isset($_POST['action']) && isset($_POST['username'])) {
    if ($_POST['action'] === 'follow') {
        $result = $client->followUser($_POST['username']);
        if (!$result) {
            setError("Failed to follow user. Please try again.");
        }
    } elseif ($_POST['action'] === 'unfollow') {
        $result = $client->unfollowUser($_POST['username']);
        if (!$result) {
            setError("Failed to unfollow user. Please try again.");
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: profile.php?username=" . urlencode($username));
    exit;
}

// Get user profile
$user = $client->getUserProfile($username);
if (!$user) {
    setError("User not found");
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$maxId = null;

// Handle pagination using max_id
if ($page > 1 && isset($_GET['max_id'])) {
    $maxId = $_GET['max_id'];
}

// Get user's tweets
$posts = $client->getUserTweets($username, $perPage, $maxId);

// Get the lowest ID for pagination
$oldestId = null;
if (!empty($posts) && is_array($posts)) {
    $oldestId = end($posts)['id_str'];
    reset($posts); // Reset array pointer after end()
}

// Determine if current user is following the profile user
$isFollowing = false;
$followsYou = false;
$isOwnProfile = false;
if ($isLoggedIn && $user) {
    if ($client->getScreenName() === $user['screen_name']) {
        $isOwnProfile = true;
    } else {
        $friendship = $client->getFriendship($user['screen_name']);
        $isFollowing = isset($friendship['relationship']['source']['following']) ? 
                      $friendship['relationship']['source']['following'] : false;
        $followsYou = isset($friendship['relationship']['source']['followed_by']) ? 
                      $friendship['relationship']['source']['followed_by'] : false;
    }
}

$pageTitle = isset($user['name']) ? $user['name'] . " (@" . $user['screen_name'] . ")" : "User Not Found";
include('layout_header.php');
?>

<?php if ($user): ?>
    <div><img src="<?php echo h($user['profile_image_url_https']); ?>" alt="<?php echo h($user['name']); ?>"></div>
    <div class="title">About <?php echo h($user['name']); ?></div>

    <?php if ($user['verified']): ?>
        <div><img src="/img/verified.png" alt="Verified"> Verified</div>
    <?php endif; ?>
    
    <?php if (!empty($user['description'])): ?>
        <div><?php echo h($user['description']); ?></div>
    <?php endif; ?>
    
    <div>
        <a href="profile.php?username=<?php echo h($user['screen_name']); ?>"><?php echo h($user['statuses_count']); ?> post<?php echo $user['statuses_count'] != 1 ? 's' : ''; ?></a>
        <a href="followers.php?username=<?php echo h($user['screen_name']); ?>"><?php echo h($user['followers_count']); ?> follower<?php echo $user['followers_count'] != 1 ? 's' : ''; ?></a>
        <a href="following.php?username=<?php echo h($user['screen_name']); ?>"><?php echo h($user['friends_count']); ?> following</a>
    </div>
    
    <?php if ($isLoggedIn && !$isOwnProfile): ?>
        <div>
            <form method="post" action="profile.php?username=<?php echo h($user['screen_name']); ?>">
                <input type="hidden" name="username" value="<?php echo h($user['screen_name']); ?>">
                <input type="hidden" name="action" value="<?php echo $isFollowing ? 'unfollow' : 'follow'; ?>">
                <button type="submit"><?php echo $isFollowing ? 'Unfollow' : 'Follow'; ?></button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($followsYou): ?>
        <div>This user follows you.</div>
    <?php endif; ?>
    
    <br>
    <div class="title">Status Updates</div>
    
    <?php if (empty($posts)): ?>
        <div style="padding: 5px;">@<?php echo h($user['screen_name']); ?> hasn't posted anything yet.</div>
    <?php else: ?>
        <ul>
            <?php foreach ($posts as $post): ?>
                <li>
                    <a href="profile.php?username=<?php echo h($post['user']['screen_name']); ?>">
                        <?php echo h($post['user']['screen_name']); ?>
                        <?php if ($post['user']['verified']): ?>
                            <img src="/img/verified.png" alt="Verified">
                        <?php endif; ?>
                    </a> 
                    <?php echo formatTweet($post['text']); ?>
                    <small><?php echo formatDate($post['created_at']); ?></small>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <div class="r">
            <?php if ($page > 1): ?>
                <a href="profile.php?username=<?php echo h($username); ?>&page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>
            
            <?php if (!empty($oldestId)): ?>
                <a href="profile.php?username=<?php echo h($username); ?>&page=<?php echo $page + 1; ?>&max_id=<?php echo $oldestId; ?>">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div class="title">User Not Found</div>
    <div style="padding: 5px;">The user you're looking for doesn't exist or may have been deleted.</div>
<?php endif; ?>

<?php include('layout_footer.php'); ?> 