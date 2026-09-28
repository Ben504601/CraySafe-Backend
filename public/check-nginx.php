<?php
$path = '/var/www/html/conf/nginx/site.conf.d/laravel.conf';
echo file_exists($path) ? "✅ Config exists" : "❌ Config NOT found";
echo "<br>";
if (file_exists($path)) {
    echo "<pre>" . file_get_contents($path) . "</pre>";
}