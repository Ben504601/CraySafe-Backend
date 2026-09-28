<?php
echo "<h2>Config files in project</h2>";
echo "<pre>";
if (file_exists('/var/www/html/conf/nginx/site.conf')) {
    echo "✅ site.conf exists\n";
} else {
    echo "❌ site.conf MISSING\n";
}
if (is_dir('/var/www/html/conf/nginx/site.conf.d')) {
    echo "📁 site.conf.d exists: " . implode(', ', scandir('/var/www/html/conf/nginx/site.conf.d')) . "\n";
}
echo "</pre>";

echo "<h2>Files nginx actually reads</h2>";
echo "<pre>";
if (is_dir('/etc/nginx/sites-enabled')) {
    foreach (scandir('/etc/nginx/sites-enabled') as $f) {
        if ($f !== '.' && $f !== '..') {
            echo "📄 $f\n";
        }
    }
} else {
    echo "❌ /etc/nginx/sites-enabled does not exist\n";
}
echo "</pre>";

echo "<h2>Default site config contents</h2>";
echo "<pre>";
if (file_exists('/etc/nginx/sites-enabled/default')) {
    echo htmlspecialchars(file_get_contents('/etc/nginx/sites-enabled/default'));
} else {
    echo "❌ No default file";
}
echo "</pre>";