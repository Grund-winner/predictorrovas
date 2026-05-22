<?php
require_once 'config.php';

// MODIFIE ICI : URL de ton nouveau site web (apres hebergement)
// Exemple: 'https://tonsite.infinityfree.com/Auto/rovas/rovassoft-main/'
define('WEB_APP_URL', 'https://rovasb-app.onrender.com/');

// Initialize SQLite database
$db = new SQLite3(DB_PATH);
$db->exec('CREATE TABLE IF NOT EXISTS users (
    user_id INTEGER PRIMARY KEY,
    language TEXT DEFAULT "en",
    isregistered TEXT,
    country TEXT,
    isdeposit TEXT,
    deposit_amount TEXT,
    deposit_transactionid TEXT
)');

// Helper functions
function saveUserData($userId, $key, $value) {
    global $db;

    // Table ke actual columns allow karo
    $allowed = ['language', 'isregistered', 'isdeposit', 'country', 'deposit_amount', 'deposit_transactionid'];
    if (!in_array($key, $allowed)) {
        throw new Exception("Invalid column name: $key");
    }

    // Update karo
    $stmt = $db->prepare("UPDATE users SET $key = :value WHERE user_id = :user_id");
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $stmt->bindValue(':value', $value, SQLITE3_TEXT);
    $stmt->execute();

    // Agar update nahi hua (user exist nahi karta)
    if ($db->changes() === 0) {
        // Nayi row insert karo with defaults
        $stmt = $db->prepare("INSERT INTO users (user_id, $key) VALUES (:user_id, :value)");
        $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':value', $value, SQLITE3_TEXT);
        $stmt->execute();
    }
}

function getUserData($userId, $key) {
    global $db;
    $stmt = $db->prepare('SELECT ' . $key . ' FROM users WHERE user_id = :user_id');
    $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row ? $row[$key] : null;
}

function telegramRequest($method, $data) {
    $url = "https://api.telegram.org/bot" . TOKEN . "/$method";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function sendMessage($chatId, $text, $replyMarkup = null, $parseMode = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text
    ];
    if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
    if ($parseMode) $data['parse_mode'] = $parseMode;
    return telegramRequest('sendMessage', $data);
}

function sendPhoto($chatId, $photo, $caption = null, $replyMarkup = null, $parseMode = null) {
    $data = [
        'chat_id' => $chatId,
        'photo' => $photo
    ];
    if ($caption) $data['caption'] = $caption;
    if ($replyMarkup) $data['reply_markup'] = json_encode($replyMarkup);
    if ($parseMode) $data['parse_mode'] = $parseMode;
    return telegramRequest('sendPhoto', $data);
}

function deleteMessage($chatId, $messageId) {
    return telegramRequest('deleteMessage', [
        'chat_id' => $chatId,
        'message_id' => $messageId
    ]);
}

function answerCallbackQuery($callbackId, $text = null, $showAlert = false) {
    $data = ['callback_query_id' => $callbackId];
    if ($text) $data['text'] = $text;
    if ($showAlert) $data['show_alert'] = true;
    return telegramRequest('answerCallbackQuery', $data);
}

function checkMembership($userId) {
    $response = telegramRequest('getChatMember', [
        'chat_id' => '@' . CHANNEL,
        'user_id' => $userId
    ]);
    return isset($response['result']['status']) && 
           in_array($response['result']['status'], ['member', 'administrator', 'creator']);
}

// Language dictionaries
$step1_texts = [
    "en" => "🔹To fully enjoy the bot, follow these 3 steps: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Click the <b>REGISTER</b> button to create a new account
◆ If you already have an account, log out and create a new one
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Use the promo code <b>ROVAS</b> during registration
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 A confirmation notification will be sent automatically after registration.✅
⟣━━━━━━━━━━━━━━⟢",
    "hi" => "🔹बॉट का पूरा लाभ उठाने के लिए, इन 3 चरणों का पालन करें: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦नया खाता बनाने के लिए <b>पंजीकरण</b> बटन दबाएं
◆ यदि आपका पहले से खाता है, लॉग आउट करें फिर नया बनाएं
⟣━━━━━━━━━━━━━━⟢
◉ 2➦पंजीकरण के दौरान प्रोमो कोड <b>ROVAS</b> का उपयोग करें
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 पंजीकरण के बाद स्वचालित रूप से पुष्टि अधिसूचना भेजी जाएगी।✅
⟣━━━━━━━━━━━━━━⟢",
    "ru" => "🔹Чтобы в полной мере пользоваться ботом, выполните эти 3 шага: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Нажмите кнопку <b>РЕГИСТРАЦИЯ</b>, чтобы создать новый аккаунт
◆ Если у вас уже есть аккаунт, выйдите из него и создайте новый
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Используйте промокод <b>ROVAS</b> при регистрации
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 После регистрации уведомление о подтверждении будет отправлено автоматически.✅
⟣━━━━━━━━━━━━━━⟢",
    "pt" => "🔹Para aproveitar totalmente o bot, siga estes 3 passos: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Clique no botao <b>REGISTRO</b> para criar uma nova conta
◆ Se voce ja tem uma conta, faca logout e crie uma nova
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Use o codigo promocional <b>ROVAS</b> durante o registro
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Uma notificacao de confirmacao sera enviada automaticamente apos o registro.✅
⟣━━━━━━━━━━━━━━⟢",
    "es" => "🔹Para disfrutar plenamente del bot, siga estos 3 pasos: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Presione el boton <b>REGISTRO</b> para crear una nueva cuenta
◆ Si ya tiene una cuenta, cierre sesion y cree una nueva
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Use el codigo promocional <b>ROVAS</b> durante el registro
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Una notificacion de confirmacion se enviara automaticamente despues del registro.✅
⟣━━━━━━━━━━━━━━⟢",
    "uz" => "🔹Botdan to'liq foydalanish uchun, quyidagi 3 qadamni bajaring: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Yangi hisob yaratish uchun <b>RO'YXATDAN O'TISH</b> tugmasini bosing
◆ Agar hisobingiz bo'lsa, chiqib qayta kirib yangi hisob yarating
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Ro'yxatdan o'tishda promo kod <b>ROVAS</b> dan foydalaning
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Ro'yxatdan o'tgandan so'ng tasdiqlash xabarnomasi avtomatik yuboriladi.✅
⟣━━━━━━━━━━━━━━⟢",
    "az" => "🔹Botdan tam istifade etmek ucun, bu 3 addimi izleyin: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Yeni hesab yaratmaq ucun <b>QEYDIYYAT</b> duymesine basin
◆ Hesabiniz varsa, cixis edib yeni hesab yaradin
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Qeydiyyat zamani promo kod <b>ROVAS</b> istifade edin
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Qeydiyyatdan sonra tesdiq bildirisi avtomatik gonderilecek.✅
⟣━━━━━━━━━━━━━━⟢",
    "tr" => "🔹Bottan tam olarak yararlanmak icin su 3 adimi izleyin: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Yeni hesap olusturmak icin <b>KAYIT</b> butonuna tiklayin
◆ Zaten hesabiniz varsa, cikis yapip yeni bir hesap olusturun
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Kayit sirasinda promo kod <b>ROVAS</b> kullanin
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Kayittan sonra onay bildirimi otomatik olarak gonderilecektir.✅
⟣━━━━━━━━━━━━━━⟢",
    "ar" => "🔹للاستمتاع الكامل بالبوت، اتبع هذه الخطوات الثلاث: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦اضغط على زر <b>التسجيل</b> لإنشاء حساب جديد
◆ إذا كان لديك حساب بالفعل، قم بتسجيل الخروج ثم أنشئ حسابً جديدًا
⟣━━━━━━━━━━━━━━⟢
◉ 2➦استخدم رمز الترويجة <b>ROVAS</b> عند التسجيل
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 سيتم إرسال إشعار تأكيد تلقائيًا بعد التسجيل.✅
⟣━━━━━━━━━━━━━━⟢",
    "fr" => "🔹Pour profiter pleinement du bot, suivez ces 3 etapes : ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Appuyez sur le bouton <b>INSCRIPTION</b> pour creer un nouveau compte
◆ Si vous avez deja un compte, deconnectez-vous puis creez-en un nouveau
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Utiliser le code promo <b>ROVAS</b> lors de l'inscription
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Une notification de confirmation vous sera envoyee automatiquement apres l'inscription.✅
⟣━━━━━━━━━━━━━━⟢",
];

$step2_texts = [
    "en" => "❂ Congratulations! Your registration has been completed successfully 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦Click now on the “RECHARGE” button
◆Make a minimum deposit of <b>\$5</b> on your 1win account to activate the bot
━━━━━━━━━━━━━━━━
◉ 2➦Once the deposit is confirmed on your account ✅
◆The bot will be automatically activated and you will be able to access the various PREDICTORS",
    "hi" => "❂ बधाई! आपका पंजीकरण सफलतापूर्वक पूरा हो गया 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦अब “रिचार्ज” बटन पर क्लिक करें
◆बॉट को सक्रिय करने के लिए अपने 1win खाते पर न्यूनतम <b>\$5</b> जमा करें
━━━━━━━━━━━━━━━━
◉ 2➦एक बार जमा आपके खाते पर पुष्टि हो जाने पर ✅
◆बॉट स्वचालित रूप से सक्रिय हो जाएगा और आप विभिन्न PREDICTORS तक पहुँच सकेंगे",
    "ru" => "❂ Поздравляем! Ваша регистрация успешно завершена 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦Нажмите сейчас на кнопку ПОПОЛНИТЬ
◆Внесите минимум <b>\$5</b> на свой счёт 1win, чтобы активировать бота
━━━━━━━━━━━━━━━━
◉ 2➦После подтверждения пополнения на вашем счёте ✅
◆Бот будет автоматически активирован и вы получите доступ к различным PREDICTORS",
    "pt" => "❂ Parabens! Seu registro foi concluido com sucesso 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦Clique agora no botao “RECARREGAR”
◆Faca um deposito minimo de <b>\$5</b> na sua conta 1win para ativar o bot
━━━━━━━━━━━━━━━━
◉ 2➦Uma vez que o deposito for confirmado na sua conta ✅
◆O bot sera ativado automaticamente e voce podera acessar os diferentes PREDICTORS",
    "es" => "❂ Felicidades! Su registro se ha completado con exito 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦Haga clic ahora en el boton “RECARGAR”
◆Realice un deposito minimo de <b>\$5</b> en su cuenta 1win para activar el bot
━━━━━━━━━━━━━━━━
◉ 2➦Una vez confirmado el deposito en su cuenta ✅
◆El bot se activara automaticamente y podra acceder a los diferentes PREDICTORS",
    "uz" => "❂ Tabriklaymiz! Sizning ro'yxatingiz muvaffaqiyatli yakunlandi 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦Endi “TO'LDIRISH” tugmasini bosing
◆Botni faollashtirish uchun 1win hisobingizga kamida <b>\$5</b> to'ldiring
━━━━━━━━━━━━━━━━
◉ 2➦Hisobingizda to'lov tasdiqlangach ✅
◆Bot avtomatik faollashadi va siz turli PREDICTORLARGA kirishingiz mumkin bo'ladi",
    "az" => "❂ Təbrikler! Qeydiyyatınız uğurla tamamlandı 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦İndi “DOLDUR” düyməsinə basın
◆Botu aktivləşdirmək üçün 1win hesabınıza minimum <b>\$5</b> daxil edin
━━━━━━━━━━━━━━━━
◉ 2➦Hesabınızda ödəniş təsdiqləndikdən sonra ✅
◆Bot avtomatik olaraq aktivləşəcək və siz müxtəlif PREDICTORLARA çatacaqsınız",
    "tr" => "❂ Tebrikler! Kaydınız başarıyla tamamlandı 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦Şimdi “YATIR” butonuna tıklayın
◆Botu aktif etmek için 1win hesabınıza minimum <b>\$5</b> yatırın
━━━━━━━━━━━━━━━━
◉ 2➦Hesabınızda ödeme onaylandıktan sonra ✅
◆Bot otomatik olarak aktif olacak ve çeşitli PREDICTORLARA erişebileceksiniz",
    "ar" => "❂ مبارك! تم تسجيلك بنجاح 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦اضغط الآن على زر إعادة الشحن
◆قم بإيداع حد أدنى <b>\$5</b> في حسابك 1win لتفعيل البوت
━━━━━━━━━━━━━━━━
◉ 2➦بمجرد تأكيد الإيداع في حسابك ✅
◆سيتم تفعيل البوت تلقائيًا وستتمكن من الوصول إلى PREDICTORS المختلفة",
    "fr" => "❂ Félicitations ! Votre inscription a été effectuée avec succès 🎉🌟
━━━━━━━━━━━━━━━━
◉ 1➦Cliquez maintenant sur le bouton « RECHARGER »
◆Effectuez un dépôt minimum de <b>\$5</b> sur votre compte 1win afin d’activer le bot
━━━━━━━━━━━━━━━━
◉ 2➦Une fois le dépôt confirmé sur votre compte ✅
◆Le bot sera automatiquement activé et vous pourrez accéder aux différents PREDICTORS",
];

$deposit_success_texts = [
    "en" => "✅ Deposit received successfully!\n\n💰 Amount: {amount}\n🌍 Country: {country}\n🔖 Transaction ID: {transactionid}\n\nYou now have full access to the bot's features.",
    "hi" => "✅ जमा सफलतापूर्वक प्राप्त हुआ!\n\n💰 राशि: {amount}\n🌍 देश: {country}\n🔖 लेनदेन आईडी: {transactionid}\n\nअब आपके पास बॉट की सभी सुविधाओं तक पूर्ण पहुंच है।",
    "ru" => "✅ Депозит успешно получен!\n\n💰 Сумma: {amount}\n🌍 Страна: {country}\n🔖 ID транзакции: {transactionid}\n\nТеперь у вас есть полный доступ к функциям бота.",
    "pt" => "✅ Depósito recebido com sucesso!\n\n💰 Valor: {amount}\n🌍 País: {country}\n🔖 ID da transação: {transactionid}\n\nAgora você tem acesso total aos recursos do bot.",
    "es" => "✅ Depósito recibido con éxito!\n\n💰 Monto: {amount}\n🌍 País: {country}\n🔖 ID de transacción: {transactionid}\n\nAhora tienes acceso completo a las funciones del bot.",
    "uz" => "✅ Depozit muvaffaqiyatli qabul qilindi!\n\n💰 Miqdor: {amount}\n🌍 Mamlakat: {country}\n🔖 Tranzaksiya ID: {transactionid}\n\nEndi siz botning barcha imkoniyatlaridan to'liq foydalanasiz.",
    "az" => "✅ Depozit uğurla qəbul edildi!\n\n💰 Məbləğ: {amount}\n🌍 Ölkə: {country}\n🔖 Əməliyyat ID: {transactionid}\n\nİndi botun bütün funksiyalarına tam girişiniz var.",
    "tr" => "✅ Para yatırma işlemi başarıyla alındı!\n\n💰 Miktar: {amount}\n🌍 Ülke: {country}\n🔖 İşlem Kimliği: {transactionid}\n\nArtık botun tüm özelliklerine tam erişiminiz var.",
    "ar" => "✅ تم استلام الإيداع بنجاح!\n\n💰 المبلغ: {amount}\n🌍 الدولة: {country}\n🔖 معرف المعاملة: {transactionid}\n\nلديك الآن وصول كامل إلى ميزات البوت.",
    "fr" => "✅ Dépôt reçu avec succès !\n\n💰 Montant : {amount}\n🌍 Pays : {country}\n🔖 ID de transaction : {transactionid}\n\nVous avez maintenant un accès complet aux fonctionnalités du bot."
];

$account_status_texts = [
    "en" => "✅ Account Status: Registered & Deposit Completed\n\n● Registration: Completed\n● Deposit: Completed\n● Country: {country}\n\nYou have full access to all features.",
    "hi" => "✅ खाता स्थिति: पंजीकृत और जमा पूर्ण\n\n● पंजीकरण: पूर्ण\n● जमा: पूर्ण\n● देश: {country}\n\nआपके पास सभी सुविधाओं तक पूर्ण पहुंच है।",
    "ru" => "✅ Статус аккаунта: Регистрация и депозит завершены\n\n● Регистрация: Завершена\n● Депозит: Завершен\n● Страна: {country}\n\nУ вас есть полный доступ ко всем функциям.",
    "pt" => "✅ Status da conta: Registro e depósito concluídos\n\n● Registro: Concluído\n● Depósito: Concluído\n● País: {country}\n\nVocê tem acesso total a todos os recursos.",
    "es" => "✅ Estado de la cuenta: Registro y depósito completados\n\n● Registro: Completado\n● Depósito: Completado\n● País: {country}\n\nTienes acceso completo a todas las funciones.",
    "uz" => "✅ Hisob holati: Ro'yxatdan o'tilgan va depozit yakunlangan\n\n● Ro'yxatdan o'tish: Yakunlangan\n● Depozit: Yakunlangan\n● Mamlakat: {country}\n\nSiz barcha funksiyalardan to'liq foydalanasiz.",
    "az" => "✅ Hesab statusu: Qeydiyyat və depozit tamamlandı\n\n● Qeydiyyat: Tamamlandı\n● Depozit: Tamamlandı\n● Ölkə: {country}\n\nBütün funksiyalara tam girişiniz var.",
    "tr" => "✅ Hesap durumu: Kayıt ve para yatırma tamamlandı\n\n● Kayıt: Tamamlandı\n● Para yatırma: Tamamlandı\n● Ülke: {country}\n\nTüm özelliklere tam erişiminiz var.",
    "ar" => "✅ حالة الحساب: التسجيل والإيداع مكتمل\n\n● التسجيل: مكتمل\n● الإيداع: مكتمل\n● الدولة: {country}\n\nلديك وصول كامل إلى جميع الميزات.",
    "fr" => "✅ État du compte : Inscription et dépôt terminés\n\n● Inscription : Terminée\n● Dépôt : Terminé\n● Pays : {country}\n\nVous avez un accès complet à toutes les fonctionnalités."
];

$instructions_translations = [
    "en" => "🔹To fully enjoy the bot, follow these 3 steps: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Click the <b>REGISTER</b> button to create a new account
◆ If you already have an account, log out and create a new one
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Use the promo code <b>ROVAS</b> during registration
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 A confirmation notification will be sent automatically after registration.✅
⟣━━━━━━━━━━━━━━⟢",
    "hi" => "🔹बॉट का पूरा लाभ उठाने के लिए, इन 3 चरणों का पालन करें: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦नया खाता बनाने के लिए <b>पंजीकरण</b> बटन दबाएं
◆ यदि आपका पहले से खाता है, लॉग आउट करें फिर नया बनाएं
⟣━━━━━━━━━━━━━━⟢
◉ 2➦पंजीकरण के दौरान प्रोमो कोड <b>ROVAS</b> का उपयोग करें
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 पंजीकरण के बाद स्वचालित रूप से पुष्टि अधिसूचना भेजी जाएगी।✅
⟣━━━━━━━━━━━━━━⟢",
    "ru" => "🔹Чтобы в полной мере пользоваться ботом, выполните эти 3 шага: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Нажмите кнопку <b>РЕГИСТРАЦИЯ</b>, чтобы создать новый аккаунт
◆ Если у вас уже есть аккаунт, выйдите из него и создайте новый
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Используйте промокод <b>ROVAS</b> при регистрации
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 После регистрации уведомление о подтверждении будет отправлено автоматически.✅
⟣━━━━━━━━━━━━━━⟢",
    "pt" => "🔹Para aproveitar totalmente o bot, siga estes 3 passos: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Clique no botao <b>REGISTRO</b> para criar uma nova conta
◆ Se voce ja tem uma conta, faca logout e crie uma nova
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Use o codigo promocional <b>ROVAS</b> durante o registro
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Uma notificacao de confirmacao sera enviada automaticamente apos o registro.✅
⟣━━━━━━━━━━━━━━⟢",
    "es" => "🔹Para disfrutar plenamente del bot, siga estos 3 pasos: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Presione el boton <b>REGISTRO</b> para crear una nueva cuenta
◆ Si ya tiene una cuenta, cierre sesion y cree una nueva
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Use el codigo promocional <b>ROVAS</b> durante el registro
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Una notificacion de confirmacion se enviara automaticamente despues del registro.✅
⟣━━━━━━━━━━━━━━⟢",
    "uz" => "🔹Botdan to'liq foydalanish uchun, quyidagi 3 qadamni bajaring: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Yangi hisob yaratish uchun <b>RO'YXATDAN O'TISH</b> tugmasini bosing
◆ Agar hisobingiz bo'lsa, chiqib qayta kirib yangi hisob yarating
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Ro'yxatdan o'tishda promo kod <b>ROVAS</b> dan foydalaning
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Ro'yxatdan o'tgandan so'ng tasdiqlash xabarnomasi avtomatik yuboriladi.✅
⟣━━━━━━━━━━━━━━⟢",
    "az" => "🔹Botdan tam istifade etmek ucun, bu 3 addimi izleyin: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Yeni hesab yaratmaq ucun <b>QEYDIYYAT</b> duymesine basin
◆ Hesabiniz varsa, cixis edib yeni hesab yaradin
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Qeydiyyat zamani promo kod <b>ROVAS</b> istifade edin
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Qeydiyyatdan sonra tesdiq bildirisi avtomatik gonderilecek.✅
⟣━━━━━━━━━━━━━━⟢",
    "tr" => "🔹Bottan tam olarak yararlanmak icin su 3 adimi izleyin: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Yeni hesap olusturmak icin <b>KAYIT</b> butonuna tiklayin
◆ Zaten hesabiniz varsa, cikis yapip yeni bir hesap olusturun
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Kayit sirasinda promo kod <b>ROVAS</b> kullanin
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Kayittan sonra onay bildirimi otomatik olarak gonderilecektir.✅
⟣━━━━━━━━━━━━━━⟢",
    "ar" => "🔹للاستمتاع الكامل بالبوت، اتبع هذه الخطوات الثلاث: ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦اضغط على زر <b>التسجيل</b> لإنشاء حساب جديد
◆ إذا كان لديك حساب بالفعل، قم بتسجيل الخروج ثم أنشئ حسابً جديدًا
⟣━━━━━━━━━━━━━━⟢
◉ 2➦استخدم رمز الترويجة <b>ROVAS</b> عند التسجيل
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 سيتم إرسال إشعار تأكيد تلقائيًا بعد التسجيل.✅
⟣━━━━━━━━━━━━━━⟢",
    "fr" => "🔹Pour profiter pleinement du bot, suivez ces 3 etapes : ↓
⟣━━━━━━━━━━━━━━⟢
◉ 1➦Appuyez sur le bouton <b>INSCRIPTION</b> pour creer un nouveau compte
◆ Si vous avez deja un compte, deconnectez-vous puis creez-en un nouveau
⟣━━━━━━━━━━━━━━⟢
◉ 2➦Utiliser le code promo <b>ROVAS</b> lors de l'inscription
⟣━━━━━━━━━━━━━━⟢
◉ 3➦📭 Une notification de confirmation vous sera envoyee automatiquement apres l'inscription.✅
⟣━━━━━━━━━━━━━━⟢",
];

$main_menu_translations = [
    "ru" => ["main_menu" => "Menu", "registration" => "📱 Регистрация", "instruction" => "📖 Руководство", "choose_lang" => "🌍 Сменить язык", "get_signal" => "🎯 Прогнозы", "back" => "← Назад", "account_status" => "💰 Уже зарегистрирован"],
    "en" => ["main_menu" => "Menu", "registration" => "📱 Register", "instruction" => "📖 Guide", "choose_lang" => "🌐 Change language", "get_signal" => "⚜️GET SIGNAL⚜️", "back" => "← Back", "account_status" => "💰 Already registered"],
    "hi" => ["main_menu" => "Menu", "registration" => "📱 पंजीकरण", "instruction" => "📖 गाइड", "choose_lang" => "🌍 भाषा बदलें", "get_signal" => "🎯 प्रेडिक्शन", "back" => "← वापस", "account_status" => "💰 पहले से पंजीकृत"],
    "pt" => ["main_menu" => "Menu", "registration" => "📱 Registro", "instruction" => "📖 Guia", "choose_lang" => "🌍 Mudar idioma", "get_signal" => "🎯 Previsoes", "back" => "← Voltar", "account_status" => "💰 Ja registrado"],
    "es" => ["main_menu" => "Menu", "registration" => "📱 Registro", "instruction" => "📖 Guia", "choose_lang" => "🌍 Cambiar idioma", "get_signal" => "🎯 Predicciones", "back" => "← Volver", "account_status" => "💰 Ya registrado"],
    "uz" => ["main_menu" => "Menu", "registration" => "📱 Ro'yxatdan o'tish", "instruction" => "📖 Yo'riqnoma", "choose_lang" => "🌍 Tilni o'zgartirish", "get_signal" => "🎯 Taxminlar", "back" => "← Orqaga", "account_status" => "💰 Avval ro'yxatdan"],
    "az" => ["main_menu" => "Menu", "registration" => "📱 Qeydiyyat", "instruction" => "📖 Me'lumat", "choose_lang" => "🌍 Dili deyisdirmek", "get_signal" => "🎯 Proqnozlar", "back" => "← Geri", "account_status" => "💰 Artiq qeydiyyatda"],
    "tr" => ["main_menu" => "Menu", "registration" => "📱 Kayit", "instruction" => "📖 Rehber", "choose_lang" => "🌍 Dili degistir", "get_signal" => "🎯 Tahminler", "back" => "← Geri", "account_status" => "💰 Zaten kayitli"],
    "ar" => ["main_menu" => "Menu", "registration" => "📱 تسجيل", "instruction" => "📖 دليل", "choose_lang" => "🌍 تغيير اللغة", "get_signal" => "🎯 التوقعات", "back" => "← رجوع", "account_status" => "💰 مسجل مسبقاً"],
    "fr" => ["main_menu" => "Menu", "registration" => "📱 Inscription", "instruction" => "📖 Guide", "choose_lang" => "🌐 Modifier la langue", "get_signal" => "⚜️GET SIGNAL⚜️", "back" => "← Retour", "account_status" => "💰 Deja inscrit"],
];

// Handle webhook events

// Handle webhook events
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'webhook') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        exit('Invalid JSON');
    }
    
    $event = $input['event'] ?? null;
    $tgid = $input['tgid'] ?? null;
    
    if ($event && $tgid) {
        if ($event === 'registration') {
            // Check if user is already registered
            $isRegistered = getUserData($tgid, 'isregistered');
            if ($isRegistered === 'yes') {
                http_response_code(200);
                exit('User already registered');
            }
            
            $country = $input['country'] ?? '';
            
            // Validate registration data
            if (empty($country)) {
                http_response_code(400);
                exit('Country is required for registration');
            }
            
            saveUserData($tgid, 'isregistered', 'yes');
            saveUserData($tgid, 'country', $country);
            saveUserData($tgid, 'isdeposit', 'no');
            
            $lang = getUserData($tgid, 'language') ?: 'en';
            $message = $step2_texts[$lang] ?? $step2_texts['en'];
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "💰 Deposit", 'url' => "https://1wyvrz.life/?open=register&p=".P_PARAM."&sub1=$tgid"]],
                    [['text' => "⬅️ Back to Main Menu", 'callback_data' => "main"]]
                ]
            ];
            
            sendPhoto($tgid, "https://i.ibb.co/zWgnCxLB/IMG-20250812-102227-999.jpg", $message, $keyboard);
        } 
        elseif ($event === 'deposit') {
            // Check if user has already made a deposit
            $isDeposit = getUserData($tgid, 'isdeposit');
            if ($isDeposit === 'yes') {
                http_response_code(200);
                exit('User already made a deposit');
            }
            
            // Check if user is registered first
            $isRegistered = getUserData($tgid, 'isregistered');
            if ($isRegistered !== 'yes') {
                http_response_code(400);
                exit('User must register before making a deposit');
            }
            
            $amount = $input['amount'] ?? '0';
            $country = $input['country'] ?? '';
            $transactionid = $input['transactionid'] ?? '';
            
            // Validate deposit data
            $errors = [];
            
            if (empty($amount) || floatval($amount) <= 0) {
                $errors[] = "Deposit amount must be greater than zero";
            }
            
            if (empty($country)) {
                $errors[] = "Country is required";
            }
            
            if (empty($transactionid)) {
                $errors[] = "Transaction ID is required";
            }
            
            // If there are validation errors, return them
            if (!empty($errors)) {
                http_response_code(400);
                exit('Validation failed: ' . implode(', ', $errors));
            }
            
            // All validation passed, process the deposit
            saveUserData($tgid, 'isdeposit', 'yes');
            saveUserData($tgid, 'deposit_amount', $amount);
            saveUserData($tgid, 'deposit_transactionid', $transactionid);
            
            // ... [previous code in deposit event]

$lang = getUserData($tgid, 'language') ?: 'en';
$message = $deposit_success_texts[$lang] ?? $deposit_success_texts['en'];
$message = str_replace(['{amount}', '{country}', '{transactionid}'], [$amount, $country, $transactionid], $message);
$t = $main_menu_translations[$lang] ?? $main_menu_translations['en'];

$keyboard = [
    'inline_keyboard' => [
        [
            [
                'text' => "📡 " . $t['get_signal'],
                'web_app' => [
                    'url' => WEB_APP_URL
                ]
            ]
        ],
        [
            [
                'text' => "⬅ " . $t['back'],
                'callback_data' => "main"
            ]
        ]
    ]
];

// FIX: Replace $chatId with $tgid
sendPhoto(
    $tgid,  // Changed from $chatId to $tgid
    "https://t.me/photoszr/11",
    "✅ BOT ACTIVATED 🟩",
    $keyboard
);
        }
        
        http_response_code(200);
        exit('OK');
    } else {
        http_response_code(400);
        exit('Missing event or tgid');
    }
}

// ... [rest of the code remains the same]

// Handle Telegram updates
$update = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    // Not a valid Telegram update
    exit;
}

if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $text = $message['text'] ?? '';
    
    if (strpos($text, '/start') === 0) {
        if (checkMembership($userId)) {
            $lang = getUserData($userId, 'language');
            if ($lang) {
                runMain($chatId, $userId);
            } else {
                showLanguageSelection($chatId);
            }
        } else {
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => "📢 Join Channel", 'url' => "https://t.me/".CHANNEL]],
                    [['text' => "✅ I've Joined", 'callback_data' => "verify_join"]]
                ]
            ];
            sendMessage($chatId, "Please join our channel to continue.", $keyboard);
        }
    }
    elseif ($text === '/gwt') {
        $webhookUrl = BASE_URL . '?action=webhook';
        sendMessage($chatId, "Webhook URL:\n$webhookUrl", null, 'HTML');
    }
} 
elseif (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $data = $callback['data'];
    $chatId = $callback['message']['chat']['id'];
    $userId = $callback['from']['id'];
    $messageId = $callback['message']['message_id'];
    $callbackId = $callback['id'];
    
    if (strpos($data, '/lang') === 0) {
        $lang = explode(' ', $data)[1] ?? '';
        if ($lang && array_key_exists($lang, $main_menu_translations)) {
            saveUserData($userId, 'language', $lang);
            answerCallbackQuery($callbackId, "✅ Language set to: ".strtoupper($lang));
            deleteMessage($chatId, $messageId);
            runMain($chatId, $userId);
        } else {
            answerCallbackQuery($callbackId, "❌ Invalid language selection", true);
        }
    } 
    else {
        switch ($data) {
            case 'verify_join':
                if (checkMembership($userId)) {
                    answerCallbackQuery($callbackId, "✅ Joined");
                    deleteMessage($chatId, $messageId);
                    $lang = getUserData($userId, 'language');
                    if ($lang) {
                        runMain($chatId, $userId);
                    } else {
                        showLanguageSelection($chatId);
                    }
                } else {
                    answerCallbackQuery($callbackId, "❌ Please join the channel first", true);
                }
                break;
                
            case 'change_lang':
                answerCallbackQuery($callbackId);
                deleteMessage($chatId, $messageId);
                showLanguageSelection($chatId);
                break;
                
            case 'instruction':
                answerCallbackQuery($callbackId);
                deleteMessage($chatId, $messageId);
                $lang = getUserData($userId, 'language') ?: 'en';
                $text = $instructions_translations[$lang] ?? $instructions_translations['en'];
                $keyboard = ['inline_keyboard' => [[['text' => "🔙 Back", 'callback_data' => "main"]]]];
                sendMessage($chatId, $text, $keyboard);
                break;
                
            case 'main':
                answerCallbackQuery($callbackId);
                deleteMessage($chatId, $messageId);
                runMain($chatId, $userId);
                break;
                
            case 'get_signal':
                answerCallbackQuery($callbackId);
                deleteMessage($chatId, $messageId);
                sendGetSignalButton($chatId, $userId);
                break;
                
            case 'registration':
                answerCallbackQuery($callbackId);
                deleteMessage($chatId, $messageId);
                handleRegistration($chatId, $userId);
                break;
                
            case 'account_status':
                answerCallbackQuery($callbackId);
                deleteMessage($chatId, $messageId);
                handleAccountStatus($chatId, $userId);
                break;
                
            default:
                answerCallbackQuery($callbackId, "❌ Unknown command", true);
                break;
        }
    }
}

// Command handlers
function showLanguageSelection($chatId) {
    global $main_menu_translations;
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "🇷🇺 Русский", 'callback_data' => "/lang ru"], ['text' => "🇬🇧 English", 'callback_data' => "/lang en"]],
            [['text' => "🇮🇳 हिंदी", 'callback_data' => "/lang hi"], ['text' => "🇧🇷 Brazilian", 'callback_data' => "/lang pt"]],
            [['text' => "🇪🇸 Español", 'callback_data' => "/lang es"], ['text' => "🇺🇿 O'zbek", 'callback_data' => "/lang uz"]],
            [['text' => "🇦🇿 Azərbaycan", 'callback_data' => "/lang az"], ['text' => "🇹🇷 Türkçe", 'callback_data' => "/lang tr"]],
            [['text' => "🇫🇷 Français", 'callback_data' => "/lang fr"], ['text' => "🇸🇦 العربية", 'callback_data' => "/lang ar"]],
            [['text' => "🔙 Back", 'callback_data' => "main"]]
        ]
    ];
    sendMessage($chatId, "🌐 Please select your language:", $keyboard);
}

function runMain($chatId, $userId) {
    global $main_menu_translations;
    
    $lang = getUserData($userId, 'language') ?: 'en';
    $t = $main_menu_translations[$lang] ?? $main_menu_translations['en'];
    
    $isRegistered = getUserData($userId, 'isregistered');
    $isDeposit = getUserData($userId, 'isdeposit');
    
    // Registration button
    $registrationButton = ['text' => $t['registration'], 'callback_data' => 'registration'];
    if ($isRegistered === 'yes' && $isDeposit === 'yes') {
        $registrationButton = ['text' => $t['account_status'], 'callback_data' => 'account_status'];
    }
    
    // Get signal button logic
    if ($isRegistered === 'yes' && $isDeposit === 'yes') {
        $getSignalButton = ['text' => $t['get_signal'], 'callback_data' => 'get_signal'];
    } else {
        $getSignalButton = ['text' => $t['get_signal'], 'callback_data' => 'registration'];
    }
    
    // Build keyboard
    $keyboard = [
        'inline_keyboard' => [
            [
                $registrationButton,
                ['text' => $t['instruction'], 'callback_data' => 'instruction']
            ],
            [
                ['text' => $t['choose_lang'], 'callback_data' => 'change_lang']
            ],
            [
                $getSignalButton
            ]
        ]
    ];
    
    // Send menu photo
    sendPhoto(
        $chatId,
        "https://i.ibb.co/qLjsWV2W/IMG-20250812-091057-129.jpg",
        $t['main_menu'],
        $keyboard
    );
}

function handleRegistration($chatId, $userId) {
    global $step1_texts, $step2_texts;
    
    $lang = getUserData($userId, 'language') ?: 'en';
    $isRegistered = getUserData($userId, 'isregistered');
    $isDeposit = getUserData($userId, 'isdeposit');
    
    if ($isRegistered !== 'yes') {
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => "📱 🔶 Register",
                        'url'  => "https://1wyvrz.life/?open=register&p=" . P_PARAM . "&sub1=$userId"
                    ]
                ],
                [
                    [
                        'text' => "⬅️ Back to Main Menu",
                        'callback_data' => "main"
                    ]
                ]
            ]
        ];
        
        $text = $step1_texts[$lang] ?? $step1_texts['en'];
        
        sendPhoto(
            $chatId,
            "https://t.me/photoszr/10",
            $text,
            $keyboard,
            'HTML'
        );
    } 
    elseif ($isDeposit !== 'yes') {
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => "💰 Deposit",
                        'url'  => "https://1wtsaw.life/casino/list?open=deposit&p=" . P_PARAM . "&sub1=$userId"
                    ]
                ],
                [
                    [
                        'text' => "⬅️ Back to Main Menu",
                        'callback_data' => "main"
                    ]
                ]
            ]
        ];
        
        $text = $step2_texts[$lang] ?? $step2_texts['en'];
        
        sendPhoto(
            $chatId,
            "https://i.ibb.co/zWgnCxLB/IMG-20250812-102227-999.jpg",
            $text,
            $keyboard,
            'HTML'
        );
    } 
    else {
        handleAccountStatus($chatId, $userId);
    }
}

function handleAccountStatus($chatId, $userId) {
    global $account_status_texts;
    
    $lang = getUserData($userId, 'language') ?: 'en';
    $country = getUserData($userId, 'country') ?: 'Not set';
    
    $text = $account_status_texts[$lang] ?? $account_status_texts['en'];
    $text = str_replace('{country}', $country, $text);
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "⬅️ Back to Main Menu", 'callback_data' => "main"]]
        ]
    ];
    
    sendMessage($chatId, $text, $keyboard);
}

function sendGetSignalButton($chatId, $userId) {
    global $main_menu_translations;
    
    $lang = getUserData($userId, 'language') ?: 'en';
    $t = $main_menu_translations[$lang] ?? $main_menu_translations['en'];
    
    $keyboard = [
        'inline_keyboard' => [
            [
                [
                    'text' => "📡 " . $t['get_signal'],
                    'web_app' => [
                        'url' => WEB_APP_URL
                    ]
                ]
            ],
            [
                [
                    'text' => "⬅ " . $t['back'],
                    'callback_data' => "main"
                ]
            ]
        ]
    ];

    sendPhoto(
        $chatId,
        "https://t.me/photoszr/11",
        "✅ BOT ACTIVATED 🟩",
        $keyboard
    );
}
?>
