<?php
echo "<h2>Active nginx config (default.conf)</h2>";
$path = '/etc/nginx/sites-enabled/default.conf';
if (file_exists($path)) {
    echo "<pre style='background:#f4f4f4;padding:10px;'>";
    echo htmlspecialchars(file_get_contents($path));
    echo "</pre>";
} else {
    echo "❌ Not found at $path";
    // Try the real path if it's a symlink
    $real = @readlink($path);
    if ($real) {
        echo "<br>Symlink points to: $real";
        if (file_exists($real)) {
            echo "<pre>" . htmlspecialchars(file_get_contents($real)) . "</pre>";
        }
    }
}

echo "<h2>Does it contain Laravel's try_files?</h2>";
$content = @file_get_contents($path);
echo (strpos($content, 'try_files') !== false)
    ? "✅ Yes — Laravel routing is configured"
    : "❌ No — this is still the default nginx config";