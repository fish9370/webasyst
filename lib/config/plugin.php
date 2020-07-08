<?php
return array(
    'name'                => 'IPtelefon.su',
    'img'                 => 'img/logo.png',
    'version'             => '1.2',
    'vendor'              => '1236928',
    'custom_settings_url' => '?plugin=iptelefonsu&module=settings',
    'frontend'            => true,
    'handlers'            => [
        'backend_assets'      => 'backendAssetsHandler',
        'backend_profile_log' => 'backendAssetsHandler',
    ],
);
