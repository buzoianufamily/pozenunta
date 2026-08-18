<?php
require_once __DIR__ . '/functions.php';
header('Content-Type: application/json; charset=utf-8');
/* Galeria se schimbă tot timpul cât ține petrecerea. Fără asta, browserul
   are voie să refolosească un răspuns vechi, iar invitatul ar derula
   printr-un album de acum o oră fără să înțeleagă de ce. */
header('Cache-Control: no-store');
asigura_schema();

/* Nu mai scriem nimic în sesiune de aici încolo: eliberăm blocajul, ca
   celelalte cereri ale aceluiași vizitator să nu aștepte după noi. */
inchide_sesiune();

$actiune = $_GET['actiune'] ?? 'lista';

if ($actiune === 'lista') {
    $pagina = max(1, (int)($_GET['pagina'] ?? 1));
    $offset = ($pagina - 1) * PER_PAGINA;

    // public: doar pozele aprobate. admin: poate cere tot cu &tot=1
    $doarAprobate = !(este_admin() && ($_GET['tot'] ?? '') === '1');

    $where = $doarAprobate ? 'WHERE aprobat = 1' : '';
    $sort  = ($_GET['sortare'] ?? 'noi') === 'apreciate'
        ? 'aprecieri DESC, data_incarcare DESC, id DESC'
        : 'data_incarcare DESC, id DESC';
    $sql = "SELECT * FROM poze $where ORDER BY $sort
            LIMIT :lim OFFSET :off";
    $stmt = db()->prepare($sql);
    $stmt->bindValue(':lim', PER_PAGINA + 1, PDO::PARAM_INT); // +1 ca să știm dacă mai sunt
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $randuri = $stmt->fetchAll();

    $maiSunt = count($randuri) > PER_PAGINA;
    if ($maiSunt) array_pop($randuri);

    $poze = array_map('poza_pentru_json', $randuri);

    /* Inimile deja apăsate de acest invitat — sursa de adevăr e serverul,
       nu memoria telefonului, deci se văd corect și de pe alt dispozitiv. */
    $mele = aprecieri_mele(array_column($randuri, 'id'));
    foreach ($poze as $i => $p) { $poze[$i]['apreciat'] = isset($mele[$p['id']]); }

    echo json_encode([
        'ok'      => true,
        'poze'    => $poze,
        'pagina'  => $pagina,
        'maiSunt' => $maiSunt,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($actiune === 'numar') {
    $total = (int)db()->query('SELECT COUNT(*) FROM poze WHERE aprobat = 1')->fetchColumn();
    echo json_encode(['ok' => true, 'total' => $total]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'eroare' => 'Acțiune necunoscută.']);
