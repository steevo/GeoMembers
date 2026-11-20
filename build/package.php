<?php
require __DIR__ . '/langs.php';
// Construit les archives installables : php build/package.php
$root = dirname(__DIR__);
$dist = $root . '/dist';
@mkdir($dist);

function zipDir(string $src, string $zipFile): void
{
    $zip = new ZipArchive();
    $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($src) + 1));
        $zip->addFile($file->getPathname(), $rel);
    }
    // Texte de la licence à la racine de chaque archive
    $zip->addFile(dirname(__DIR__) . '/LICENSE.txt', 'LICENSE.txt');
    $zip->close();
    echo basename($zipFile) . ' : ' . round(filesize($zipFile) / 1024) . " Ko\n";
}

$tmp = sys_get_temp_dir() . '/geomembers-pkg-' . uniqid();
mkdir($tmp . '/packages', 0777, true);

foreach (['com_geomembers', 'mod_geomembers', 'plg_content_geomembers'] as $ext) {
    if (is_dir($root . '/' . $ext) && glob($root . '/' . $ext . '/*.xml')) {
        zipDir($root . '/' . $ext, $dist . '/' . $ext . '.zip');
        copy($dist . '/' . $ext . '.zip', $tmp . '/packages/' . $ext . '.zip');
    }
}

if (is_file($root . '/pkg_geomembers.xml')) {
    copy($root . '/pkg_geomembers.xml', $tmp . '/pkg_geomembers.xml');
    copy($root . '/pkg_script.php', $tmp . '/pkg_script.php');
    foreach (glob($root . '/language/*/pkg_geomembers.sys.ini') as $ini) {
        $tag = basename(dirname($ini));
        @mkdir($tmp . '/language/' . $tag, 0777, true);
        copy($ini, $tmp . '/language/' . $tag . '/pkg_geomembers.sys.ini');
    }
    zipDir($tmp, $dist . '/pkg_geomembers.zip');
}
