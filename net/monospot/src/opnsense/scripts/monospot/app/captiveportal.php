<?php

/**
 * Monospot hotspot portalı — OPNsense sürümü.
 *
 * pfSense bağımlılıkları kaldırıldı: istemci bilgisi REMOTE_ADDR + core ARP'ten
 * okunur, yetkilendirme `configctl captiveportal allow` ile, oturum süresi
 * `configctl captiveportal set.session_restrictions` ile yönetilir. Zone şablon
 * şimi istemciyi http://<host>:<portal_port>/?redirurl=... adresine yönlendirir.
 */

date_default_timezone_set('Europe/Istanbul');

require __DIR__ . '/lib/monospot_db.php';
require __DIR__ . '/lib/limonade.php';
require __DIR__ . '/lib/idiorm.php';
require __DIR__ . '/lib/paris.php';
require __DIR__ . '/models/user.php';
require __DIR__ . '/models/group.php';

// Operatör dosyaları paketle gelmez; yoklukları portalı çökertmemeli.
if (file_exists(__DIR__ . '/sms.php')) {
    include __DIR__ . '/sms.php';
}
if (file_exists(__DIR__ . '/custom_functions.php')) {
    include __DIR__ . '/custom_functions.php';
}

$MONOSPOT_CONF = is_array($tmp = @include '/var/etc/monospot.conf') ? $tmp : array();
define('MONOSPOT_ZONEID', (string)(isset($MONOSPOT_CONF['zoneid']) ? $MONOSPOT_CONF['zoneid'] : 0));

// Portal istemciye doğrudan bağlandığı için REMOTE_ADDR geçerlidir (zone şimi 302 yapar).
$clientip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$clientmac = monospot_client_mac($clientip);

$hotspot = parse_ini_file(__DIR__ . '/hotspot.ini');
// portal docroot'u uygulama dizinidir: varlıklar kökten 'assets/...' olarak sunulur
$hotspot['_assets'] = 'assets';
$global_settings = get_global_settings();
$settings = get_settings();

// Core'un oturum dizini (wwwonly:750) www tarafından yazılamaz; kendi dizinimizi kullan.
ini_set('session.save_path', '/var/lib/php/monospot-sessions');
ini_set('session.cookie_httponly', '1');
session_start();

function get_settings()
{
    global $clientmac;

    if ($clientmac) {
        monospot_db_init(__DIR__ . '/db/hotspot.db');
        global $hotspot;
        set('hotspot', $hotspot);
        $group = Model::factory('Group')->where_like('macs', "%$clientmac%")->find_one();
        if ($group) {
            return $group->getSettings();
        }
    }

    return get_global_settings();
}

function get_global_settings()
{
    return include __DIR__ . '/settings.inc';
}

function configure()
{
    $dir = dirname(__FILE__);

    monospot_db_init($dir . '/db/hotspot.db');

    option('views_dir', $dir . '/views');

    error_layout('layouts/captiveportal.html.php');

    global $hotspot;
    set('hotspot', $hotspot);
}

dispatch('', 'welcome');
function welcome()
{
    global $hotspot;

    if (date('Y', strtotime($hotspot['demo_bitis'])) != '2010' && time() > strtotime($hotspot['demo_bitis'])) {
        $demo_expired = '<meta charset="utf-8"><div style="color:#e00;font-weight:bold;text-align:center">Hotspot demo süresi ' . $hotspot['demo_bitis'] . ' tarihinde dolmuştur.</div>';
        return $demo_expired;
    }

    if ($hotspot['maksimum_kullanici']) {
        $concurrent = monospot_zone_client_count();
        if ($concurrent >= $hotspot['maksimum_kullanici']) {
            $max_user_reached = '<meta charset="utf-8"><div style="color:#e00;font-weight:bold;text-align:center">Maksimum kullanıcı sayısına ulaşıldı!</div>';
            return $max_user_reached;
        }
    }

    global $settings;

    $user = Model::factory('User')->create();
    $user->fillDefaults();
    set('settings', $settings);
    set('title', $settings['name'] . ' ' . t('welcome'));
    set('color', $settings['color']);
    set('user', $user);
    set('lang', isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr');
    if (isset($settings['sms']['simple_screen'])) {
        set('form', 'sms_register');
    }
    /* POST-redirect-GET sonrası tek seferlik mesaj (örn. başarılı giriş) */
    if (!empty($_SESSION['monospot_flash'])) {
        set('message', $_SESSION['monospot_flash']);
        set('message_arg', '');
        unset($_SESSION['monospot_flash']);
    }
    return html('layouts/captiveportal.html.php');
}

dispatch_post('', 'welcome_post');
function welcome_post()
{
    global $settings, $clientmac, $clientip;

    if (isset($_POST['lang'])) {
        $_SESSION['lang'] = strtolower($_POST['lang']);
    }

    $form = '';
    $message_arg = '';

    // SMS ile giriş - yeni kayıt
    if ($_POST['form_id'] == 'sms_register') {
        if ((!isset($settings['sms']['international']) && strlen($_POST['user']['gsm']) == 10) ||
            isset($settings['sms']['international'])) {
            $user = Model::factory('User')->where('gsm', $_POST['user']['gsm'])->find_one();

            if (!$user) { // Daha önceden kaydı yoksa yeni kayıt açıp gerekirse TC kimlik kontrolü yapıyoruz
                $user = Model::factory('User')->create();
                $user->fillDefaults();
                $user->fill($_POST['user']);

                if (isset($settings['sms']['id_number'])) {
                    $user->name = tr_toUpper($user->name);
                    $user->surname = tr_toUpper($user->surname);

                    $tckn_data = array(
                        'isim'      => $user->name,
                        'soyisim'   => $user->surname,
                        'dogumyili' => $_POST['birthyear'],
                        'tcno'      => $user->id_number
                    );

                    $sonuc = tckn_dogrula($tckn_data);

                    if ($sonuc === false) {
                        monospot_log_auth($user->id_number, $clientmac, $clientip, "FAILURE");
                        $message = 'invalid_id_number';
                        $form = 'sms_register';
                    }
                }
            } else {
                // daha önce kayıt olmuşsa bile gsm/e-posta izin bilgileri güncellensin
                $message = permission_process($user, 'sms', 'sms_register');
                if ($message) {
                    $form = 'sms_register';
                } else {
                    if (isset($settings['sms']['always_send_password'])) {
                        // Geçerli şifresi olsa bile yeni şifre gönder etkinse
                        $_POST['form_id'] = 'sms_login';
                        $_POST['password'] = '';
                        return welcome_post();
                    } else {
                        $message = 'user_already_registered';
                        $form = 'sms_login';
                    }
                }
            }

            if (isset($settings['disallow_multiple_logins'])) {
                // Aynı MAC adresi ile farklı kullanıcının giriş yapmasını engelle etkinse
                if (isset($settings['disallow_multiple_logins_for'])) {
                    $mac_disallow_for = $settings['disallow_multiple_logins_for'];
                } else {
                    $mac_disallow_for = 30;
                }

                if (!empty($clientmac)) {
                    $mac_sms = ORM::for_table('sms')->where('mac', $clientmac)->order_by_desc('timestamp')->find_one(); // Bu MAC adresi icin son gonderilen SMS
                    if ($mac_sms) {
                        $mac_user = Model::factory('User')->find_one($mac_sms->user_id);
                        $mac_gsm = $mac_user->gsm;

                        if ($_POST['user']['gsm'] != $mac_gsm) { // Bu MAC adresi icin son gonderilen SMS kullanicinin verdigi numaradan farkli bir numaraya gonderilmisse
                            $current_time = time();
                            $mac_time = $mac_sms->timestamp;
                            $mac_interval = $current_time - $mac_time;

                            if ($mac_interval <= ($mac_disallow_for * 86400)) { // Son gonderilen SMS'ten beri yeterli sure gecmemisse
                                $mac_user = Model::factory('User')->find_one($mac_sms->user_id);
                                $message = 'multiple_logins_disallowed';
                                $form = 'sms_login';
                            }
                        }
                    }
                }
            }

            if (!$form) { // TC kimlik doğrulama varsa ondan geçmiş demektir
                $message = permission_process($user, 'sms', 'sms_register');
                if ($message) {
                    $form = 'sms_register';
                } else {
                    $message = send_password($user);
                    if ($message == 'min_interval') {
                        $message_arg = $settings['min_interval'];
                    }
                    $form = 'sms_login';
                }
            }
        }
    }

    // SMS ile giriş
    if ($_POST['form_id'] == 'sms_login') {
        $user = Model::factory('User')->where('gsm', $_POST['user']['gsm'])->find_one();

        if ($user && isset($settings['sms']['id_number']) && !$user->id_number) {
            // TCKN kontrolü devreye alınmadan önce kayıt olmuş bir kullanıcı ise silelim, TCKN ile tekrar kayıt olsun
            $user->delete();
        }

        if (!$user) {
            $message = 'user_not_found';
            $form = 'sms_register';
        } else {
            if (strlen($_POST['password']) > 0) { // giriş denemesi
                if ($user->password == $_POST['password']) {
                    if (password_expired($user)) {
                        $message = 'password_expired_sms';
                    } else {
                        $message = login($user, 'gsm');
                        if ($message) {
                            $form = 'sms_login';
                        }
                    }
                } else {
                    monospot_log_auth($user->gsm, $clientmac, $clientip, "FAILURE");
                    $message = 'invalid_password';
                }
            } else { // şifre isteği
                $message = send_password($user);
                if ($message == 'min_interval') {
                    $message_arg = $settings['min_interval'];
                }
            }
            $form = 'sms_login';
        }
    }

    // TC kimlik no ile giriş
    if ($_POST['form_id'] == 'id_number_login') {
        $user = Model::factory('User')->where('id_number', $_POST['user']['id_number'])->find_one();

        if (!$user) {
            $user = Model::factory('User')->create();
            $user->fillDefaults();
        }

        // Daha önce kayıtlı olsa bile Nüfus Müdürlüğü'ne kullanıcının girdiği bilgiler gonderilsin
        $user->fill($_POST['user']);
        $user->name = tr_toUpper($user->name);
        $user->surname = tr_toUpper($user->surname);

        $tckn_data = array(
            "isim"      => $user->name,
            "soyisim"   => $user->surname,
            "dogumyili" => $_POST['birthyear'],
            "tcno"      => $user->id_number
        );

        $sonuc = tckn_dogrula($tckn_data);

        if ($sonuc === true) {
            $possible_user = Model::factory('User')->where('id_number', $user->id_number)->find_one();

            if ($possible_user && $possible_user->surname !== $user->surname) {
                $possible_user->delete();
                $user->save();
            }

            $message = permission_process($user, 'id_number');
            if (!$message) {
                $message = login($user, 'id_number');
            }
        } else {
            monospot_log_auth($user->id_number, $clientmac, $clientip, "FAILURE");
            $message = 'invalid_id_number';
        }
        $form = 'id_number_login';
    }

    // Kullanıcı adı ve şifre ile giriş
    if ($_POST['form_id'] == 'manual_user_login') {
        $user = Model::factory('User')->where('username', $_POST['user']['username'])->find_one();

        if ($user) {
            $user->fill($_POST['user']);
            if ($user->password == $_POST['password']) {
                if (password_expired($user)) {
                    $message = 'password_expired';
                } else {
                    $message = permission_process($user, 'manual_user');
                    if (!$message) {
                        $message = login($user, 'username');
                        if ($message) {
                            $form = 'manual_user_login';
                        }
                    }
                }
            } else {
                monospot_log_auth($user->username, $clientmac, $clientip, "FAILURE");
                $user->password = '';
                $message = 'invalid_password';
            }
        } else {
            $message = 'user_not_found';
        }
        $form = 'manual_user_login';
    }

    if (file_exists(__DIR__ . '/captiveportal_custom.php')) {
        include __DIR__ . '/captiveportal_custom.php';
    }

    set('settings', $settings);
    set('title', $settings['name'] . ' ' . t('welcome'));
    set('color', $settings['color']);
    set('message', $message);
    set('message_arg', $message_arg);
    set('form', $form);
    if (!$form && isset($settings['sms']['simple_screen'])) {
        set('form', 'sms_register');
    }
    set('user', $user);
    set('lang', isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr');
    return html('layouts/captiveportal.html.php');
}

run();


/* Functions */

function permission_process(&$user, $method, $form_id = null)
{
    global $settings, $clientmac, $clientip;
    $message = '';

    // izin vermek ya da sözleşmeyi kabul etmek zorunlu ise kontrol et (javascript'te de kontrol var ama teorik olarak aşılabilir)
    if (isset($settings['contact'][$method]['gsm_permission_required'])) {
        if (!isset($_POST['gsm_permission'])) {
            $message = 'gsm_permission_required';
        }
    }
    if (isset($settings['contact'][$method]['email_permission_required'])) {
        if (!isset($_POST['email_permission'])) {
            $message =  'email_permission_required';
        }
    }
    // sms kayıt formu dışında sözleşme kontrolü yap
    if (isset($settings['terms']) && $form_id != 'sms_register') {
        if (!isset($_POST['terms'])) {
            $message = 'terms_required';
        }
    }

    // izinleri kaydet
    if (!$message) {
        // izin istenmişse 1 ya da 0 değerlerini ata, istenmemişse veritabanına NULL yazılacak
        if (isset($_POST['gsm_permission_asked'])) {
            if (isset($_POST['gsm_permission'])) {
                $user->gsm_permission = 1;
            } else {
                $user->gsm_permission = 0;
            }
        }
        if (isset($_POST['email_permission_asked'])) {
            $user->email = $_POST['user']['email'];
            if (isset($_POST['email_permission'])) {
                $user->email_permission = 1;
            } else {
                $user->email_permission = 0;
            }
        }

        if (isset($_POST['user']['gsm'])) {
            $user->gsm = $_POST['user']['gsm'];
        }
        if (isset($_POST['user']['email'])) {
            $user->email = $_POST['user']['email'];
        }
        $user->save();

        if (isset($_POST['gsm_permission']) || isset($_POST['email_permission'])) {
            $permission = ORM::for_table('permission')->create();
            $permission->user_id = $user->id;
            if (isset($_POST['gsm_permission'])) {
                $permission->gsm = $user->gsm;
            }
            if (isset($_POST['email_permission'])) {
                $permission->email = $user->email;
            }
            $permission->mac = $clientmac;
            $permission->ip = $clientip;
            $permission->timestamp = time();
            $permission->save();
        }
    }

    return $message;
}
function send_password($user)
{
    global $settings, $clientmac;

    $message = password_request_check($user);
    if (!$message) {
        $password = generate_password();
        $user->password = $password;

        if (send_sms($user, $password, $clientmac)) {
            $sms = ORM::for_table('sms')->create();
            $sms->user_id = $user->id;
            $sms->mac = $clientmac;
            $sms->timestamp = time();
            $sms->save();
            $user->last_sms = time();
            $user->expires = strtotime('+' . $settings['valid_for'] . ' ' . (isset($settings['valid_for_unit']) ? $settings['valid_for_unit'] : 'days'));
            $user->save();
            $message = 'password_sent';
        } else {
            $message = 'password_not_sent';
        }
    }
    return $message;
}

function login($user, $field)
{
    global $settings, $global_settings, $clientmac, $clientip;

    $user->last_login = time();
    $user->last_mac = $clientmac;
    $user->save();
    monospot_log_auth($user->$field, $clientmac, $clientip, "LOGIN");

    $result = monospot_allow_client($user->$field, $clientip);
    if (empty($result['sessionId'])) {
        // oturum açılamadı (configd hatası / zone yok): çağıran tarafa mesaj döndür
        monospot_log_auth($user->$field, $clientmac, $clientip, "FAILURE");
        return 'login_failed';
    }

    // pfSense'teki portal_allow'a karşılık gelen oturum sonlandırma zamanı:
    // grup/şifre süresi global hard timeout'tan kısaysa core oturuma kısıt eklenir.
    $terminate = null;
    if ($settings['session_timeout'] < $global_settings['session_timeout']) {
        $terminate = strtotime('+' . $settings['session_timeout'] . ' minutes');
    }
    if ($user->expires && $user->expires < strtotime('+' . $settings['session_timeout'] . ' minutes')) {
        $terminate = $user->expires;
    }
    if ($terminate) {
        monospot_configdp('captiveportal set.session_restrictions', array(
            MONOSPOT_ZONEID,
            $result['sessionId'],
            max(1, $terminate - time()),
            isset($settings['session_timeout']) ? $settings['session_timeout'] : 60,
        ));
    }

    $target = monospot_redirurl();
    if ($target === '/') {
        /* Dönülecek adres yok (istemci portala doğrudan geldi): portala dönüp
         * başarı mesajını göster; aksi hâlde giriş formu aynen yeniden çizilir
         * ve giriş başarısız sanılır. */
        $_SESSION['monospot_flash'] = 'logged_in';
    }
    header('Location: ' . $target, true, 302);
    exit();
}

function t($key, $message_arg = '')
{
    $lang = isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
    $messages = include dirname(__FILE__) . '/lang/' . $lang . '.inc';
    $translated_message = sprintf($messages[$key], $message_arg);
    return $translated_message ?: $key;
}

function generate_password()
{
    return mt_rand(100000, 999999);
}

function password_expired($user)
{
    if ($user->expires < time()) {
        return true;
    } else {
        return false;
    }
}

function password_request_check($user)
{
    global $settings;

    # daily global limit
    $daily_global_count = ORM::for_table('sms')->where_raw("strftime('%Y-%m-%d', date(timestamp, 'unixepoch', 'localtime')) = date('now')")->count();
    if ($daily_global_count >= $settings['daily_global_limit']) {
        return 'daily_global_limit';
    }

    # minimum interval
    $seconds = time() - $user->last_sms;
    $minutes = floor($seconds / 60);
    if ($minutes < $settings['min_interval']) {
        return 'min_interval';
    }

    # daily limit
    $daily_count = ORM::for_table('sms')->where('user_id', $user->id)->where_raw("date(timestamp, 'unixepoch', 'localtime') = date('now')")->count();
    if ($daily_count >= $user->daily_limit) {
        return 'daily_limit';
    }

    # weekly limit
    $weekly_count = ORM::for_table('sms')->where('user_id', $user->id)->where_raw("date(timestamp, 'unixepoch', 'localtime') >= date('now', 'weekday 0', '-7 days')")->count();
    if ($weekly_count >= $user->weekly_limit) {
        return 'weekly_limit';
    }

    # monthly limit
    $monthly_count = ORM::for_table('sms')->where('user_id', $user->id)->where_raw("strftime('%Y-%m', date(timestamp, 'unixepoch', 'localtime')) = strftime('%Y-%m', 'now')")->count();
    if ($monthly_count >= $user->monthly_limit) {
        return 'monthly_limit';
    }

    # yearly limit
    $yearly_count = ORM::for_table('sms')->where('user_id', $user->id)->where_raw("strftime('%Y', date(timestamp, 'unixepoch', 'localtime')) = strftime('%Y', 'now')")->count();
    if ($yearly_count >= $user->yearly_limit) {
        return 'yearly_limit';
    }

    return false;
}

function tckn_dogrula($tckn_data)
{
    $xml = '<?xml version="1.0" encoding="utf-8"?>
	<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
	<soap:Body>
	<TCKimlikNoDogrula xmlns="http://tckimlik.nvi.gov.tr/WS">
	<TCKimlikNo>' . $tckn_data["tcno"] . '</TCKimlikNo>
	<Ad>' . $tckn_data["isim"] . '</Ad>
	<Soyad>' . $tckn_data["soyisim"] . '</Soyad>
	<DogumYili>' . $tckn_data["dogumyili"] . '</DogumYili>
	</TCKimlikNoDogrula>
	</soap:Body>
	</soap:Envelope>';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://tckimlik.nvi.gov.tr/Service/KPSPublic.asmx");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'POST /Service/KPSPublic.asmx HTTP/1.1',
        'Host: tckimlik.nvi.gov.tr',
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "http://tckimlik.nvi.gov.tr/WS/TCKimlikNoDogrula"',
        'Content-Length: ' . strlen($xml)
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    return strip_tags($response) == 'true' ? true : false;
}

function tr_toUpper($string)
{
    return strtoupper(str_replace(array('ı', 'i', 'ğ', 'ü', 'ş', 'ö', 'ç' ), array('I', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç' ), $string));
}


function permission_checked($method, $form_id, $type)
{
    global $settings;

    if ($_POST && $form_id == $_POST['form_id']) {
        return isset($_POST[$type . '_permission']);
    } else {
        return isset($settings['contact'][$method][$type . '_permission_checked']);
    }
}

/* ---------------------------------------------------------------------------
 * OPNsense köprüsü (core captive portal ile konuşan yüzey)
 * ------------------------------------------------------------------------- */

/**
 * configd action çalıştırır ve JSON çıktısını dizi olarak döndürür.
 * Hata durumunda ['_error' => true, '_raw' => <çıktı>] döner.
 *
 * DİKKAT: configd, action tanımındaki %s yer tutucularını verilen argümanlarla
 * SIRAYLA doldurur (bkz. actions_captiveportal.conf: `--zoneid=%s ...`), bu
 * yüzden parametreler `--flag=değer` biçiminde DEĞİL, sade değer olarak verilir.
 */
function monospot_configdp($action, array $params = array())
{
    $cmd = '/usr/local/sbin/configctl ' . $action;
    foreach ($params as $param) {
        $cmd .= ' ' . escapeshellarg((string)$param);
    }
    $out = @shell_exec($cmd . ' 2>&1');
    $decoded = json_decode((string)$out, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return array('_error' => true, '_raw' => trim((string)$out));
}

/**
 * pfSense portal_allow() karşılığı: core'a oturumu açar.
 * MAC'i core ARP üzerinden kendisi çözer, pf tablosu/ipfw sayacı core'un işidir.
 */
function monospot_allow_client($username, $ip)
{
    return monospot_configdp('captiveportal allow', array(
        MONOSPOT_ZONEID,
        $username,
        $ip,
        'monospot',
    ));
}

/**
 * Zone'daki yetkili istemci sayısı (pfSense captiveportal_read_db() karşılığı).
 */
function monospot_zone_client_count()
{
    $clients = monospot_configdp('captiveportal list_clients', array(MONOSPOT_ZONEID));
    return is_array($clients) ? count($clients) : 0;
}

/**
 * İstemci MAC adresi — core ile aynı yöntem (ARP tablosu).
 */
function monospot_client_mac($ip)
{
    if ($ip === '' || $ip === null) {
        return '';
    }
    $out = @shell_exec('/usr/sbin/arp -n ' . escapeshellarg($ip) . ' 2>/dev/null');
    if (preg_match('/([0-9a-f]{2}(?::[0-9a-f]{2}){5})/i', (string)$out, $match)) {
        return strtolower($match[1]);
    }
    return '';
}

/**
 * Giriş sonrası dönülecek adres. Zone şimi ?redirurl= ile taşır, formlar gizli
 * alanla POST'a ekler. Yalnız http(s) kabul edilir (açık yönlendirme olmasın).
 */
function monospot_redirurl()
{
    $url = isset($_REQUEST['redirurl']) ? trim((string)$_REQUEST['redirurl']) : '';
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return '/';
    }
    return $url;
}

/**
 * Portal kimlik doğrulama kaydı. pfSense'te captiveportal_logportalauth() vardı;
 * OPNsense'te karşılığı yok, syslog(auth) kullanılır.
 */
function monospot_log_auth($username, $mac, $ip, $status)
{
    openlog('monospot', LOG_PID, LOG_AUTH);
    syslog(LOG_NOTICE, sprintf('portal %s user=%s mac=%s ip=%s zone=%s',
        $status, $username, $mac === '' ? '-' : $mac, $ip, MONOSPOT_ZONEID));
    closelog();
}
