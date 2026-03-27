<?php
header('Content-Type: application/xml; charset=utf-8');
require 'config/db.php';

// Base URL
$base_url = 'http://localhost' . $base_url;

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Página principal -->
    <url>
        <loc><?php echo $base_url; ?>/index.php</loc>
        <lastmod><?php echo date('Y-m-d'); ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    <!-- Categorías -->
    <?php
    $stmt = $pdo->query("SELECT id FROM categorias ORDER BY id");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categorias as $cat) {
        echo "<url>\n";
        echo "    <loc>{$base_url}/categoria.php?id={$cat['id']}</loc>\n";
        echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "</url>\n";
    }
    ?>

    <!-- Empresas -->
    <?php
    $stmt = $pdo->query("SELECT id FROM empresas ORDER BY id");
    $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($empresas as $emp) {
        echo "<url>\n";
        echo "    <loc>{$base_url}/empresa.php?id={$emp['id']}</loc>\n";
        echo "    <lastmod>" . date('Y-m-d') . "</lastmod>\n";
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.6</priority>\n";
        echo "</url>\n";
    }
    ?>
</urlset>