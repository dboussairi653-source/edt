<?php
declare(strict_types=1);
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/helpers.php";
require_role(['admin']);

$msg=$err="";

$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

$filieres = $pdo->query("SELECT id, nom FROM filieres ORDER BY nom")->fetchAll();
$filiere_id = (int)($_GET['filiere_id'] ?? 0);
if ($filiere_id<=0 && $filieres) $filiere_id = (int)$filieres[0]['id'];

$niveaux = [];
if ($filiere_id>0) {
  $st=$pdo->prepare("SELECT id, nom FROM niveaux WHERE filiere_id=? ORDER BY nom");
  $st->execute([$filiere_id]);
  $niveaux=$st->fetchAll();
}

$niveau_id = (int)($_GET['niveau_id'] ?? 0);

// ✅ FIX: si niveau_id ne correspond pas à la filière, prendre le 1er niveau
$niveau_ids = array_map(fn($x)=>(int)$x['id'], $niveaux);
if ($niveau_id<=0 || ($niveau_ids && !in_array($niveau_id, $niveau_ids, true))) {
  if ($niveaux) $niveau_id = (int)$niveaux[0]['id'];
}

$students = [];
if ($niveau_id>0) {
  $st=$pdo->prepare("
    SELECT u.id, u.nom, u.email, g.nom AS groupe
    FROM users u
    JOIN groupes g ON g.id=u.groupe_id
    WHERE u.role='student' AND g.niveau_id=?
    ORDER BY g.nom, u.nom
  ");
  $st->execute([$niveau_id]);
  $students=$st->fetchAll();
}

$entries = [];
if ($niveau_id>0) {
  $st=$pdo->prepare("
    SELECT te.id, te.day_of_week, te.start_time, te.end_time, te.kind,
           CONCAT(m.id,' - ',m.titre,' (',te.kind,') ',
                  LPAD(te.day_of_week,1,'0'),' ',
                  SUBSTR(te.start_time,1,5),'-',SUBSTR(te.end_time,1,5)
           ) AS label
    FROM timetable_entries te
    JOIN modules m ON m.id=te.module_id
    WHERE te.week_start=? AND te.niveau_id=?
    ORDER BY te.day_of_week, te.start_time
  ");

  $st->execute([$week_start,$niveau_id]);
  $entries=$st->fetchAll();
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $mode = $_POST['mode'] ?? '';

  if ($mode === 'mark') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $entry_id = (int)($_POST['entry_id'] ?? 0);

    if (!$user_id || !$entry_id) $err="Choix invalide.";
    else {
      $pdo->prepare("
        INSERT INTO absences(user_id, timetable_entry_id, status)
        VALUES (?,?, 'non_justifie')
        ON DUPLICATE KEY UPDATE status='non_justifie'
      ")->execute([$user_id,$entry_id]);
      $msg="Absence marquée.";
    }
  }

  if ($mode === 'toggle') {
    $abs_id = (int)($_POST['abs_id'] ?? 0);
    $status = $_POST['status'] ?? 'non_justifie';
    $reason = trim($_POST['reason'] ?? '');

    if ($abs_id>0) {
      $pdo->prepare("UPDATE absences SET status=?, reason=? WHERE id=?")
          ->execute([$status, $reason ?: null, $abs_id]);
      $msg="Mise à jour faite.";
    }
  }
}

$abs_list = [];
if ($niveau_id > 0) {
  $st = $pdo->prepare("
    SELECT a.id, a.status, a.reason, a.created_at,
           u.nom AS student_name, g.nom AS group_name,
           te.day_of_week, te.start_time, te.end_time, te.kind,
           m.titre
    FROM absences a
    JOIN users u ON u.id = a.user_id
    JOIN groupes g ON g.id = u.groupe_id
    JOIN timetable_entries te ON te.id = a.timetable_entry_id
    JOIN modules m ON m.id = te.module_id
    WHERE te.week_start = ? AND te.niveau_id = ?
    ORDER BY te.day_of_week, te.start_time, g.nom, u.nom
  ");
  $st->execute([$week_start, $niveau_id]);
  $abs_list = $st->fetchAll();
}

$title="Absences";
require __DIR__."/../includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0">Gestion des absences</h4>
    <div class="text-muted small">Semaine (lundi) : <b><?= e($week_start) ?></b></div>
  </div>
  <form method="get" class="d-flex gap-2">
    <input type="date" class="form-control" name="week" value="<?= e($week_start) ?>">
    <select class="form-select" name="filiere_id" onchange="this.form.submit()">
      <?php foreach($filieres as $f): ?>
        <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id']===$filiere_id)?'selected':'' ?>><?= e($f['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select" name="niveau_id" onchange="this.form.submit()">
      <?php foreach($niveaux as $n): ?>
        <option value="<?= (int)$n['id'] ?>" <?= ((int)$n['id']===$niveau_id)?'selected':'' ?>><?= e($n['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-dark">OK</button>
  </form>
</div>

<?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<div class="card p-3 mb-3">
  <h5 class="mb-3">Marquer une absence</h5>
  <form method="post" class="row g-2">
    <input type="hidden" name="mode" value="mark">

    <div class="col-md-5">
      <label class="form-label">Étudiant</label>
      <select name="user_id" class="form-select" required>
        <option value="">-- choisir --</option>
        <?php foreach($students as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['groupe']." - ".$s['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-7">
      <label class="form-label">Séance</label>
      <select name="entry_id" class="form-select" required>
        <option value="">-- choisir --</option>
        <?php foreach($entries as $e): ?>
          <option value="<?= (int)$e['id'] ?>"><?= e($e['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <button class="btn btn-dark">Marquer absent</button>
    </div>
  </form>
</div>

<div class="card p-3">
  <h5 class="mb-3">Liste des absences (semaine + niveau)</h5>
  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Étudiant</th><th>Groupe</th><th>Séance</th><th>Statut</th><th>Motif</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$abs_list): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Aucune absence.</td></tr>
        <?php else: foreach($abs_list as $a): ?>
          <tr>
            <td><?= e($a['student_name']) ?></td>
            <td><?= e($a['group_name']) ?></td>
            <td>
              <?= e(day_name((int)$a['day_of_week'])) ?>
              <?= e(substr($a['start_time'],0,5)."-".substr($a['end_time'],0,5)) ?>
              — <?= e($a['titre']) ?> (<?= e(strtoupper($a['kind'])) ?>)

            </td>
            <td><?= $a['status']==='justifie' ? 'Justifiée' : 'Non justifiée' ?></td>
            <td><?= e((string)$a['reason']) ?></td>
            <td>
              <form method="post" class="d-flex gap-2">
                <input type="hidden" name="mode" value="toggle">
                <input type="hidden" name="abs_id" value="<?= (int)$a['id'] ?>">
                <select name="status" class="form-select form-select-sm" style="max-width:160px">
                  <option value="non_justifie" <?= $a['status']==='non_justifie'?'selected':'' ?>>Non justifiée</option>
                  <option value="justifie" <?= $a['status']==='justifie'?'selected':'' ?>>Justifiée</option>
                </select>
                <input name="reason" class="form-control form-control-sm" placeholder="Motif" value="<?= e((string)$a['reason']) ?>">
                <button class="btn btn-sm btn-outline-dark">OK</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__."/../includes/footer.php"; ?>
