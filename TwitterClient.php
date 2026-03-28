<?php
class TwitterClient {
    private $apiUrl = 'http://t.blueblips.com'; // changed to blueblips api
    private $username;
    private $password;
    private $accessToken;
    private $accessTokenSecret;
    private $userId;
    private $screenName;
    private $cookieExpiry = 2592000; // 30 days in seconds

    public function __construct() {
        session_start();
        $this->loadSession();
        $this->checkRememberMeCookie();
    }

    // Check if user is logged in
    public function isLoggedIn() {
        return isset($this->accessToken) && isset($this->accessTokenSecret);
    }

    // Load credentials from session
    private function loadSession() {
        if (isset($_SESSION['twitter_access_token'])) {
            $this->accessToken = $_SESSION['twitter_access_token'];
            $this->accessTokenSecret = $_SESSION['twitter_access_token_secret'];
            $this->userId = $_SESSION['twitter_user_id'];
            $this->screenName = $_SESSION['twitter_screen_name'];
        }
    }

    // Save credentials to session
    private function saveSession() {
        $_SESSION['twitter_access_token'] = $this->accessToken;
        $_SESSION['twitter_access_token_secret'] = $this->accessTokenSecret;
        $_SESSION['twitter_user_id'] = $this->userId;
        $_SESSION['twitter_screen_name'] = $this->screenName;
    }

    // Check for remember me cookie and auto-login if present
    private function checkRememberMeCookie() {
        if (!$this->isLoggedIn() && isset($_COOKIE['flirb_remember'])) {
            $cookieData = json_decode($_COOKIE['flirb_remember'], true);
            
            if (isset($cookieData['token']) && isset($cookieData['secret']) && 
                isset($cookieData['user_id']) && isset($cookieData['screen_name'])) {
                
                $this->accessToken = $cookieData['token'];
                $this->accessTokenSecret = $cookieData['secret'];
                $this->userId = $cookieData['user_id'];
                $this->screenName = $cookieData['screen_name'];
                
                $this->saveSession();
            }
        }
    }

    // Set remember me cookie
    private function setRememberMeCookie() {
        $cookieData = [
            'token' => $this->accessToken,
            'secret' => $this->accessTokenSecret,
            'user_id' => $this->userId,
            'screen_name' => $this->screenName
        ];
        
        setcookie(
            'flirb_remember',
            json_encode($cookieData),
            time() + $this->cookieExpiry,
            '/',
            '',
            isset($_SERVER['HTTPS']),
            true
        );
    }

    // Authenticate with xAuth
    public function authenticate($username, $password, $remember = false) {
        $this->username = $username;
        $this->password = $password;

        $url = $this->apiUrl . '/oauth/access_token';
        $params = array(
            'x_auth_username' => $username,
            'x_auth_password' => $password,
            'x_auth_mode' => 'client_auth'
        );

        $response = $this->makeRequest('POST', $url, $params, false);
        
        if (empty($response)) {
            return false;
        }
        
        // Check if response is an error array (including rate limiting)
        if (is_array($response) && isset($response['errors'])) {
            return $response; // Return the error response
        }

        // Process successful string response
        if (is_string($response)) {
            parse_str($response, $token);
            
            if (isset($token['oauth_token']) && isset($token['oauth_token_secret'])) {
                $this->accessToken = $token['oauth_token'];
                $this->accessTokenSecret = $token['oauth_token_secret'];
                $this->userId = $token['user_id'];
                $this->screenName = $token['screen_name'];
                
                $this->saveSession();
                
                // Set the cookie if remember me is checked
                if ($remember) {
                    $this->setRememberMeCookie();
                }
                
                return true;
            }
        }
        
        return false;
    }

    // Log out user
    public function logout() {
        $this->accessToken = null;
        $this->accessTokenSecret = null;
        $this->userId = null;
        $this->screenName = null;
        
        unset($_SESSION['twitter_access_token']);
        unset($_SESSION['twitter_access_token_secret']);
        unset($_SESSION['twitter_user_id']);
        unset($_SESSION['twitter_screen_name']);
        
        // Remove the remember me cookie
        if (isset($_COOKIE['flirb_remember'])) {
            setcookie('flirb_remember', '', time() - 3600, '/');
        }
    }

    // Get user's home timeline
    public function getHomeTimeline($count = 20, $max_id = null) {
        $url = $this->apiUrl . '/1.1/statuses/home_timeline.json';
        $params = array('count' => $count);
        
        if ($max_id) {
            $params['max_id'] = $max_id;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Get user's mentions timeline
    public function getMentionsTimeline($count = 20, $max_id = null) {
        $url = $this->apiUrl . '/1.1/statuses/mentions.json';
        $params = array('count' => $count);
        
        if ($max_id) {
            $params['max_id'] = $max_id;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Get user's public timeline
    public function getPublicTimeline($count = 20, $max_id = null) {
        $url = $this->apiUrl . '/1/statuses/public_timeline.json';
        $params = array('count' => $count);
        
        if ($max_id) {
            $params['max_id'] = $max_id;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }
    
    // Get user's profile
    public function getUserProfile($screenName = null) {
        $url = $this->apiUrl . '/1.1/users/show.json';
        $params = array();
        
        if ($screenName) {
            $params['screen_name'] = $screenName;
            
            // For unauthenticated requests, we need to directly handle the API call and parsing
            if (!$this->isLoggedIn()) {
                // Make direct unauthenticated request
                $curl = curl_init();
                
                $fullUrl = $url . '?' . http_build_query($params);
                curl_setopt($curl, CURLOPT_URL, $fullUrl);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_USERAGENT, 'Flirb-Web/1.0');
                curl_setopt($curl, CURLOPT_HEADER, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($curl, CURLOPT_TIMEOUT, 30);

                $realIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
                $headers = array(
                    'X-Forwarded-For: ' . $realIp,
                    'X-Real-IP: ' . $realIp,
                );
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                
                $response = curl_exec($curl);
                $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                
                // Decode the JSON response
                if ($httpCode >= 200 && $httpCode < 300) {
                    return json_decode($response, true);
                }
                
                return false;
            }
        } else {
            // For viewing own profile, must be logged in
            if (!$this->isLoggedIn()) {
                return false;
            }
            $params['user_id'] = $this->userId;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Post a new tweet
    public function postTweet($status, $mediaPath = null) {
        if ($mediaPath) {
            return $this->postTweetWithMedia($status, $mediaPath);
        }
        
        $url = $this->apiUrl . '/1.1/statuses/update.json';
        $params = array('status' => $status);
        
        return $this->makeRequest('POST', $url, $params);
    }
    
    // Post a tweet with media
    private function postTweetWithMedia($status, $mediaPath) {
        $url = $this->apiUrl . '/1.1/statuses/update_with_media.json';
        
        if (!file_exists($mediaPath)) {
            return false;
        }
        
        // Check if file is an image
        $fileInfo = getimagesize($mediaPath);
        if ($fileInfo === false) {
            return false; // Not an image
        }
        
        $params = array(
            'status' => $status,
            'media' => new CURLFile($mediaPath)
        );
        
        return $this->makeRequest('POST', $url, $params, true, true);
    }

    // Search for tweets
    public function search($query, $count = 20) {
        $url = $this->apiUrl . '/1.1/search/tweets.json';
        $params = array(
            'q' => $query,
            'count' => $count
        );
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Search for users
    public function searchUsers($query, $count = 20) {
        $url = $this->apiUrl . '/1.1/users/search.json';
        $params = array(
            'q' => $query,
            'count' => $count
        );
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Follow a user
    public function followUser($screenName) {
        $url = $this->apiUrl . '/1.1/friendships/create.json';
        $params = array('screen_name' => $screenName);
        
        return $this->makeRequest('POST', $url, $params);
    }

    // Unfollow a user
    public function unfollowUser($screenName) {
        $url = $this->apiUrl . '/1.1/friendships/destroy.json';
        $params = array('screen_name' => $screenName);
        
        return $this->makeRequest('POST', $url, $params);
    }

    // Get user's tweets
    public function getUserTweets($screenName = null, $count = 20, $max_id = null) {
        $url = $this->apiUrl . '/1.1/statuses/user_timeline.json';
        $params = array('count' => $count);
        
        if ($screenName) {
            $params['screen_name'] = $screenName;
        } else {
            // For viewing own tweets, must be logged in
            if (!$this->isLoggedIn()) {
                return array();
            }
            $params['user_id'] = $this->userId;
        }
        
        // Add max_id parameter for pagination if provided
        if ($max_id) {
            $params['max_id'] = $max_id;
        }
        
        // Handle unauthenticated requests for viewing others' tweets
        if ($screenName && !$this->isLoggedIn()) {
            // Make direct unauthenticated request
            $curl = curl_init();
            
            $fullUrl = $url . '?' . http_build_query($params);
            curl_setopt($curl, CURLOPT_URL, $fullUrl);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_USERAGENT, 'Flirb-Web/1.0');
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 30);

            $realIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
            $headers = array(
                'X-Forwarded-For: ' . $realIp,
                'X-Real-IP: ' . $realIp,
            );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            // Decode the JSON response
            if ($httpCode >= 200 && $httpCode < 300) {
                return json_decode($response, true);
            }
            
            return array();
        }
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Update profile
    public function updateProfile($name, $description, $location = null, $website = null) {
        $url = $this->apiUrl . '/1.1/account/update_profile.json';
        $params = array(
            'name' => $name,
            'description' => $description
        );
        
        if ($location !== null) {
            $params['location'] = $location;
        }
        
        if ($website !== null) {
            $params['url'] = $website;
        }
        
        return $this->makeRequest('POST', $url, $params);
    }

    // Update profile image
    public function updateProfileImage($imagePath) {
        $url = $this->apiUrl . '/1.1/account/update_profile_image.json';
        
        if (!file_exists($imagePath)) {
            return false;
        }
        
        $params = array('image' => new CURLFile($imagePath));
        
        return $this->makeRequest('POST', $url, $params, true, true);
    }

    // Update profile banner
    public function updateProfileBanner($imagePath) {
        $url = $this->apiUrl . '/1.1/account/update_profile_banner.json';
        
        if (!file_exists($imagePath)) {
            return false;
        }
        
        $params = array('banner' => new CURLFile($imagePath));
        
        return $this->makeRequest('POST', $url, $params, true, true);
    }

    // Favorite a tweet
    public function favoriteTweet($tweetId) {
        $url = $this->apiUrl . '/1.1/favorites/create.json';
        $params = array('id' => $tweetId);
        
        return $this->makeRequest('POST', $url, $params);
    }

    // Unfavorite a tweet
    public function unfavoriteTweet($tweetId) {
        $url = $this->apiUrl . '/1.1/favorites/destroy.json';
        $params = array('id' => $tweetId);
        
        return $this->makeRequest('POST', $url, $params);
    }

    // Repost a tweet
    public function repostTweet($tweetId) {
        $url = $this->apiUrl . '/1.1/statuses/retweet/' . $tweetId . '.json';
        
        return $this->makeRequest('POST', $url);
    }

    // Post a reply to a tweet
    public function replyToTweet($status, $inReplyToStatusId) {
        $url = $this->apiUrl . '/1.1/statuses/update.json';
        $params = array(
            'status' => $status,
            'in_reply_to_status_id' => $inReplyToStatusId
        );
        
        return $this->makeRequest('POST', $url, $params);
    }

    // Unrepost a tweet
    public function unrepostTweet($tweetId) {
        $url = $this->apiUrl . '/1.1/statuses/destroy/' . $tweetId . '.json';
        
        return $this->makeRequest('POST', $url);
    }

    // Delete a tweet
    public function deleteTweet($tweetId) {
        $url = $this->apiUrl . '/1.1/statuses/destroy/' . $tweetId . '.json';
        
        return $this->makeRequest('POST', $url);
    }

    // Check friendship status
    public function getFriendship($targetScreenName) {
        $url = $this->apiUrl . '/1.1/friendships/show.json';
        $params = array(
            'source_screen_name' => $this->screenName,
            'target_screen_name' => $targetScreenName
        );
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Get trending topics
    public function getTrendingTopics() {
        $url = $this->apiUrl . '/1.1/trends/place.json';
        $params = array('id' => 1);
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Get similar (suggested) users
    public function getSimilarUsers($screenName, $count = 3) {
        $url = $this->apiUrl . '/1.1/users/recommendations.json';
        $params = array('screen_name' => $screenName, 'limit' => $count);

        return $this->makeRequest('GET', $url, $params);
    }

    // Lookup friendship status for multiple users at once
    public function getFriendshipsLookup($screenNames) {
        $url = $this->apiUrl . '/1.1/friendships/lookup.json';
        
        // Convert array of screen names to comma-separated string
        if (is_array($screenNames)) {
            $screenNames = implode(',', $screenNames);
        }
        
        $params = array('screen_name' => $screenNames);
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Get followers list
    public function getFollowers($screenName = null, $count = 20, $cursor = -1) {
        $url = $this->apiUrl . '/1.1/followers/list.json';
        $params = array(
            'count' => $count,
            'cursor' => $cursor,
            'skip_status' => true,
            'include_user_entities' => false
        );
        
        if ($screenName) {
            $params['screen_name'] = $screenName;
        } else {
            $params['user_id'] = $this->userId;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }
    
    // Get user's favorites
    public function getFavorites($screenName = null, $count = 20, $max_id = null) {
        $url = $this->apiUrl . '/1.1/favorites/list.json';
        $params = array('count' => $count);
        
        if ($screenName) {
            $params['screen_name'] = $screenName;
        } else {
            $params['user_id'] = $this->userId;
        }
        
        if ($max_id) {
            $params['max_id'] = $max_id;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }
    
    // Get activity summary of a post (favorites, reposts, replies)
    public function getPostActivitySummary($postId) {
        $url = $this->apiUrl . "/1.1/statuses/{$postId}/activity/summary.json";
        return $this->makeRequest('GET', $url);
    }
    
    // Look up users by user IDs
    public function getUsersLookup($userIds) {
        $url = $this->apiUrl . '/1.1/users/lookup.json';
        
        // Convert array of user IDs to comma-separated string
        if (is_array($userIds)) {
            $userIds = implode(',', $userIds);
        }
        
        $params = array('user_id' => $userIds);
        
        return $this->makeRequest('GET', $url, $params);
    }
    
    // Get following list (friends in Twitter API terminology)
    public function getFollowing($screenName = null, $count = 20, $cursor = -1) {
        $url = $this->apiUrl . '/1.1/friends/list.json';
        $params = array(
            'count' => $count,
            'cursor' => $cursor,
            'skip_status' => true,
            'include_user_entities' => false
        );
        
        if ($screenName) {
            $params['screen_name'] = $screenName;
        } else {
            $params['user_id'] = $this->userId;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }

    // Get current user's screen name
    public function getScreenName() {
        return $this->screenName;
    }
    
    // Get the API URL
    public function getApiUrl() {
        return $this->apiUrl;
    }
    
    // Register a new user
    public function registerUser($name, $screenName, $email, $password, $inviteCode, $hcaptchaToken) {
        $url = $this->apiUrl . '/oauth/register';
        
        // Prepare the data
        $data = [
            'name' => $name,
            'screenName' => $screenName,
            'email' => $email,
            'password' => $password,
            'inviteCode' => $inviteCode,
            'hcaptchaToken' => $hcaptchaToken
        ];
        
        // Encode data as form-urlencoded
        $postData = http_build_query($data);
        
        // Initialize cURL session
        $ch = curl_init();
        
        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        
        // Set request headers
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postData),
            'User-Agent: Flirb-Web/1.0',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Additional settings
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Always try to decode the response
        $decoded = json_decode($response, true);
        
        // If it's a valid JSON response, return it as is - this preserves server error messages
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        // If not JSON or other error, handle accordingly
        if (!empty($response)) {
            return $response; // Return raw response if not JSON
        }
        
        // Only create a generic error if we have nothing else
        return [
            'errors' => [
                [
                    'message' => "Registration failed. Please try again later.",
                    'code' => $httpCode
                ]
            ]
        ];
    }
    
    // Try registration with different formats
    private function tryRegistration($url, $params, $format = 'form') {
        // For debugging
        error_log("Sending registration request to: " . $url . " with format: " . $format);
        error_log("Registration params: " . json_encode($params));
        
        // Custom request for registration
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Flirb-Web/1.0');
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        
        $realIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
        $headers = array(
            'X-Forwarded-For: ' . $realIp,
            'X-Real-IP: ' . $realIp,
        );
        
        // Set content type and prepare data based on format
        if ($format === 'json') {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
        }
        
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);
        
        error_log("Registration response HTTP code: " . $httpCode);
        error_log("Registration response: " . $response);
        if ($curlError) {
            error_log("cURL error: " . $curlError . " (" . $curlErrno . ")");
        }
        
        curl_close($curl);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            return $response;
        }
        
        return [
            'errors' => [
                [
                    'message' => "Registration failed with format $format: HTTP Code $httpCode, cURL Error: $curlError ($curlErrno)",
                    'raw_response' => $response
                ]
            ]
        ];
    }
    
    // Make a custom request to any endpoint
    public function makeCustomRequest($method, $url, $params = array(), $multipart = false) {
        return $this->makeRequest($method, $url, $params, true, $multipart);
    }

    // Make HTTP request to Twitter API
    private function makeRequest($method, $url, $params = array(), $auth = true, $multipart = false) {
        $curl = curl_init();
        
        // Build query for GET requests
        if ($method == 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Flirb-Web-Mobile/1.0');
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);

        $realIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];

        $headers = array(
            'X-Forwarded-For: ' . $realIp,
            'X-Real-IP: ' . $realIp,
        );
        
        if ($auth) {
            if (!$this->isLoggedIn()) {
                return false;
            }
        
            $headers[] = 'Authorization: OAuth oauth_token="' . $this->accessToken . '"';
        }
        
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        
        // Set POST data
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            if ($multipart) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
            } else {
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
            }
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);
        curl_close($curl);
        
        // Check for successful response
        if ($httpCode >= 200 && $httpCode < 300) {
            if ($auth || $url == $this->apiUrl . '/oauth/register') {
                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    // JSON decode failed, return raw response
                    return $response;
                }
                
                // Check for rate limit error in successful responses that might still contain error info
                if (isset($decoded['errors']) && is_array($decoded['errors'])) {
                    foreach ($decoded['errors'] as $error) {
                        if (isset($error['code']) && $error['code'] == 88) {
                            // This is a rate limit error
                            return [
                                'errors' => [
                                    [
                                        'message' => 'Rate limit exceeded. Please try again later.',
                                        'code' => 88,
                                        'rate_limited' => true
                                    ]
                                ]
                            ];
                        }
                    }
                }
                
                return $decoded;
            }
            return $response;
        }
        
        // Parse response for error details
        $errorInfo = [
            'message' => "API Error: HTTP Code $httpCode, cURL Error: $curlError ($curlErrno)",
            'raw_response' => $response
        ];
        
        // Try to decode JSON error response
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['errors']) && is_array($decoded['errors'])) {
            // Check specifically for rate limit errors
            foreach ($decoded['errors'] as $error) {
                if (isset($error['code']) && $error['code'] == 88) {
                    return [
                        'errors' => [
                            [
                                'message' => 'Rate limit exceeded. Please try again later.',
                                'code' => 88,
                                'rate_limited' => true
                            ]
                        ]
                    ];
                }
            }
            
            // Return the original error response if it's not a rate limit error
            return $decoded;
        }
        
        // Default error response
        return [
            'errors' => [
                $errorInfo
            ]
        ];
    }

    // Get direct messages sent to the user
    public function getDirectMessages($count = 20, $max_id = null) {
        $url = $this->apiUrl . '/1.1/direct_messages.json';
        $params = array('count' => $count);
        
        if ($max_id) {
            $params['max_id'] = $max_id;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }
    
    // Get direct messages sent by the user
    public function getSentDirectMessages($count = 20, $max_id = null) {
        $url = $this->apiUrl . '/1.1/direct_messages/sent.json';
        $params = array('count' => $count);
        
        if ($max_id) {
            $params['max_id'] = $max_id;
        }
        
        return $this->makeRequest('GET', $url, $params);
    }
    
    // Get a list of all conversations with latest messages
    public function getDirectMessagesConversations() {
        // First get received messages
        $received = $this->getDirectMessages(50);
        // Then get sent messages
        $sent = $this->getSentDirectMessages(50);
        
        // Combine and organize messages by user
        $conversations = array();
        
        // Process received messages
        if (is_array($received)) {
            foreach ($received as $message) {
                $sender = $message['sender_screen_name'];
                if (!isset($conversations[$sender]) || strtotime($message['created_at']) > strtotime($conversations[$sender]['created_at'])) {
                    $conversations[$sender] = array(
                        'message' => $message,
                        'created_at' => $message['created_at'],
                        'user' => $message['sender'],
                        'is_received' => true
                    );
                }
            }
        }
        
        // Process sent messages
        if (is_array($sent)) {
            foreach ($sent as $message) {
                $recipient = $message['recipient_screen_name'];
                if (!isset($conversations[$recipient]) || strtotime($message['created_at']) > strtotime($conversations[$recipient]['created_at'])) {
                    $conversations[$recipient] = array(
                        'message' => $message,
                        'created_at' => $message['created_at'],
                        'user' => $message['recipient'],
                        'is_received' => false
                    );
                }
            }
        }
        
        // Sort conversations by most recent message
        uasort($conversations, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $conversations;
    }
    
    // Get direct messages with a specific user
    public function getDirectMessagesWithUser($screenName, $since_id = null, $count = 50, $max_id = null) {
        // Get both received and sent messages
        $receivedParams = ['count' => 200];
        $sentParams = ['count' => 200];
        
        if ($max_id) {
            $receivedParams['max_id'] = $max_id;
            $sentParams['max_id'] = $max_id;
        }
        
        if ($since_id) {
            $receivedParams['since_id'] = $since_id;
            $sentParams['since_id'] = $since_id;
            error_log("Fetching messages since ID: " . $since_id);
        }
        
        // Call API with appropriate parameters
        $received = $this->makeRequest('GET', $this->apiUrl . '/1.1/direct_messages.json', $receivedParams);
        $sent = $this->makeRequest('GET', $this->apiUrl . '/1.1/direct_messages/sent.json', $sentParams);
        
        $conversation = array();
        
        // Filter received messages from this user
        if (is_array($received)) {
            foreach ($received as $message) {
                if ($message['sender_screen_name'] === $screenName) {
                    // Make sure direction is explicitly set
                    $message['direction'] = 'received';
                    $conversation[] = $message;
                }
            }
        }
        
        // Filter sent messages to this user
        if (is_array($sent)) {
            foreach ($sent as $message) {
                if ($message['recipient_screen_name'] === $screenName) {
                    // Make sure direction is explicitly set
                    $message['direction'] = 'sent';
                    $conversation[] = $message;
                }
            }
        }
        
        // Sort messages by date (oldest first)
        usort($conversation, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
        // Debug the resulting conversation array
        error_log("Found " . count($conversation) . " messages with user " . $screenName);
        
        // Return the most recent messages (limited by count)
        if (count($conversation) > $count) {
            $conversation = array_slice($conversation, -$count);
        }
        
        return $conversation;
    }
    
    // Send a direct message
    public function sendDirectMessage($screenName, $text) {
        $url = $this->apiUrl . '/1.1/direct_messages/new.json';
        $params = array(
            'screen_name' => $screenName,
            'text' => $text
        );
        
        return $this->makeRequest('POST', $url, $params);
    }
} 
