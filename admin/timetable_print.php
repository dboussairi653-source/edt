<?php
declare(strict_types=1);
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/helpers.php";
require_role(['admin']);

$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

// filtres filière/niveau
$filieres = $pdo->query("SELECT id, nom FROM filieres ORDER BY nom")->fetchAll();
$filiere_id = (int)($_GET['filiere_id'] ?? 0);
if ($filiere_id<=0 && $filieres) $filiere_id = (int)$filieres[0]['id'];

$niveaux = [];
if ($filiere_id>0) {
  $st = $pdo->prepare("SELECT id, nom FROM niveaux WHERE filiere_id=? ORDER BY nom");
  $st->execute([$filiere_id]);
  $niveaux = $st->fetchAll();
}
$niveau_id = (int)($_GET['niveau_id'] ?? 0);
if ($niveau_id<=0 && $niveaux) $niveau_id = (int)$niveaux[0]['id'];

$niveau_name = "";
$filiere_name = "";
if ($niveau_id>0) {
  $st = $pdo->prepare("
    SELECT n.nom AS niveau, f.nom AS filiere
    FROM niveaux n JOIN filieres f ON f.id=n.filiere_id
    WHERE n.id=?
  ");
  $st->execute([$niveau_id]);
  $x = $st->fetch();
  if ($x) { $niveau_name = (string)$x['niveau']; $filiere_name = (string)$x['filiere']; }
}

$rows = [];
if ($niveau_id>0) {
  $st = $pdo->prepare("
    SELECT te.day_of_week, te.start_time, te.end_time, te.kind,
           m.code, m.titre,
           u.nom AS teacher_name,
           r.nom AS room_name,
           COALESCE(g.nom,'(commun)') AS group_name
    FROM timetable_entries te
    JOIN modules m ON m.id=te.module_id
    JOIN users u ON u.id=te.teacher_id
    JOIN rooms r ON r.id=te.room_id
    LEFT JOIN groupes g ON g.id=te.group_id
    WHERE te.week_start=? AND te.niveau_id=?
    ORDER BY te.day_of_week, te.start_time, te.kind, te.group_id
  ");
  $st->execute([$week_start,$niveau_id]);
  $rows = $st->fetchAll();
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Impression EDT</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:Arial, sans-serif; margin:24px; color:#111;}
    .top{display:flex; justify-content:space-between; align-items:flex-start; gap:12px;}
    h2{margin:0 0 4px 0;}
    .muted{color:#666; font-size:12px;}
    table{width:100%; border-collapse:collapse; margin-top:14px;}
    th,td{border:1px solid #222; padding:8px; font-size:12px; vertical-align:top;}
    th{background:#f2f2f2; text-align:left;}
    .badge{display:inline-block; padding:2px 6px; border:1px solid #222; font-size:11px;}
    .printbar{margin-top:14px; display:flex; gap:8px;}
    @media print{
      .printbar, form{display:none !important;}
      body{margin:0;}
    }
  </style>
</head>
<body>

<form method="get" class="printbar">
  <input type="date" name="week" value="<?= e($week_start) ?>">
  <select name="filiere_id" onchange="this.form.submit()">
    <?php foreach($filieres as $f): ?>
      <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id']===$filiere_id)?'selected':'' ?>><?= e($f['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="niveau_id" onchange="this.form.submit()">
    <?php foreach($niveaux as $n): ?>
      <option value="<?= (int)$n['id'] ?>" <?= ((int)$n['id']===$niveau_id)?'selected':'' ?>><?= e($n['nom']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="button" onclick="window.print()">Imprimer / PDF</button>
  <a href="/edt/admin/timetable.php?week=<?= e(urlencode($week_start)) ?>&filiere_id=<?= (int)$filiere_id ?>&niveau_id=<?= (int)$niveau_id ?>">Retour</a>
</form>

<div class="top">
  <div>
    <h2>Emploi du temps</h2>
    <div class="muted">Filière : <b><?= e($filiere_name) ?></b> — Niveau : <b><?= e($niveau_name) ?></b></div>
    <div class="muted">Semaine (lundi) : <b><?= e($week_start) ?></b></div>
  </div>
  <div class="muted">
    Document généré par le système EDT
  </div>
</div>

<table>
  <thead>
    <tr>
      <th>Jour</th>
      <th>Heure</th>
      <th>Type</th>
      <th>Module</th>
      <th>Prof</th>
      <th>Salle</th>
      <th>Groupe</th>
    </tr>
  </thead>
  <tbody>
    <?php if(!$rows): ?>
      <tr><td colspan="7" class="muted">Aucune séance.</td></tr>
    <?php else: foreach($rows as $r): ?>
      <tr>
        <td><?= e(day_name((int)$r['day_of_week'])) ?></td>
        <td><?= e(substr((string)$r['start_time'],0,5))."-".e(substr((string)$r['end_time'],0,5)) ?></td>
        <td><span class="badge"><?= e(strtoupper((string)$r['kind'])) ?></span></td>
        <td><?= e($r['code']." - ".$r['titre']) ?></td>
        <td><?= e($r['teacher_name']) ?></td>
        <td><?= e($r['room_name']) ?></td>
        <td><?= e($r['group_name']) ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

</body>
</html>
