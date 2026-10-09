<!DOCTYPE html>
<html lang="tr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?=$hotspot['marka']?></title>
	<link href="<?= $hotspot['_assets'] ?>/css/style.css" rel="stylesheet" />
	<link href="<?= $hotspot['_assets'] ?>/css/smoothness/jquery-ui-1.10.1.custom.min.css" rel="stylesheet" />
	<script src="<?= $hotspot['_assets'] ?>/js/jquery-1.9.1.js"></script>
	<script src="<?= $hotspot['_assets'] ?>/js/jquery-ui-1.10.1.custom.min.js"></script>
	<script src="<?= $hotspot['_assets'] ?>/js/jquery.ui.datetimepicker-tr.js"></script>
	<script src="<?= $hotspot['_assets'] ?>/js/jquery-ui-timepicker-addon.js"></script>
</head>
<body>
	<div id="container">

	<header class="page-head">
		<h1><?=$hotspot['marka']?> Yönetim Paneli</h1>
		<a id="back-to-gui" href="/">&#8629; OPNsense</a>
	</header>

	<?php
	if (date('Y', strtotime($hotspot['demo_bitis'])) != '2010')
		echo '<p class="notice">Hotspot demo süresi ' . htmlspecialchars($hotspot['demo_bitis']) . ' tarihinde dolacaktır.</p>';
	?>

	<?php
// aktif menü: ilk yol parçası (user/5 gibi detay sayfaları da listeye bağlanır)
$current_route = 'users';
if (!empty($_GET['u'])) {
    $current_route = strtok($_GET['u'], '/');
    if ($current_route === 'user') {
        $current_route = 'users';
    }
}
?>
	<nav id="nav">
		<a href="<?=url_for('users')?>"<?= $current_route == 'users' ? ' aria-current="page"' : '' ?>>Kullanıcı Listesi</a>
		<a href="<?=url_for('groups')?>"<?= $current_route == 'groups' ? ' aria-current="page"' : '' ?>>Grup Listesi</a>
	<? if (isset($settings['authentication']['sms'])): ?>
		<a href="<?=url_for('sms')?>"<?= $current_route == 'sms' ? ' aria-current="page"' : '' ?>>SMS Raporu</a>
	<? endif; ?>
		<a href="<?=url_for('permissions')?>"<?= $current_route == 'permissions' ? ' aria-current="page"' : '' ?>>İzin Raporu</a>
		<a href="<?=url_for('settings')?>"<?= $current_route == 'settings' ? ' aria-current="page"' : '' ?>>Ayarlar</a>
		<a href="<?=url_for('lang/tr')?>"<?= $current_route == 'lang' ? ' aria-current="page"' : '' ?>>Dil</a>
		<a href="<?=url_for('logout')?>">Çıkış</a>
	</nav>

	<? if (isset($message)): ?>
		<div class="message" role="status"><span class="<?=$status?>"><?=$message?></div>
	<? endif; ?>

	<div class="content">
		<?= $content; ?>
	</div>

	<footer>
		<p>© 2014 <?=$hotspot['firma']?></p>
	</footer>

	</div>
</body>
</html>
