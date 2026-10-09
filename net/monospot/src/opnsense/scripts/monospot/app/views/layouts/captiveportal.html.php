<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= htmlspecialchars($color) ?>">
    <title><?= $title ?></title>
    <link href="<?= $hotspot['_assets'] ?>/css/captiveportal-style.css" rel="stylesheet">
    <link href="<?= $hotspot['_assets'] ?>/css/captiveportal-popup.css" rel="stylesheet">
    <script src="<?= $hotspot['_assets'] ?>/js/captiveportal-jquery-1.9.1.js"></script>
    <script src="<?= $hotspot['_assets'] ?>/js/captiveportal-jquery.popup.min.js"></script>
    <? if (isset($settings['authentication']['sms'], $settings['sms']['international'])): ?>
        <link href="<?= $hotspot['_assets'] ?>/css/intlTelInput.css" rel="stylesheet">
        <script>
            var smsInternational = true;
        </script>
        <script src="<?= $hotspot['_assets'] ?>/js/intlTelInput.js"></script>
        <script src="<?= $hotspot['_assets'] ?>/js/intlTelInputUtils.js"></script>
    <? else: ?>
        <script>
            var smsInternational = false;
        </script>
    <? endif; ?>
    <script>
        <? include __DIR__ . '/../../assets/js/captiveportal.js'; ?>
    </script>
    <style>
        :root {
            /* vurgu rengi ayarlardan; marka kırmızısı varsayılan */
            --brand: <?=$color?>;
            --brand-ink: <?=htmlspecialchars(isset($settings['color_contrast']) ? $settings['color_contrast'] : '#fff')?>;
        }

        <? if (isset($settings['authentication']['sms']) && isset($settings['sms']['simple_screen'])): ?>
        /* basitleştirilmiş ekran: yöntem seçimini ve açıklamaları gizle */
        #form-selection,
        .form .description,
        .form .item-description {
            display: none
        }

        <? endif; ?>
    </style>
</head>

<body>

<header id="brand">
    <div id="logo">
        <img src="<?= !empty($settings['logo']) ? htmlspecialchars($settings['logo']) : $hotspot['_assets'] . '/img/monospot-logo.png' ?>" alt="<?= htmlspecialchars($settings['name'] !== '' ? $settings['name'] : $hotspot['marka']) ?>">
    </div>
    <div id="title"><?= $title ?></div>
    <div id="wifi-logo">
        <img src="<?= $hotspot['_assets'] ?>/img/captiveportal-wifi.png" alt="">
    </div>
</header>

<div id="container">
<?
/*
 * Soğuk açılışta (POST yok) sunucu $form'u boş bırakıyor; o durumda ilk yöntem
 * görünür olmalı, aksi hâlde misafir boş bir sayfa görür.
 */
$active_form = !empty($form) ? $form : (isset($settings['authentication']['sms']) ? 'sms_register' : '');
?>
<? if (!empty($active_form)): ?>
    <nav id="steps" aria-hidden="true">
        <span class="step<?= $active_form == 'sms_register' ? ' on' : '' ?>"><?= t('gsm') ?></span>
        <span class="sep" aria-hidden="true"></span>
        <span class="step<?= $active_form == 'sms_login' ? ' on' : '' ?>"><?= t('password') ?></span>
    </nav>
<? endif; ?>

    <div id="js-errors" class="message error" role="alert" aria-live="assertive" tabindex="-1" hidden></div>

    <? if (isset($message)): ?>
        <div class="message <?= $message == 'password_sent' ? 'success' : 'error' ?>" role="alert"><?= t($message, $message_arg) ?></div>
    <? endif; ?>

    <nav id="form-selection" aria-label="<?= t('sms_login') ?>">
        <? $id_number_active = $active_form == 'id_number_login'
            || (!isset($settings['authentication']['sms']) && isset($settings['id_number']['open_extended'])); ?>

        <div class="tabs">
<? if (isset($settings['authentication']['sms'])): ?>
        <button id="sms_register"<?= $active_form == 'sms_register' ? ' class="active"' : '' ?>><?= t('sms_register') ?></button>
        <button id="sms_login"<?= $active_form == 'sms_login' ? ' class="active"' : '' ?>><?= t('sms_login') ?></button>
<? endif; ?>
<? if (isset($settings['authentication']['id_number']) && $lang == 'tr'): ?>
        <button id="id_number_login"<?= $id_number_active ? ' class="active"' : '' ?>><?= t('id_number_login') ?></button>
<? endif; ?>
<? if (isset($settings['authentication']['manual_user'])): ?>
        <button id="manual_user_login"<?= $form == 'manual_user_login' ? ' class="active"' : '' ?>><?= t('manual_user_login') ?></button>
<? endif; ?>
        </div>

    <? if (isset($settings['authentication']['sms'])): ?>
        <div class="form<?= $active_form == 'sms_register' ? ' active' : '' ?>" id="form_sms_register">
            <div class="content">
                <form method="post" onSubmit="return validateForm(this)" action="">
                    <p class="description"><?= t('sms_register_desc') ?></p>
                    <div class="item">
                        <label for="phoneRegister"><?= t('gsm') ?>:</label>
                        <? if (isset($settings['sms']['international'])): ?>
                            <input id="phoneRegister" name="user[gsm]" class="text intlPhone" type="tel" inputmode="tel" autocomplete="tel" value="<?= $user->gsm ?>">
                            <div class="item-description"><?= t('gsm_int_desc') ?></div>
                        <? else: ?>
                            <input id="phoneRegister" name="user[gsm]" class="text" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10" value="<?= $user->gsm ?>" onkeypress="checkphone(this, event)">
                            <div class="item-description"><?= t('gsm_desc') ?></div>
                        <? endif; ?>
                        <input type="hidden" name="gsm_required">
                    </div>
                    <? if (isset($settings['sms']['id_number']) && $lang == 'tr'): ?>
                        <div class="item">
                            <label><?= t('name') ?>:</label>
                            <input name="user[name]" class="text" type="text" maxlength="40" value="<?= $user->name ?>">
                        </div>
                        <div class="item">
                            <label><?= t('surname') ?>:</label>
                            <input name="user[surname]" class="text" type="text" maxlength="40"
                                   value="<?= $user->surname ?>">
                        </div>
                        <div class="item">
                            <label><?= t('birthyear') ?>:</label>
                            <input name="birthyear" class="text" type="text" maxlength="4"
                                   value="<?= $_POST['birthyear'] ?>">
                        </div>
                        <div class="item">
                            <label><?= t('id_number') ?>:</label>
                            <input name="user[id_number]" class="text" type="text" maxlength="11"
                                   value="<?= $user->id_number ?>">
                        </div>
                    <? endif; ?>

                    <?php $method = 'sms';
                    $_form = 'sms_register';
                    include 'captiveportal_permissions.html.php'; ?>

                    <input name="form_id" type="hidden" value="sms_register">
                    <input name="redirurl" type="hidden" value="<?= htmlspecialchars(isset($_REQUEST['redirurl']) ? $_REQUEST['redirurl'] : '') ?>">
                    <input class="submit" name="submit" type="submit" value="<?= t('register') ?> &#187;">
                </form>
            </div>
        </div>

        <div class="form<?= $active_form == 'sms_login' ? ' active' : '' ?>" id="form_sms_login">
            <div class="content">
                <form method="post" onSubmit="return validateForm(this)" action="">
                    <p class="description"><?= t('login_desc_sms') ?></p>
                    <div class="item">
                        <label for="phoneLogin"><?= t('gsm') ?>:</label>
                        <? if (isset($settings['sms']['international'])): ?>
                            <input id="phoneLogin" name="user[gsm]" class="text intlPhone" type="tel" value="<?= $user->gsm ?>" autocomplete="tel">
                            <div class="item-description"><?= t('gsm_int_desc') ?></div>
                        <? else: ?>
                            <input id="phoneLogin" name="user[gsm]" class="text" type="tel" inputmode="numeric" autocomplete="tel-national" maxlength="10" value="<?= $user->gsm ?>" onkeypress="checkphone(this, event)">
                            <div class="item-description"><?= t('gsm_desc') ?></div>
                        <? endif; ?>
                        <input type="hidden" name="gsm_required">
                    </div>
                    <div class="item">
                        <label for="passwordLogin"><?= t('password') ?>:</label>
                        <div class="code-field">
                            <input id="passwordLogin" name="password" class="text code" type="password"
                                   inputmode="numeric" autocomplete="one-time-code"
                                   aria-describedby="passwordLoginHint">
                            <div class="code-ticks" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i></div>
                        </div>
                        <div class="item-description" id="passwordLoginHint"><?= t('password_desc_gsm') ?></div>
                    </div>
                    <?php if (isset($settings['terms'])) require 'captiveportal_terms.html.php'; ?>
                    <input name="form_id" type="hidden" value="sms_login">
                    <input name="redirurl" type="hidden" value="<?= htmlspecialchars(isset($_REQUEST['redirurl']) ? $_REQUEST['redirurl'] : '') ?>">
                    <input class="submit" name="submit" type="submit" value="<?= t('login') ?> &#187;">
                </form>
            </div>
        </div>
    <? endif; ?>

    <? if (isset($settings['authentication']['id_number']) && $lang == 'tr'): ?>
        <div class="form<?= $id_number_active ? ' active' : '' ?>"
             id="form_id_number_login">
            <div class="content">
                <form method="post" onSubmit="return validateForm(this)" action="">
                    <p class="description"><?= t('login_desc_id_number') ?></p>
                    <div class="item">
                        <label><?= t('name') ?>:</label>
                        <input name="user[name]" class="text" type="text" maxlength="40" value="<?= $user->name ?>">
                    </div>
                    <div class="item">
                        <label><?= t('surname') ?>:</label>
                        <input name="user[surname]" class="text" type="text" maxlength="40"
                               value="<?= $user->surname ?>">
                    </div>
                    <div class="item">
                        <label><?= t('birthyear') ?>:</label>
                        <input name="birthyear" class="text" type="text" maxlength="4"
                               value="<?= $_POST['birthyear'] ?>">
                    </div>
                    <div class="item">
                        <label><?= t('id_number') ?>:</label>
                        <input name="user[id_number]" class="text" type="text" maxlength="11"
                               value="<?= $user->id_number ?>">
                    </div>

                    <?php $method = 'id_number';
                    $_form = 'id_number_login';
                    include 'captiveportal_permissions.html.php'; ?>

                    <?php if (isset($settings['terms'])) require 'captiveportal_terms.html.php'; ?>

                    <input name="form_id" type="hidden" value="id_number_login">
                    <input name="redirurl" type="hidden" value="<?= htmlspecialchars(isset($_REQUEST['redirurl']) ? $_REQUEST['redirurl'] : '') ?>">
                    <input class="submit" name="submit" type="submit" value="<?= t('login') ?> &#187;">
                </form>
            </div>
        </div>
    <? endif; ?>

    <? if (isset($settings['authentication']['manual_user'])): ?>
        <div class="form<?= $form == 'manual_user_login' ? ' active' : '' ?>" id="form_manual_user_login">
            <div class="content">
                <form method="post" onSubmit="return validateForm(this)" action="">
                    <div class="description"><?= t('login_desc') ?></div>
                    <div class="item">
                        <label for="manualUser"><?= t('manual_user_name') ?>:</label>
                        <input id="manualUser" name="user[username]" class="text" type="text" maxlength="11"
                               autocomplete="username" value="<?= $user->username ?>">
                    </div>
                    <div class="item">
                        <label for="manualPassword"><?= t('password') ?>:</label>
                        <input id="manualPassword" name="password" class="text" type="password"
                               autocomplete="current-password">
                    </div>

                    <?php $method = 'manual_user';
                    $_form = 'manual_user_login';
                    include 'captiveportal_permissions.html.php'; ?>

                    <?php if (isset($settings['terms'])) require 'captiveportal_terms.html.php'; ?>

                    <input name="form_id" type="hidden" value="manual_user_login">
                    <input name="redirurl" type="hidden" value="<?= htmlspecialchars(isset($_REQUEST['redirurl']) ? $_REQUEST['redirurl'] : '') ?>">
                    <input class="submit" name="submit" type="submit" value="<?= t('login') ?> &#187;">
                </form>
            </div>
        </div>
    <? endif; ?>

    </nav>

    <? include dirname(__FILE__) . '/captiveportal_custom.html.php'; ?>

    <div id="lang">
        <form method="post" action="">
            <input type="submit" name="lang" value="TR" aria-current="<?= $lang == 'tr' ? 'true' : 'false' ?>" aria-label="Türkçe">
            <input type="submit" name="lang" value="EN" aria-current="<?= $lang == 'en' ? 'true' : 'false' ?>" aria-label="English">
            <input name="redirurl" type="hidden" value="<?= htmlspecialchars(isset($_REQUEST['redirurl']) ? $_REQUEST['redirurl'] : '') ?>">
        </form>
    </div>

</div>

<div id="terms-text" style="display:none"><p><?= nl2br(t('terms_text')) ?></p></div>

</body>
</html>
