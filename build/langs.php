<?php
// Génère les fichiers de langue dérivés à partir de com_geomembers.ini :
// .sys.ini du composant, langues du module et du paquet. Usage : php build/langs.php
$root  = dirname(__DIR__);
$tags  = ['fr-FR', 'en-GB', 'de-DE', 'es-ES'];
$sys   = ['COM_GEOMEMBERS', 'COM_GEOMEMBERS_XML_DESCRIPTION', 'COM_GEOMEMBERS_SUBMENU_DATASETS', 'COM_GEOMEMBERS_SUBMENU_CATEGORIES', 'COM_GEOMEMBERS_SUBMENU_GROUPINGS',
    'COM_GEOMEMBERS_SUBMENU_LAYERS', 'COM_GEOMEMBERS_MAP_VIEW_TITLE', 'COM_GEOMEMBERS_MAP_VIEW_DESC'];
$mod   = [
    'fr-FR' => ['GeoMembers – carte', 'Affiche une carte de répartition GeoMembers (le composant GeoMembers doit être installé).'],
    'en-GB' => ['GeoMembers – map', 'Displays a GeoMembers distribution map (the GeoMembers component must be installed).'],
    'de-DE' => ['GeoMembers – Karte', 'Zeigt eine GeoMembers-Verteilungskarte (die Komponente GeoMembers muss installiert sein).'],
    'es-ES' => ['GeoMembers – mapa', 'Muestra un mapa de distribución de GeoMembers (el componente GeoMembers debe estar instalado).'],
];

$plg   = [
    'fr-FR' => ['Contenu - GeoMembers', 'Empêche la suppression d\'une catégorie GeoMembers qui contient encore des jeux de données.'],
    'en-GB' => ['Content - GeoMembers', 'Prevents deleting a GeoMembers category that still contains datasets.'],
    'de-DE' => ['Inhalt - GeoMembers', 'Verhindert das Löschen einer GeoMembers-Kategorie, die noch Datensätze enthält.'],
    'es-ES' => ['Contenido - GeoMembers', 'Impide eliminar una categoría de GeoMembers que todavía contiene conjuntos de datos.'],
];

// Clés utilisées dans le manifeste du module
preg_match_all('/COM_GEOMEMBERS_[A-Z0-9_]+/', file_get_contents($root . '/mod_geomembers/mod_geomembers.xml'), $m);
$modKeys = array_unique($m[0]);

foreach ($tags as $tag) {
    $file = $root . "/com_geomembers/admin/language/$tag/com_geomembers.ini";
    $lines = [];

    foreach (file($file) as $line) {
        if (preg_match('/^([A-Z0-9_]+)=/', $line, $k)) {
            $lines[$k[1]] = rtrim($line, "\r\n");
        }
    }

    $header = "; GeoMembers – Copyright (C) 2026 Philippe Ousset – GNU/GPL v2+\n";

    $out = $header;
    foreach ($sys as $key) {
        $out .= $lines[$key] . "\n";
    }
    file_put_contents($root . "/com_geomembers/admin/language/$tag/com_geomembers.sys.ini", $out);

    @mkdir($root . "/mod_geomembers/language/$tag", 0777, true);
    $modSys = $header . 'MOD_GEOMEMBERS="' . $mod[$tag][0] . "\"\n" . 'MOD_GEOMEMBERS_XML_DESCRIPTION="' . $mod[$tag][1] . "\"\n";
    file_put_contents($root . "/mod_geomembers/language/$tag/mod_geomembers.sys.ini", $modSys);
    $modIni = $modSys;
    foreach ($modKeys as $key) {
        if (isset($lines[$key])) {
            $modIni .= $lines[$key] . "\n";
        } else {
            echo "$tag : clé manquante $key\n";
        }
    }
    file_put_contents($root . "/mod_geomembers/language/$tag/mod_geomembers.ini", $modIni);

    @mkdir($root . "/plg_content_geomembers/language/$tag", 0777, true);
    $plgIni = $header . 'PLG_CONTENT_GEOMEMBERS="' . $plg[$tag][0] . "\"
" . 'PLG_CONTENT_GEOMEMBERS_XML_DESCRIPTION="' . $plg[$tag][1] . "\"
";
    file_put_contents($root . "/plg_content_geomembers/language/$tag/plg_content_geomembers.ini", $plgIni);
    file_put_contents($root . "/plg_content_geomembers/language/$tag/plg_content_geomembers.sys.ini", $plgIni);

    @mkdir($root . "/language/$tag", 0777, true);
    file_put_contents(
        $root . "/language/$tag/pkg_geomembers.sys.ini",
        $header . 'PKG_GEOMEMBERS="GeoMembers"' . "\n" . 'PKG_GEOMEMBERS_XML_DESCRIPTION=' . substr($lines['COM_GEOMEMBERS_XML_DESCRIPTION'], strlen('COM_GEOMEMBERS_XML_DESCRIPTION=')) . "\n"
    );
}

// Vérifie que toutes les langues ont les mêmes clés que fr-FR
$ref = parse_ini_file($root . '/com_geomembers/admin/language/fr-FR/com_geomembers.ini');
foreach ($tags as $tag) {
    $cur = parse_ini_file($root . "/com_geomembers/admin/language/$tag/com_geomembers.ini");
    if ($cur === false) {
        echo "$tag : fichier invalide\n";
        continue;
    }
    foreach (array_diff_key($ref, $cur) as $k => $v) {
        echo "$tag : manque $k\n";
    }
    foreach (array_diff_key($cur, $ref) as $k => $v) {
        echo "$tag : en trop $k\n";
    }
}
echo "Langues générées.\n";
