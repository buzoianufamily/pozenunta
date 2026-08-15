<?php
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=utf-8');
asigura_schema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$id  = (int)($_POST['id'] ?? 0);
$val = (int)($_POST['val'] ?? 1);
$val = $val < 0 ? -1 : 1;   // +1 apreciază, -1 retrage aprecierea

if ($id <= 0) { echo json_encode(['ok' => false]); exit; }

try {
    // CAST la SIGNED ca să evităm underflow pe coloana UNSIGNED
    $stmt = db()->prepare(
        'UPDATE poze SET aprecieri = GREATEST(0, CAST(aprecieri AS SIGNED) + ?) WHERE id = ? AND aprobat = 1'
    );
    $stmt->execute([$val, $id]);

    $nou = (int)db()->query("SELECT aprecieri FROM poze WHERE id = " . $id)->fetchColumn();
    echo json_encode(['ok' => true, 'aprecieri' => $nou]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false]);
}
