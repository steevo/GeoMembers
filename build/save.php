<?php
// Outil de développement local uniquement : enregistre un TopoJSON généré par build.html.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Interdit');
}

$file = basename((string) ($_GET['file'] ?? ''));

if (!preg_match('/^[a-z0-9-]+\.json$/', $file)) {
    http_response_code(400);
    exit('Nom invalide');
}

$data = file_get_contents('php://input');

if (json_decode($data) === null) {
    http_response_code(400);
    exit('JSON invalide');
}

file_put_contents(__DIR__ . '/../com_geomembers/media/geo/' . $file, $data);
echo 'enregistré';
