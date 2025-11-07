<?php
require_once('utils.php');

$client = getTwitterClient();
$error = getError();
$success_message = '';

// Check if already logged in
if ($client->isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $remember = isset($_POST['remember']) ? true : false;

    if (empty($username) || empty($password)) {
        setError("Username and password are required");
        header("Location: login.php");
        exit;
    } else {
        $auth_result = $client->authenticate($username, $password, $remember);
        
        if ($auth_result === true) {
            header("Location: index.php");
            exit;
        } else {
            // Check if this is a rate limit error
            if (is_array($auth_result) && isset($auth_result['errors']) && is_array($auth_result['errors'])) {
                foreach ($auth_result['errors'] as $errorItem) {
                    if (isset($errorItem['code']) && $errorItem['code'] == 88) {
                        setError("You've reached the request limit. Please wait a moment and try again later.");
                        break;
                    }
                }
            }
            
            // Default error message if not a rate limit issue
            if (!$error) {
                setError("Invalid username or password");
            }
            
            header("Location: login.php");
            exit;
        }
    }
}

$pageTitle = "Login - Flirb Mobile";
include('layout_header.php');
?>

<div>
    <form action="login.php" method="post">
        <b>Username:</b>
        <input type="text" name="username" id="username">
        <br>
        <b>Password:</b>
        <input type="password" name="password" id="password">
        <br>
        <input type="checkbox" name="remember">
        <label>Remember me</label>
        <br>
        <br>
        <button type="submit">Login</button>
    </form>
</div>
<br>
<div class="title">Forgot password?</div>
<div><a href="http://flirb.net/forgot_password.php">Reset on the regular web client, flirb.net</a></div>
<br>
<div class="title">Don't have an account?</div>
<div><a href="http://flirb.net/register.php">Register on the regular web client, flirb.net</a></div>

<?php include('layout_footer.php'); ?> 