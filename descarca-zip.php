<?php
require_once __DIR__ . '/functions.php';
cere_admin();

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    die('Extensia ZIP nu este disponibilă pe server. Descarcă pozele prin File Manager / FTP din folderul "uploads".');
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$randuri = db()->query('SELECT nume_fisier, nume_original, nume_invitat, data_incarcare FROM poze ORDER BY data_incarcare ASC')->fetchAll();
if (empty($randuri)) { die('Nu există fotografii de descărcat.'); }

$numeZip = 'Album_' . preg_replace('/[^A-Za-z0-9]/', '', NUME_MIRE) . '_' . preg_replace('/[^A-Za-z0-9]/', '', NUME_MIREASA) . '_' . date('Ymd_His') . '.zip';
$caleTmp = sys_get_temp_dir() . '/' . uniqid('album_', true) . '.zip';

$zip = new ZipArchive();
if ($zip->open($caleTmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    die('Nu s-a putut crea arhiva. Verifică spațiul liber pe server.');
}

$folosite = [];
foreach ($randuri as $r) {
    $cale = UPLOAD_DIR . $r['nume_fisier'];
    if (!is_file($cale)) continue;

    // nume prietenos în arhivă: data + (invitat) + nume original
    $ext = pathinfo($r['nume_fisier'], PATHINFO_EXTENSION);
    $eticheta = $r['nume_invitat'] ? preg_replace('/[^\p{L}\p{N} _-]/u', '', $r['nume_invitat']) : 'invitat';
    $data = date('Ymd_His', strtotime($r['data_incarcare']));
    $baza = $data . '_' . $eticheta;
    $numeInZip = $baza . '.' . $ext;
    $i = 1;
    while (isset($folosite[$numeInZip])) { $numeInZip = $baza . '_' . (++$i) . '.' . $ext; }
    $folosite[$numeInZip] = true;

    $zip->addFile($cale, $numeInZip);
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $numeZip . '"');
header('Content-Length: ' . filesize($caleTmp));
header('Cache-Control: no-store');

$fp = fopen($caleTmp, 'rb');
if ($fp) { while (!feof($fp)) { echo fread($fp, 1024 * 256); flush(); } fclose($fp); }
@unlink($caleTmp);
exit;
