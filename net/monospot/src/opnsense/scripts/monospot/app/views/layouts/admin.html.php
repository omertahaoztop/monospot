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

	<h1><?=$hotspot['marka']?> Yönetim Paneli</h1>

	<a id="back-to-gui" href="/">&#8629; OPNsense</a>

	<?php
	if (date('Y', strtotime($hotspot['demo_bitis'])) != '2010')
		echo '<div style="color:#e00;font-weight:bold">Hotspot demo süresi ' . $hotspot['demo_bitis'] . ' tarihinde dolacaktır.</div>';
	?>

	<nav id="nav">
		<a href="<?=url_for('users')?>">Kullanıcı Listesi</a> |
        <a href="<?=url_for('groups')?>">Grup Listesi</a> |
	<? if (isset($settings['authentication']['sms'])): ?>
		<a href="<?=url_for('sms')?>">SMS Raporu</a> |
	<? endif; ?>
		<a href="<?=url_for('permissions')?>">İzin Raporu</a> |
		<a href="<?=url_for('settings')?>">Ayarlar</a> |
		<a href="<?=url_for('lang/tr')?>">Dil</a> |
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
