<?php
date_default_timezone_set("UTC");
// ========================
// CONFIGURATION
// ========================
// MODIFIE ICI : ton ID Telegram (celui que tu as donne a BotFather)
define('ADMIN_ID', TON_ID_TELEGRAM_ICI);
define('DB_PATH', __DIR__ . '/rovasprono.db');
// REMPLACE PAR TON NOUVEAU TOKEN DU BOT 2 (Rovaspredict - via @BotFather)
define('BOT_TOKEN', '8601017974:AAERo3CGyY8Zab0K9h36eQsVtpnYmRPySR0');
define('CHANNELS', ['@ROVASOFFICIEL']);

// ========================
// DATABASE SETUP
// ========================
try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table with enhanced fields
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY,
        username TEXT,
        first_name TEXT,
        last_name TEXT,
        balance REAL DEFAULT 0.0,
        last_run_at INTEGER,
        language TEXT,
        state TEXT,
        is_banned BOOLEAN DEFAULT 0,
        join_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_active DATETIME DEFAULT CURRENT_TIMESTAMP,
        win_id TEXT
    )");
    
    // Create logs table
    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    exit;
}

// ========================
// HELPER FUNCTIONS
// ========================
function getUser(int $userId): ?array {
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindValue(':id', $userId);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function saveUser(array $userData): void {
    global $db;
    $stmt = $db->prepare("REPLACE INTO users (
        id, username, first_name, last_name, balance, 
        last_run_at, language, state, is_banned, win_id
    ) VALUES (
        :id, :username, :first_name, :last_name, :balance, 
        :last_run_at, :language, :state, :is_banned, :win_id
    )");
    
    $stmt->execute([
        ':id' => $userData['id'],
        ':username' => $userData['username'] ?? '',
        ':first_name' => $userData['first_name'] ?? '',
        ':last_name' => $userData['last_name'] ?? '',
        ':balance' => $userData['balance'] ?? 0.0,
        ':last_run_at' => $userData['last_run_at'] ?? null,
        ':language' => $userData['language'] ?? 'en',
        ':state' => $userData['state'] ?? null,
        ':is_banned' => $userData['is_banned'] ?? 0,
        ':win_id' => $userData['win_id'] ?? null
    ]);
}

function logAction(int $userId, string $action): void {
    global $db;
    $stmt = $db->prepare("INSERT INTO logs (user_id, action) VALUES (:user_id, :action)");
    $stmt->execute([':user_id' => $userId, ':action' => $action]);
}

function sendMessage(int $chatId, string $text, string $parseMode = 'HTML', ?array $replyMarkup = null, bool $disablePreview = false): void {
    $url = "https://api.telegram.org/bot".BOT_TOKEN."/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => $disablePreview
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function answerCallback(string $callbackId, string $text, bool $showAlert = false): void {
    $url = "https://api.telegram.org/bot".BOT_TOKEN."/answerCallbackQuery";
    $data = [
        'callback_query_id' => $callbackId,
        'text' => $text,
        'show_alert' => $showAlert
    ];
    
    $options = [
        'http' => [
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function deleteMessage(int $chatId, int $messageId): void {
    $url = "https://api.telegram.org/bot".BOT_TOKEN."/deleteMessage";
    $data = ['chat_id' => $chatId, 'message_id' => $messageId];
    
    $options = [
        'http' => [
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function getChatMemberStatus(string $channel, int $userId): string {
    $url = "https://api.telegram.org/bot".BOT_TOKEN."/getChatMember";
    $data = [
        'chat_id' => $channel,
        'user_id' => $userId
    ];
    
    $options = [
        'http' => [
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $data = json_decode($response, true);
    
    return $data['result']['status'] ?? 'left';
}

// ========================
// COMMAND HANDLERS
// ========================
function handleStart(int $userId, array $userData): void {
    // Create/update user record
    $user = getUser($userId) ?? [
        'id' => $userId,
        'username' => $userData['username'] ?? '',
        'first_name' => $userData['first_name'] ?? '',
        'last_name' => $userData['last_name'] ?? '',
        'balance' => 0.0
    ];
    saveUser($user);
    
    // Send welcome message
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "✅ Rejoindre le canal", 'url' => "https://t.me/ROVASOFFICIEL"]],
            [['text' => "🔍 Vérifié", 'callback_data' => "P1"]]
        ]
    ];
    
    sendMessage(
        $userId,
        "<strong>📮 BIENVENUE DANS ROVAS PREDICTOR – LUCKY JET</strong>\n\nPour continuer, veuillez rejoindre notre canal Telegram.",
        'HTML',
        $keyboard
    );
    
    logAction($userId, 'start_command');
}

function handleP1(int $userId, array $callback): void {
    $user = getUser($userId);
    if (!$user) return;
    
    // Check if banned
    if ($user['is_banned']) {
        sendMessage($userId, "*Il vous est interdit d'utiliser le bot ❌*", 'Markdown');
        return;
    }
    
    // Check channel subscriptions
    $allSubscribed = true;
    foreach (CHANNELS as $channel) {
        $status = getChatMemberStatus($channel, $userId);
        if (!in_array($status, ['member', 'administrator', 'creator'])) {
            $allSubscribed = false;
            break;
        }
    }
    
    // Process based on subscription status
    if ($allSubscribed) {
        // Update user state and proceed
        $user['state'] = 'verified';
        saveUser($user);
        handleP2($userId);
        
        // Answer callback
        if (isset($callback['id'])) {
            answerCallback($callback['id'], "😉 Vous êtes autorisé(e)s Maintenant", true);
        }
    } else {
        // Not subscribed
        if (isset($callback['id'])) {
            answerCallback($callback['id'], "❌😓Oups vous devez forcément rejoindre le canal", true);
        }
        handleStart($userId, $user);
    }
    
    logAction($userId, 'verification_attempt');
}

function handleP2(int $userId): void {
    $keyboard = [
        'keyboard' => [
            ['Signal premium 🥷'],
            ['Aide✉️']
        ],
        'resize_keyboard' => true
    ];
    
    sendMessage(
        $userId,
        "🙌 Bienvenue Dans Lucky jet 🚀 pro Signal de ROVAS 😎😎💸💸",
        'HTML',
        $keyboard
    );
    
    logAction($userId, 'access_granted');
}

function handleSignalPremium(int $userId): void {
    $user = getUser($userId);
    if (!$user) return;
    
    // Check balance status
    $balance = $user['balance'];
    $formattedBalance = number_format($balance, 2);
    
    if ($formattedBalance == "1.20") {
        sendMessage($userId, "Veuillez Patienter, Votre demande est toujours en cours de vérification. Nous allons vous notifié et vous recevrez les predictions une fois la vérification terminée");
    } elseif ($formattedBalance == "1.50") {
        handleSectio1($userId);
    } else {
        // Reset balance and start verification
        $user['balance'] = 0.0;
        saveUser($user);
        handleCheckSubscription($userId);
    }
    
    logAction($userId, 'signal_premium_request');
}

function handleSectio1(int $userId): void {
    $user = getUser($userId);
    if (!$user) return;
    
    // Rate limiting check
    $now = time();
    if ($user['last_run_at'] && ($now - $user['last_run_at']) < 180) {
        $waitTime = 3 - ceil(($now - $user['last_run_at']) / 60);
        sendMessage($userId, "Veuillez patienter pendant au moins $waitTime min pour demander une autre 🚀");
        return;
    }
    
    // Update last run time
    $user['last_run_at'] = $now;
    saveUser($user);
    
    // Generate prediction times
    $time1 = date('H:i', $now + 120);
    $time2 = date('H:i', $now + 180);
    // Generate coefficients
    $coefficient1 = number_format(mt_rand(400, 600) / 100, 2);
    $coefficient2 = number_format(mt_rand(1000, 2300) / 100, 2);
    
    // Format message
    $message = <<<MSG
<b><u>LUCKY JET PREDICTION</u></b>
┏━━━━━━━━━━━━━
┠ ⭓➤𝐇𝐄𝐔𝐑𝐄 : $time1 - $time2 ⏰

┠ ⭓➤𝐂𝐎𝐓𝐄 : {$coefficient2}X+ 🚀

┠ ⭓➤𝐀𝐒𝐒𝐔𝐑𝐀𝐍𝐂𝐄 : {$coefficient1}X+ ✅
┗━━━━━━━━━━━━━
<a href='https://1wimtk.com/v3/landing-page/casino?p=v9cf'>S' INSCRIRE SUR 1WIN ICI...</a>

𝚌𝚘𝚍𝚎 𝚙𝚛𝚘𝚖𝚘: <b>𝐑𝐎𝐕𝐀𝐒</b>
MSG;

    sendMessage($userId, $message, 'HTML', null, true);
    
    logAction($userId, 'prediction_generated');
}

function handleCheckSubscription(int $userId): void {
    $user = getUser($userId);
    if (!$user) return;
    
    // Language detection
    $lang = $user['language'] ?? 'en';
    $messages = [
        'fr' => [
            'enter_id' => "Bonjour {$user['first_name']}, veuillez entrer votre id 1win de votre compte que vous avez créé avec le code promo *ROVAS*",
            'wait_verification' => "Veuillez patienter, votre demande est toujours en cours de vérification. Nous allons vous notifier et vous recevrez les prédictions une fois la vérification terminée.",
            'verification_complete' => "Votre vérification est terminée. Vous allez recevoir les prédictions."
        ],
        'en' => [
            'enter_id' => "Hello {$user['first_name']}, please enter your 1win account ID that you just created with the promo code *ROVAS*",
            'wait_verification' => "Please wait, your request is still under verification. We will notify you and you will receive the predictions once the verification is complete.",
            'verification_complete' => "Your verification is complete. You will receive the predictions."
        ]
    ];
    
    // Set user state for ID input
    $user['state'] = 'ANA';
    saveUser($user);
    
    // Send appropriate message
    if ($user['balance'] == 0.0) {
        sendMessage($userId, $messages[$lang]['enter_id'], 'Markdown');
    } elseif (number_format($user['balance'], 2) == "1.20") {
        sendMessage($userId, $messages[$lang]['wait_verification']);
    } else {
        handleP2($userId);
    }
    
    logAction($userId, 'subscription_check');
}

function handleAdminRef(int $adminId, string $command, string $targetUserId): void {
    if ($adminId != ADMIN_ID) {
        sendMessage($adminId, "Vous n'êtes pas administrateur.");
        return;
    }
    
    if (!is_numeric($targetUserId)) {
        sendMessage($adminId, "Ce n'est pas un ID Telegram valide.");
        return;
    }
    
    // Reset target user's balance
    $targetUser = getUser($targetUserId);
    if ($targetUser) {
        $targetUser['balance'] = 0.0;
        saveUser($targetUser);
    }
    
    // Notify target user
    sendMessage($targetUserId, "❌ Inscris-toi avec le code ROVAS, puis recharge ton compte. Ensuite, envoie ton ID au bot pour l'activer ✅.\nSi ça ne marche pas, clique sur 👉 /start pour réessayer.");
    
    // Notify admin
    sendMessage($adminId, "* 🤴 User ID : $targetUserId\n\n💫 STATUT : Non à approuver*", 'Markdown');
    
    logAction($adminId, "admin_ref_command: $targetUserId");
}

function handleAdminNotification(int $userId, string $winId): void {
    $user = getUser($userId);
    if (!$user) return;
    
    // Save WIN ID
    $user['win_id'] = $winId;
    $user['state'] = 'pending';
    $user['balance'] = 1.20;
    saveUser($user);
    
    // Notify admin
    $adminMessage = "📝 Nouvelle demande de vérification\n\n"
        . "👤 User: @{$user['username']} ({$user['id']})\n"
        . "🏷️ WIN ID: $winId\n\n"
        . "/ACP_{$user['id']} pour accepter\n"
        . "/REF_{$user['id']} pour refuser";
    
    sendMessage(ADMIN_ID, $adminMessage);
    
    // Notify user
    sendMessage(
        $userId,
        "✅ Votre ID 1win ($winId) a été enregistré avec succès.\n"
        . "Nous vérifions votre inscription avec le code promo ROVAS et votre dépôt.\n"
        . "Vous recevrez une notification une fois la vérification terminée."
    );
    
    logAction($userId, 'win_id_submitted');
}

function handleAdminApprove(int $adminId, string $targetUserId): void {
    if ($adminId != ADMIN_ID) return;
    
    $targetUser = getUser($targetUserId);
    if (!$targetUser) {
        sendMessage($adminId, "Utilisateur introuvable.");
        return;
    }
    
    // Approve user
    $targetUser['balance'] = 1.50;
    saveUser($targetUser);
    
    // Notify user
    sendMessage(
        $targetUserId,
        "🎉 Votre compte a été vérifié avec succès !\n"
        . "Vous pouvez maintenant accéder aux signaux premium.\n\n"
        . "Cliquez sur 'Signal premium 🥷' pour commencer."
    );
    
    // Notify admin
    sendMessage($adminId, "✅ Utilisateur {$targetUser['id']} approuvé avec succès !");
    
    logAction($adminId, "admin_approve: $targetUserId");
}

function handleRef0(int $userId): void {
    $user = getUser($userId);
    if (!$user) return;
    
    sendMessage(
        $userId,
        "❌ Format d'ID invalide !\n"
        . "Votre ID 1win doit être un numéro de 8-9 chiffres.\n\n"
        . "Veuillez réessayer :"
    );
    
    logAction($userId, 'invalid_win_id_format');
}

// ========================
// MAIN REQUEST HANDLER
// ========================
$update = json_decode(file_get_contents('php://input'), true);
if (!$update) exit;

try {
    // Handle callback queries
    if (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $userId = $callback['from']['id'];
        $data = $callback['data'];
        
        switch ($data) {
            case 'P1':
                handleP1($userId, $callback);
                break;
        }
    }
    // Handle regular messages
    elseif (isset($update['message']['text'])) {
        $message = $update['message'];
        $userId = $message['from']['id'];
        $text = trim($message['text']);
        
        // Get or create user
        $user = getUser($userId);
        if (!$user) {
            $user = [
                'id' => $userId,
                'username' => $message['from']['username'] ?? '',
                'first_name' => $message['from']['first_name'] ?? '',
                'last_name' => $message['from']['last_name'] ?? '',
                'language' => $message['from']['language_code'] ?? 'en'
            ];
            saveUser($user);
        }
        
        // Handle commands
        switch ($text) {
            case '/start':
            case '/ferma':
                handleStart($userId, $message['from']);
                break;
                
            case 'Signal premium 🥷':
                handleSignalPremium($userId);
                break;
                
            case 'Aide✉️':
                sendMessage(
                    $userId,
                    "<strong><u>📲   AIDE  ET  SUPPORT</u></strong>\n\n\nBesoin d'aide ? Voici nos contacts :\n\n🔹 Support :  @PARTRICK09\n🔹 Canal officiel :  @ROVASOFFICIEL\n<blockquote>⚠️ Attention aux arnaques, utilisez uniquement nos contacts officiels !</blockquote>",
                    'HTML'
                );
                break;
                
            case 'PROD1#':
            case 'back':
            case 'retour':
                handleCheckSubscription($userId);
                break;
                
            default:
                // Handle state-based commands
                if ($user['state'] === 'ANA') {
                    // Handle 1win ID processing
                    if (!preg_match('/^\d{8,9}$/', $text)) {
                        handleRef0($userId);
                    } else {
                        handleAdminNotification($userId, $text);
                    }
                }
                // Handle admin commands
                elseif (strpos($text, '/REF_') === 0 && $userId == ADMIN_ID) {
                    $parts = explode('_', $text);
                    handleAdminRef($userId, $parts[0], $parts[1] ?? '');
                }
                elseif (strpos($text, '/ACP_') === 0 && $userId == ADMIN_ID) {
                    $parts = explode('_', $text);
                    handleAdminApprove($userId, $parts[1] ?? '');
                }
        }
    }
} catch (Exception $e) {
    sendMessage(ADMIN_ID, "❌ Bot error: " . $e->getMessage());
}
