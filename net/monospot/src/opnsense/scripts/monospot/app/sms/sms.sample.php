<?php
/**
 * SMS sağlayıcı şablonu. Operatör bu dosyayı kopyalayıp kendi API'sine göre
 * doldurmalı ve uygulama dizinine `sms.php` olarak koymalıdır (bkz. README).
 * Bu dosya paketle gelir ama çalıştırılmaz.
 */

function send_sms($user, $password)
{
	// Kullanıcı bilgileri
	$api_username = '';
	$api_password = '';

	global $settings;
	$message = $settings['name'] . ' WiFi hizmeti icin sifreniz ' . $password . ' olarak tanimlanmistir.';
	$xml = '';
	$url = 'https://sms-saglayici.example/api';
	$result = HTTPPoster($url, $xml);

	// Sağlayıcı yanıtını burada değerlendir (örnek: gövdede "OK" geçmesi)
	return $result !== false && strpos($result, 'OK') !== false;
}

function HTTPPoster($url, $data)
{
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;
}