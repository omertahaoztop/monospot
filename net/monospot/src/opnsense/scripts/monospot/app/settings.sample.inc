<?php

return array(
    'authentication' =>
        array(
            'sms' => '1',
        ),
    /* Giriş sonrası dönülecek varsayılan adres (redirurl yoksa). Boş bırakılırsa
       portalda kalınır ve yalnız başarı mesajı gösterilir. */
    'after_login_url' => 'http://www.msftconnecttest.com/redirect',
    'session_timeout' => '180',
    'valid_for' => '1',
    'valid_for_unit' => 'days',
    'disallow_multiple_logins' => '1',
    'disallow_multiple_logins_for' => '30',
    'daily_global_limit' => '200',
    'daily_limit' => '4',
    'weekly_limit' => '100',
    'monthly_limit' => '250',
    'yearly_limit' => '500',
    'min_interval' => '2',
    'name' => '',
    'color' => '#111417',
    'logo' => '',
    'items_per_page' => '100',
    'custom_fields' => '',
);
