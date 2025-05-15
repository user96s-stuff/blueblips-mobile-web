<?php
// Include the TwitterClient class
require_once('TwitterClient.php');

/**
 * Get the Twitter client instance
 *
 * @return TwitterClient The Twitter client
 */
function getTwitterClient() {
    static $client = null;
    if ($client === null) {
        $client = new TwitterClient();
    }
    return $client;
}

/**
 * Get any error message from the session
 *
 * @return string The error message
 */
function getError() {
    $error = '';
    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        unset($_SESSION['error']);
    }
    return $error;
}

/**
 * Set an error message in the session
 *
 * @param string $message The error message
 */
function setError($message) {
    $_SESSION['error'] = $message;
}

/**
 * Ensure user is logged in, redirect to login if not
 */
function requireLogin() {
    $client = getTwitterClient();
    if (!$client->isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * HTML escape function
 *
 * @param string $string The string to escape
 * @return string The escaped string
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format tweet text by converting @mentions, #hashtags, and URLs to links
 * 
 * @param string $text The tweet text to format
 * @return string Formatted text with HTML links
 */
function formatTweet($text) {
    // Use a placeholder approach that safely preserves special characters
    $placeholders = array();
    $placeholder_i = 0;
    
    // Function to create a placeholder
    $createPlaceholder = function() use (&$placeholder_i) {
        return "PLACEHOLDER_" . ($placeholder_i++) . "_PLACEHOLDER";
    };
    
    // Safely escape text using a custom function that preserves apostrophes
    $safeEscape = function($str) {
        return htmlspecialchars($str, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    };
    
    // Extract URLs and replace with placeholders
    $urlPattern = '/(https?:\/\/\S+)/i';
    $text = preg_replace_callback($urlPattern, function($matches) use (&$placeholders, $createPlaceholder, $safeEscape) {
        $url = $matches[1];
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $placeholder = $createPlaceholder();
        $placeholders[$placeholder] = '<a href="' . $safeUrl . '">' . $safeUrl . '</a>';
        return $placeholder;
    }, $text);
    
    // Extract @mentions and replace with placeholders
    $mentionPattern = '/\B@([a-zA-Z0-9_]{1,15})\b/';
    $text = preg_replace_callback($mentionPattern, function($matches) use (&$placeholders, $createPlaceholder, $safeEscape) {
        $username = $matches[1];
        $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $placeholder = $createPlaceholder();
        $placeholders[$placeholder] = '<a href="profile.php?username=' . $safeUsername . '">@' . $safeUsername . '</a>';
        return $placeholder;
    }, $text);
    
    // Extract #hashtags and replace with placeholders
    $hashtagPattern = '/\B#([a-zA-Z0-9_]+)\b/';
    $text = preg_replace_callback($hashtagPattern, function($matches) use (&$placeholders, $createPlaceholder, $safeEscape) {
        $hashtag = $matches[1];
        $safeHashtag = htmlspecialchars($hashtag, ENT_QUOTES, 'UTF-8');
        $placeholder = $createPlaceholder();
        $placeholders[$placeholder] = '<span style="color: #FF3284;">#' . $safeHashtag . '</span>';
        return $placeholder;
    }, $text);
    
    // Escape the remaining text (preserving apostrophes)
    $text = $safeEscape($text);
    
    // Replace placeholders with their HTML content
    foreach ($placeholders as $placeholder => $html) {
        $text = str_replace($placeholder, $html, $text);
    }
    
    return $text;
}

/**
 * Format a date for display
 *
 * @param string $date The date string
 * @return string The formatted date
 */
function formatDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " min" . ($mins != 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours != 1 ? "s" : "") . " ago";
    } else {
        return date("j M Y", $timestamp);
    }
}
?> 