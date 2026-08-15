<?php
require_once __DIR__ . '/functions.php';

if (este_admin()) { header('Location: admin.php'); exit; }

$eroare = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? '')) {
        $eroare = 'Sesiune expirată. Încearcă din nou.';
    } else {
        $u = trim((string)($_POST['utilizator'] ?? ''));
        $p = (string)($_POST['parola'] ?? '');
        if (hash_equals(ADMIN_USER, $u) && hash_equals(ADMIN_PASS, $p)) {
            session_regenerate_id(true);
            $_SESSION['admin']      = true;
            $_SESSION['admin_user'] = ADMIN_USER;
            header('Location: admin.php');
            exit;
        }
        $eroare = 'Utilizator sau parolă greșite.';
    }
}
?><!doctype html>
<html lang="ro">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administrare · <?= h(NUME_MIRE) ?> &amp; <?= h(NUME_MIREASA) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card card fade-up" style="padding:36px 32px">
    <div class="mono"><?= mb_substr(NUME_MIRE,0,1) ?> <span class="amp">&amp;</span> <?= mb_substr(NUME_MIREASA,0,1) ?></div>
    <h1>Administrare album</h1>
    <p class="sub">Acces rezervat mirilor</p>

    <?php if ($eroare): ?><div class="alerta"><?= h($eroare) ?></div><?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <div class="camp">
        <label for="utilizator">Utilizator</label>
        <input type="text" id="utilizator" name="utilizator" required autofocus value="<?= h($_POST['utilizator'] ?? '') ?>">
      </div>
      <div class="camp">
        <label for="parola">Parolă</label>
        <input type="password" id="parola" name="parola" required>
      </div>
      <button class="btn btn-primar btn-full" type="submit">Autentificare</button>
    </form>
    <p style="margin-top:18px"><a href="index.php" style="font-size:.84rem;color:var(--muted)">← Înapoi la album</a></p>
  </div>
</div>
</body>
</html>
