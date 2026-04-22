<?php
// ==============================
// MOVIE BOT - WITH AUTO-DELETE & REQUEST SYSTEM
// ==============================

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// ==============================
// CONFIGURATION
// ==============================
if (!getenv('BOT_TOKEN')) {
    die("❌ BOT_TOKEN missing!");
}

define('BOT_TOKEN', getenv('BOT_TOKEN'));
define('ADMIN_ID', (int)getenv('ADMIN_ID'));

// Channels
define('MAIN_CHANNEL_ID', getenv('MAIN_CHANNEL_ID'));
define('MAIN_CHANNEL_USERNAME', getenv('MAIN_CHANNEL_USERNAME', '@EntertainmentTadka786'));
define('CHANNEL2_ID', getenv('CHANNEL2_ID'));
define('CHANNEL2_USERNAME', getenv('CHANNEL2_USERNAME'));
define('CHANNEL3_ID', getenv('CHANNEL3_ID'));
define('CHANNEL3_USERNAME', getenv('CHANNEL3_USERNAME'));
define('BACKUP_CHANNEL_ID', getenv('BACKUP_CHANNEL_ID'));
define('BACKUP_CHANNEL_USERNAME', getenv('BACKUP_CHANNEL_USERNAME'));

// Request Group
define('REQUEST_GROUP_ID', getenv('REQUEST_GROUP_ID'));
define('REQUEST_GROUP_USERNAME', getenv('REQUEST_GROUP_USERNAME', '@EntertainmentTadka7860'));

// Files
define('CSV_FILE', 'movies.csv');
define('REQUESTS_FILE', 'requests.json');
define('LOG_FILE', 'bot.log');
define('MAX_SEARCH_RESULTS', 15);

// ==============================
// AUTO-DELETE CLASS
// ==============================
class AutoDeleteManager {
    
    private static $delete_queue = [];
    
    const TEXT_DELETE = 10;
    const SEARCH_DELETE = 60;
    const MOVIE_DELETE = 300;
    
    public static function addToQueue($chat_id, $message_id, $type, $duration) {
        $key = $chat_id . '_' . $message_id;
        self::$delete_queue[$key] = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'type' => $type,
            'duration' => $duration,
            'start_time' => time(),
            'deleted' => false
        ];
        
        self::scheduleDelete($chat_id, $message_id, $duration);
        return $key;
    }
    
    private static function scheduleDelete($chat_id, $message_id, $delay) {
        if (function_exists('pcntl_fork')) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                sleep($delay);
                self::deleteMessageNow($chat_id, $message_id);
            } elseif ($pid == 0) {
                sleep($delay);
                self::deleteMessageNow($chat_id, $message_id);
                exit;
            }
        } else {
            sleep($delay);
            self::deleteMessageNow($chat_id, $message_id);
        }
    }
    
    private static function deleteMessageNow($chat_id, $message_id) {
        apiRequest('deleteMessage', [
            'chat_id' => $chat_id,
            'message_id' => $message_id
        ]);
    }
    
    public static function updateProgress($chat_id, $message_id, $start_time, $duration) {
        $elapsed = time() - $start_time;
        $remaining = $duration - $elapsed;
        
        if ($remaining <= 0) {
            return false;
        }
        
        $percentage = round(($elapsed / $duration) * 100);
        $progress_bar = self::getProgressBar($percentage);
        
        return [
            'remaining' => $remaining,
            'percentage' => $percentage,
            'progress_bar' => $progress_bar
        ];
    }
    
    private static function getProgressBar($percentage) {
        $filled = round($percentage / 10);
        $empty = 10 - $filled;
        
        $bar = '[';
        $bar .= str_repeat('▓', $filled);
        $bar .= str_repeat('░', $empty);
        $bar .= ']';
        
        return $bar;
    }
    
    public static function formatTime($seconds) {
        if ($seconds >= 60) {
            $mins = floor($seconds / 60);
            $secs = $seconds % 60;
            return $mins . 'm ' . $secs . 's';
        }
        return $seconds . 's';
    }
    
    public static function getWarningMessage($type, $duration, $start_time = null) {
        if ($start_time) {
            $progress = self::updateProgress(0, 0, $start_time, $duration);
            if ($progress) {
                $time_left = self::formatTime($progress['remaining']);
                $bar = $progress['progress_bar'];
                $percentage = $progress['percentage'];
                
                if ($percentage >= 90) {
                    $warning = "⚠️ <b>DELETING SOON!</b> {$time_left} remaining\n";
                } elseif ($percentage >= 70) {
                    $warning = "⚠️ <b>Auto-delete in</b> {$time_left}\n";
                } else {
                    $warning = "⚠️ <b>Message will auto-delete in</b> {$time_left}\n";
                }
                
                $warning .= "{$bar} {$percentage}%\n";
                
                if ($percentage >= 90) {
                    $warning .= "🕐 <i>Hurry up!</i>";
                }
                
                return $warning;
            }
        }
        
        $time = self::formatTime($duration);
        $warning = "━━━━━━━━━━━━━━━━━━━━\n";
        $warning .= "⚠️ <b>This message will auto-delete in {$time}</b>\n";
        $warning .= self::getProgressBar(0) . " 0%\n";
        $warning .= "━━━━━━━━━━━━━━━━━━━━";
        
        return $warning;
    }
}

// ==============================
// INITIALIZE REQUESTS FILE
// ==============================
function initRequestsFile() {
    if (!file_exists(REQUESTS_FILE)) {
        $data = ['requests' => [], 'last_id' => 0];
        file_put_contents(REQUESTS_FILE, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// ==============================
// LOAD REQUESTS
// ==============================
function loadRequests() {
    if (!file_exists(REQUESTS_FILE)) {
        initRequestsFile();
    }
    $data = json_decode(file_get_contents(REQUESTS_FILE), true);
    return $data['requests'] ?? [];
}

// ==============================
// SAVE REQUESTS
// ==============================
function saveRequests($requests) {
    $data = ['requests' => $requests, 'last_id' => count($requests)];
    file_put_contents(REQUESTS_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

// ==============================
// ADD REQUEST
// ==============================
function addRequest($user_id, $movie_name, $username = '') {
    $requests = loadRequests();
    $request_id = time() . rand(100, 999);
    
    $requests[] = [
        'id' => $request_id,
        'user_id' => $user_id,
        'username' => $username,
        'movie_name' => $movie_name,
        'date' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];
    
    saveRequests($requests);
    bot_log("Request added: $movie_name by $user_id");
    return $request_id;
}

// ==============================
// GET USER REQUESTS
// ==============================
function getUserRequests($user_id) {
    $requests = loadRequests();
    $user_requests = [];
    foreach ($requests as $req) {
        if ($req['user_id'] == $user_id && $req['status'] == 'pending') {
            $user_requests[] = $req;
        }
    }
    return $user_requests;
}

// ==============================
// GET PENDING REQUESTS
// ==============================
function getPendingRequests() {
    $requests = loadRequests();
    $pending = [];
    foreach ($requests as $req) {
        if ($req['status'] == 'pending') {
            $pending[] = $req;
        }
    }
    return $pending;
}

// ==============================
// APPROVE REQUEST
// ==============================
function approveRequest($request_id) {
    $requests = loadRequests();
    foreach ($requests as &$req) {
        if ($req['id'] == $request_id) {
            $req['status'] = 'approved';
            $req['approved_date'] = date('Y-m-d H:i:s');
            saveRequests($requests);
            return true;
        }
    }
    return false;
}

// ==============================
// BULK APPROVE REQUESTS
// ==============================
function bulkApproveRequests($request_ids) {
    $requests = loadRequests();
    $approved_count = 0;
    
    foreach ($requests as &$req) {
        if (in_array($req['id'], $request_ids) && $req['status'] == 'pending') {
            $req['status'] = 'approved';
            $req['approved_date'] = date('Y-m-d H:i:s');
            $approved_count++;
        }
    }
    
    if ($approved_count > 0) {
        saveRequests($requests);
    }
    
    return $approved_count;
}

// ==============================
// BASIC LOGGING
// ==============================
function bot_log($message) {
    file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
}

// ==============================
// LOAD MOVIES FROM CSV
// ==============================
function load_movies() {
    if (!file_exists(CSV_FILE)) {
        return [];
    }
    
    $movies = [];
    $handle = fopen(CSV_FILE, "r");
    if ($handle !== FALSE) {
        fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (!empty($row[0]) && !empty($row[1]) && !empty($row[2])) {
                $name = strtolower(trim($row[0]));
                if (!isset($movies[$name])) {
                    $movies[$name] = [];
                }
                $movies[$name][] = [
                    'name' => $row[0],
                    'message_id' => $row[1],
                    'channel_id' => $row[2]
                ];
            }
        }
        fclose($handle);
    }
    return $movies;
}

// ==============================
// TELEGRAM API FUNCTIONS
// ==============================
function apiRequest($method, $params = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $options = [
        'http' => [
            'method' => 'POST',
            'content' => http_build_query($params),
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
        ]
    ];
    $context = stream_context_create($options);
    return @file_get_contents($url, false, $context);
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id, 
        'text' => $text, 
        'disable_web_page_preview' => true,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    return json_decode(apiRequest('sendMessage', $data), true);
}

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    $result = apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
    return json_decode($result, true);
}

function answerCallbackQuery($callback_id, $text = null) {
    $data = ['callback_query_id' => $callback_id];
    if ($text) $data['text'] = $text;
    return apiRequest('answerCallbackQuery', $data);
}

function sendChatAction($chat_id, $action = 'typing') {
    return apiRequest('sendChatAction', [
        'chat_id' => $chat_id,
        'action' => $action
    ]);
}

function deleteMessage($chat_id, $message_id) {
    return apiRequest('deleteMessage', [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ]);
}

function editMessageText($chat_id, $message_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    return apiRequest('editMessageText', $data);
}

// ==============================
// SEND MESSAGE WITH AUTO-DELETE
// ==============================
function sendMessageWithAutoDelete($chat_id, $text, $type, $reply_markup = null) {
    switch ($type) {
        case 'text':
            $duration = AutoDeleteManager::TEXT_DELETE;
            break;
        case 'search':
            $duration = AutoDeleteManager::SEARCH_DELETE;
            break;
        case 'movie':
            $duration = AutoDeleteManager::MOVIE_DELETE;
            break;
        default:
            $duration = AutoDeleteManager::TEXT_DELETE;
    }
    
    $warning = AutoDeleteManager::getWarningMessage($type, $duration);
    $full_text = $text . "\n\n" . $warning;
    
    $result = sendMessage($chat_id, $full_text, $reply_markup);
    
    if ($result && isset($result['ok']) && $result['ok']) {
        $message_id = $result['result']['message_id'];
        AutoDeleteManager::addToQueue($chat_id, $message_id, $type, $duration);
        return $result;
    }
    
    return $result;
}

// ==============================
// SEARCH FUNCTION
// ==============================
function search_movie($query, $movies) {
    $query = strtolower(trim($query));
    $results = [];
    
    foreach ($movies as $movie => $entries) {
        if (strpos($movie, $query) !== false) {
            $results[$movie] = $entries;
        }
    }
    
    uksort($results, function($a, $b) use ($query) {
        $pos_a = strpos($a, $query);
        $pos_b = strpos($b, $query);
        if ($pos_a === false) return 1;
        if ($pos_b === false) return -1;
        return $pos_a - $pos_b;
    });
    
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

// ==============================
// DELIVER MOVIE WITH ATTRIBUTION & AUTO-DELETE
// ==============================
function deliver_movie($chat_id, $movie_data, $requester_name = '', $requester_id = '') {
    $count = 0;
    
    foreach ($movie_data as $entry) {
        forwardMessage($chat_id, $entry['channel_id'], $entry['message_id']);
        $count++;
        usleep(200000);
    }
    
    $attribution = "\n\n━━━━━━━━━━━━━━━━━━━━\n";
    
    if (!empty($requester_name)) {
        if ($requester_id == ADMIN_ID) {
            $attribution .= "👑 <b>REQUESTED BY: ADMIN</b>\n";
        } else {
            $attribution .= "🎬 <b>REQUESTED BY:</b> " . $requester_name . "\n";
        }
        $attribution .= "🆔 <b>USER ID:</b> <code>" . $requester_id . "</code>\n";
        $attribution .= "📅 <b>DATE:</b> " . date('d-m-Y H:i:s') . "\n";
    }
    
    $attribution .= "🔗 <b>CHANNEL:</b> " . MAIN_CHANNEL_USERNAME . "\n";
    $attribution .= "━━━━━━━━━━━━━━━━━━━━\n";
    $attribution .= "💬 <b>Need help?</b> " . REQUEST_GROUP_USERNAME;
    
    $confirm_text = "✅ $count movie version(s) sent!\n\n📢 Join: " . MAIN_CHANNEL_USERNAME . $attribution;
    
    sendMessageWithAutoDelete($chat_id, $confirm_text, 'movie');
    
    bot_log("Movie delivered to $chat_id - " . $movie_data[0]['name'] . " - Requested by: $requester_name");
}

// ==============================
// SEND SEARCH RESULTS WITH AUTO-DELETE
// ==============================
function sendSearchResults($chat_id, $results, $query, $user_id, $username, $first_name) {
    $reply = "🔍 <b>Found " . count($results) . " movies for '" . htmlspecialchars($query) . "':</b>\n\n";
    $i = 1;
    foreach ($results as $movie => $entries) {
        $reply .= "$i. <b>" . ucwords($movie) . "</b> (" . count($entries) . " versions)\n";
        $i++;
    }
    
    sendMessageWithAutoDelete($chat_id, $reply, 'search');
    
    $keyboard = ['inline_keyboard' => []];
    foreach (array_slice(array_keys($results), 0, 5) as $movie) {
        $keyboard['inline_keyboard'][] = [['text' => "🎬 " . ucwords($movie), 'callback_data' => $movie]];
    }
    
    sendMessageWithAutoDelete($chat_id, "👇 <b>Click to download:</b>", 'search', $keyboard);
    
    global $user_sessions;
    $requester_name = !empty($username) ? "@" . $username : (!empty($first_name) ? $first_name : "User#" . $user_id);
    $user_sessions[$user_id] = [
        'results' => $results,
        'requester_name' => $requester_name,
        'requester_id' => $user_id
    ];
}

// ==============================
// GET ALL CHANNELS
// ==============================
function getAllChannels() {
    $channels = [];
    if (MAIN_CHANNEL_USERNAME) {
        $channels[] = ['username' => MAIN_CHANNEL_USERNAME, 'id' => MAIN_CHANNEL_ID, 'name' => 'Main Channel'];
    }
    if (CHANNEL2_USERNAME) {
        $channels[] = ['username' => CHANNEL2_USERNAME, 'id' => CHANNEL2_ID, 'name' => 'Channel 2'];
    }
    if (CHANNEL3_USERNAME) {
        $channels[] = ['username' => CHANNEL3_USERNAME, 'id' => CHANNEL3_ID, 'name' => 'Channel 3'];
    }
    if (BACKUP_CHANNEL_USERNAME) {
        $channels[] = ['username' => BACKUP_CHANNEL_USERNAME, 'id' => BACKUP_CHANNEL_ID, 'name' => 'Backup Channel'];
    }
    return $channels;
}

// ==============================
// COMMAND HANDLER
// ==============================
function handleCommand($chat_id, $text, $user_id, $username, $first_name, $is_admin = false) {
    global $movies_data;
    
    sendChatAction($chat_id, 'typing');
    
    // /start command
    if ($text == '/start') {
        $channels = getAllChannels();
        $welcome = "🎬 <b>Welcome to Movie Bot!</b>\n\n";
        $welcome .= "🔍 Simply type any movie name to search.\n";
        $welcome .= "📝 Examples: <code>kgf</code>, <code>pushpa</code>, <code>avengers</code>\n\n";
        $welcome .= "📢 <b>Our Channels:</b>\n";
        
        foreach ($channels as $channel) {
            $welcome .= "• " . $channel['username'] . "\n";
        }
        
        $welcome .= "\n💬 <b>Request Group:</b> " . REQUEST_GROUP_USERNAME . "\n\n";
        $welcome .= "💡 Use /help for all commands";
        
        $keyboard = ['inline_keyboard' => []];
        foreach ($channels as $channel) {
            $url = str_replace('@', '', $channel['username']);
            $keyboard['inline_keyboard'][] = [['text' => "📢 " . $channel['name'], 'url' => 'https://t.me/' . $url]];
        }
        $keyboard['inline_keyboard'][] = [['text' => '💬 Request Group', 'url' => 'https://t.me/EntertainmentTadka7860']];
        
        sendMessageWithAutoDelete($chat_id, $welcome, 'text', $keyboard);
        return;
    }
    
    // /help command
    if ($text == '/help') {
        $help = "🤖 <b>Movie Bot Commands</b>\n\n";
        $help .= "🎬 <code>/start</code> - Welcome message\n";
        $help .= "📖 <code>/help</code> - Show this help\n";
        $help .= "🔍 <code>/search movie_name</code> - Search movie\n";
        $help .= "📝 <code>/request movie_name</code> - Request a movie\n";
        $help .= "📋 <code>/myrequests</code> - Your pending requests\n";
        $help .= "📢 <code>/channels</code> - Show all channels\n";
        $help .= "💬 <code>/requestgroup</code> - Get request group link\n\n";
        
        if ($is_admin) {
            $help .= "👑 <b>Admin Commands:</b>\n";
            $help .= "📊 <code>/pending_requests</code> - All pending requests\n";
            $help .= "✅ <code>/bulk_approve</code> - Bulk approve requests\n";
            $help .= "📈 <code>/stats</code> - Bot statistics\n";
        }
        
        $help .= "\n💡 <b>Quick Search:</b> Just type movie name!";
        
        sendMessageWithAutoDelete($chat_id, $help, 'text');
        return;
    }
    
    // /request command
    if (strpos($text, '/request ') === 0) {
        $movie_name = trim(substr($text, 9));
        if (strlen($movie_name) < 2) {
            sendMessageWithAutoDelete($chat_id, "❌ Please enter a valid movie name.\n\nUsage: <code>/request Movie Name</code>", 'text');
            return;
        }
        
        $request_id = addRequest($user_id, $movie_name, $username);
        
        $group_msg = "📝 <b>New Movie Request</b>\n\n";
        $group_msg .= "🎬 Movie: <b>" . htmlspecialchars($movie_name) . "</b>\n";
        $group_msg .= "👤 User: @" . ($username ?: $user_id) . "\n";
        $group_msg .= "🆔 ID: <code>$request_id</code>\n";
        $group_msg .= "📅 Time: " . date('Y-m-d H:i:s');
        
        sendMessage(REQUEST_GROUP_ID, $group_msg);
        
        sendMessageWithAutoDelete($chat_id, "✅ <b>Request submitted!</b>\n\n🎬 Movie: " . htmlspecialchars($movie_name) . "\n🆔 Request ID: <code>$request_id</code>\n\n📢 We'll add it soon!\n💬 Join request group: " . REQUEST_GROUP_USERNAME, 'text');
        
        bot_log("Request submitted: $movie_name by $user_id");
        return;
    }
    
    // /myrequests command
    if ($text == '/myrequests') {
        $user_requests = getUserRequests($user_id);
        
        if (empty($user_requests)) {
            sendMessageWithAutoDelete($chat_id, "📭 <b>No pending requests</b>\n\nYou haven't requested any movies yet.\n\nUse <code>/request movie_name</code> to request!", 'text');
            return;
        }
        
        $msg = "📋 <b>Your Pending Requests</b>\n\n";
        foreach ($user_requests as $req) {
            $msg .= "🎬 " . htmlspecialchars($req['movie_name']) . "\n";
            $msg .= "🆔 <code>" . $req['id'] . "</code>\n";
            $msg .= "📅 " . $req['date'] . "\n\n";
        }
        $msg .= "📢 Request group: " . REQUEST_GROUP_USERNAME;
        
        sendMessageWithAutoDelete($chat_id, $msg, 'text');
        return;
    }
    
    // /channels command
    if ($text == '/channels') {
        $channels = getAllChannels();
        $msg = "📢 <b>Our Channels</b>\n\n";
        
        foreach ($channels as $channel) {
            $msg .= "• " . $channel['username'] . "\n";
        }
        
        $msg .= "\n💬 <b>Request Group:</b> " . REQUEST_GROUP_USERNAME;
        
        $keyboard = ['inline_keyboard' => []];
        foreach ($channels as $channel) {
            $url = str_replace('@', '', $channel['username']);
            $keyboard['inline_keyboard'][] = [['text' => "📢 " . $channel['name'], 'url' => 'https://t.me/' . $url]];
        }
        $keyboard['inline_keyboard'][] = [['text' => '💬 Request Group', 'url' => 'https://t.me/EntertainmentTadka7860']];
        
        sendMessageWithAutoDelete($chat_id, $msg, 'text', $keyboard);
        return;
    }
    
    // /requestgroup command
    if ($text == '/requestgroup') {
        $msg = "💬 <b>Request Group</b>\n\n";
        $msg .= "Join our request group for:\n";
        $msg .= "• Movie requests\n";
        $msg .= "• Support & help\n";
        $msg .= "• Updates & announcements\n\n";
        $msg .= "🔗 " . REQUEST_GROUP_USERNAME;
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💬 Join Request Group', 'url' => 'https://t.me/EntertainmentTadka7860']]
            ]
        ];
        
        sendMessageWithAutoDelete($chat_id, $msg, 'text', $keyboard);
        return;
    }
    
    // /search command
    if (strpos($text, '/search ') === 0) {
        $query = substr($text, 8);
        if (strlen($query) < 2) {
            sendMessageWithAutoDelete($chat_id, "❌ Please enter at least 2 characters to search.", 'text');
            return;
        }
        
        $results = search_movie($query, $movies_data);
        
        if (empty($results)) {
            sendMessageWithAutoDelete($chat_id, "❌ Movie not found: <b>" . htmlspecialchars($query) . "</b>\n\n💬 Request in group: " . REQUEST_GROUP_USERNAME . "\n📝 Use <code>/request " . htmlspecialchars($query) . "</code>", 'text');
            return;
        }
        
        sendSearchResults($chat_id, $results, $query, $user_id, $username, $first_name);
        return;
    }
    
    // /pending_requests (Admin only)
    if ($text == '/pending_requests' && $is_admin) {
        $pending = getPendingRequests();
        
        if (empty($pending)) {
            sendMessageWithAutoDelete($chat_id, "📭 <b>No pending requests</b>\n\nAll requests have been processed!", 'text');
            return;
        }
        
        $msg = "📋 <b>Pending Requests (" . count($pending) . ")</b>\n\n";
        
        foreach ($pending as $req) {
            $msg .= "🎬 <b>" . htmlspecialchars($req['movie_name']) . "</b>\n";
            $msg .= "🆔 <code>" . $req['id'] . "</code>\n";
            $msg .= "👤 @" . ($req['username'] ?: $req['user_id']) . "\n";
            $msg .= "📅 " . $req['date'] . "\n\n";
        }
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '✅ Approve All', 'callback_data' => 'approve_all']],
                [['text' => '❌ Close', 'callback_data' => 'close']]
            ]
        ];
        
        sendMessageWithAutoDelete($chat_id, $msg, 'text', $keyboard);
        return;
    }
    
    // /bulk_approve (Admin only)
    if ($text == '/bulk_approve' && $is_admin) {
        $pending = getPendingRequests();
        
        if (empty($pending)) {
            sendMessageWithAutoDelete($chat_id, "📭 No pending requests to approve!", 'text');
            return;
        }
        
        $request_ids = array_column($pending, 'id');
        $approved_count = bulkApproveRequests($request_ids);
        
        foreach ($pending as $req) {
            $user_msg = "✅ <b>Your request has been approved!</b>\n\n";
            $user_msg .= "🎬 Movie: " . htmlspecialchars($req['movie_name']) . "\n";
            $user_msg .= "🆔 Request ID: <code>" . $req['id'] . "</code>\n\n";
            $user_msg .= "📢 The movie will be added to our channel soon!\n";
            $user_msg .= "🔗 Join: " . MAIN_CHANNEL_USERNAME;
            
            sendMessage($req['user_id'], $user_msg);
        }
        
        sendMessageWithAutoDelete($chat_id, "✅ <b>Bulk approval completed!</b>\n\n📊 Approved: $approved_count requests\n👥 Notified: " . count($pending) . " users", 'text');
        
        bot_log("Bulk approved $approved_count requests by admin");
        return;
    }
    
    // /stats (Admin only)
    if ($text == '/stats' && $is_admin) {
        $movie_count = 0;
        foreach ($movies_data as $entries) {
            $movie_count += count($entries);
        }
        $unique_movies = count($movies_data);
        $file_size = file_exists(CSV_FILE) ? round(filesize(CSV_FILE) / 1024, 2) : 0;
        $pending_count = count(getPendingRequests());
        
        $channels = getAllChannels();
        $msg = "📊 <b>Bot Statistics</b>\n\n";
        $msg .= "🎬 Total Movies: $movie_count\n";
        $msg .= "📝 Unique Titles: $unique_movies\n";
        $msg .= "💾 Database Size: {$file_size} KB\n";
        $msg .= "📋 Pending Requests: $pending_count\n";
        $msg .= "📢 Total Channels: " . count($channels) . "\n";
        $msg .= "💬 Request Group: " . REQUEST_GROUP_USERNAME . "\n";
        $msg .= "📅 Last Updated: " . date('d-m-Y H:i:s');
        
        sendMessageWithAutoDelete($chat_id, $msg, 'text');
        return;
    }
    
    // DEFAULT - Movie search
    if (strlen($text) >= 2) {
        $results = search_movie($text, $movies_data);
        
        if (empty($results)) {
            sendMessageWithAutoDelete($chat_id, "❌ Movie not found: <b>" . htmlspecialchars($text) . "</b>\n\n💬 Request in group: " . REQUEST_GROUP_USERNAME . "\n📝 Use <code>/request " . htmlspecialchars($text) . "</code>", 'text');
            return;
        }
        
        if (count($results) == 1) {
            $movie_name = array_key_first($results);
            
            $requester_name = '';
            if (!empty($username)) {
                $requester_name = "@" . $username;
            } elseif (!empty($first_name)) {
                $requester_name = $first_name;
            } else {
                $requester_name = "User#" . $user_id;
            }
            
            deliver_movie($chat_id, $results[$movie_name], $requester_name, $user_id);
        } else {
            sendSearchResults($chat_id, $results, $text, $user_id, $username, $first_name);
        }
    } elseif (strlen($text) == 1) {
        sendMessageWithAutoDelete($chat_id, "❌ Please enter at least 2 characters to search.\n\nExample: <code>kgf</code> or <code>pushpa</code>\n📝 Request: <code>/request Movie Name</code>", 'text');
    }
}

// ==============================
// MAIN PROCESSING
// ==============================
initRequestsFile();
$movies_data = load_movies();
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    bot_log("Update received");
    
    // Handle callback queries
    if (isset($update['callback_query'])) {
        $query = $update['callback_query'];
        $chat_id = $query['message']['chat']['id'];
        $user_id = $query['from']['id'];
        $username = $query['from']['username'] ?? '';
        $first_name = $query['from']['first_name'] ?? '';
        $data = $query['data'];
        $callback_id = $query['id'];
        
        sendChatAction($chat_id, 'typing');
        
        if ($data == 'close') {
            deleteMessage($chat_id, $query['message']['message_id']);
            answerCallbackQuery($callback_id, "Closed");
        }
        elseif ($data == 'approve_all') {
            $pending = getPendingRequests();
            if (!empty($pending)) {
                $request_ids = array_column($pending, 'id');
                $approved_count = bulkApproveRequests($request_ids);
                
                foreach ($pending as $req) {
                    $user_msg = "✅ <b>Your request has been approved!</b>\n\n";
                    $user_msg .= "🎬 Movie: " . htmlspecialchars($req['movie_name']) . "\n";
                    $user_msg .= "🆔 Request ID: <code>" . $req['id'] . "</code>\n\n";
                    $user_msg .= "📢 The movie will be added soon!\n";
                    $user_msg .= "🔗 Join: " . MAIN_CHANNEL_USERNAME;
                    
                    sendMessage($req['user_id'], $user_msg);
                }
                
                sendMessageWithAutoDelete($chat_id, "✅ Approved $approved_count requests!", 'text');
                bot_log("Bulk approved $approved_count requests via callback");
                deleteMessage($chat_id, $query['message']['message_id']);
            }
            answerCallbackQuery($callback_id, "Approved all requests");
        }
        elseif (isset($movies_data[$data])) {
            $requester_name = '';
            if (!empty($username)) {
                $requester_name = "@" . $username;
            } elseif (!empty($first_name)) {
                $requester_name = $first_name;
            } else {
                $requester_name = "User#" . $user_id;
            }
            
            deliver_movie($chat_id, $movies_data[$data], $requester_name, $user_id);
            answerCallbackQuery($callback_id, "Sending " . ucwords($data) . "...");
        }
        else {
            answerCallbackQuery($callback_id, "Movie not found");
        }
    }
    
    // Handle messages
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $username = $message['from']['username'] ?? '';
        $first_name = $message['from']['first_name'] ?? '';
        $text = trim($message['text'] ?? '');
        
        if (!empty($text)) {
            $is_admin = ($user_id == ADMIN_ID);
            handleCommand($chat_id, $text, $user_id, $username, $first_name, $is_admin);
        }
    }
}

// ==============================
// DEFAULT PAGE
// ==============================
if (!isset($update) && php_sapi_name() !== 'cli') {
    $movie_count = 0;
    foreach ($movies_data as $entries) $movie_count += count($entries);
    $channels = getAllChannels();
    $pending = count(getPendingRequests());
    
    echo "<h1>🎬 Movie Bot</h1>";
    echo "<p><strong>Status:</strong> ✅ Running with Auto-Delete Feature</p>";
    echo "<p><strong>Movies in DB:</strong> " . $movie_count . "</p>";
    echo "<p><strong>Unique Titles:</strong> " . count($movies_data) . "</p>";
    echo "<p><strong>Total Channels:</strong> " . count($channels) . "</p>";
    echo "<p><strong>Request Group:</strong> " . REQUEST_GROUP_USERNAME . "</p>";
    echo "<p><strong>Pending Requests:</strong> " . $pending . "</p>";
    echo "<hr>";
    echo "<p>⏰ <strong>Auto-Delete Timers:</strong></p>";
    echo "<ul>";
    echo "<li>📝 Text Messages: 10 seconds</li>";
    echo "<li>🔍 Search Results: 60 seconds</li>";
    echo "<li>🎬 Movie Files: 300 seconds (5 minutes)</li>";
    echo "</ul>";
}
?>
