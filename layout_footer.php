<?php
// Only show navigation menu if user is logged in
$client = getTwitterClient();
if ($client->isLoggedIn()): 
?>
<br>
<div><a href="index.php">Home</a></div>
<div><a href="new.php">New Post</a></div>
<div><a href="profile.php">Your Profile</a></div>
<div><a href="public.php">Public Timeline</a></div>
<div><a href="logout.php">Sign Out</a></div>
<?php endif; ?>

<div class="footer">
    <small>© 2025 Flirb</small>
</div>
</body>
</html> 