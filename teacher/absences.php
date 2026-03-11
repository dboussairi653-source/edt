<?php
declare(strict_types=1);
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/helpers.php";
require_role(['teacher']);

$u = current_user();
$teacher_id = (int)$u['id'];

$msg = $err = "";
$ALERT_THRESHOLD = 3;

$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

// niveaux où le prof enseigne cette semaine
$levels = $pdo->prepare("
  SELECT DISTINCT n.id, CONCAT(f.nom,' - ',n.nom) AS label
  FROM timetable_entries te
  JOIN niveaux n ON n.id=te.niveau_id
  JOIN filieres f ON f.id=n.filiere_id
  WHERE te.week_start=? AND te.teacher_id=?
  ORDER BY f.nom, n.nom
");
$levels->execute([$week_start,$teacher_id]);
$levels = $levels->fetchAll();

$niveau_id = (int)($_GET['niveau_id'] ?? 0);
if ($niveau_id<=0 && $levels) $niveau_id = (int)$levels[0]['id'];

// séances du prof (semaine+niveau)
$entries = [];
if ($niveau_id>0) {
  $st = $pdo->prepare("
    SELECT te.id, te.day_of_week, te.start_time, te.end_time, te.kind, te.module_id,
           CONCAT(m.code,' - ',m.titre,' (',te.kind,') ',
                  LPAD(te.day_of_week,1,'0'),' ',
                  SUBSTR(te.start_time,1,5),'-',SUBSTR(te.end_time,1,5),' ',
                  COALESCE(g.nom,'(commun)')) AS label
    FROM timetable_entries te
    JOIN modules m ON m.id=te.module_id
    LEFT JOIN groupes g ON g.id=te.group_id
    WHERE te.week_start=? AND te.teacher_id=? AND te.niveau_id=?
    ORDER BY te.day_of_week, te.start_time
  ");
  $st->execute([$week_start,$teacher_id,$niveau_id]);
  $entries = $st->fetchAll();
}

// étudiants du niveau
$students = [];
if ($niveau_id>0) {
  $st = $pdo->prepare("
    SELECT u.id, u.nom, g.nom AS groupe
    FROM users u
    JOIN groupes g ON g.id=u.groupe_id
    WHERE u.role='student' AND g.niveau_id=?
    ORDER BY g.nom, u.nom
  ");
  $st->execute([$niveau_id]);
  $students = $st->fetchAll();
}

// POST: marquer absence uniquement (non_justifie)
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $user_id  = (int)($_POST['user_id'] ?? 0);
  $entry_id = (int)($_POST['entry_id'] ?? 0);

  if (!$user_id || !$entry_id) {
    $err = "Choix invalide.";
  } else {
    $chk = $pdo->prepare("SELECT id FROM timetable_entries WHERE id=? AND teacher_id=?");
    $chk->execute([$entry_id,$teacher_id]);
    if (!$chk->fetch()) {
      $err = "Séance invalide.";
    } else {
      $pdo->prepare("
        INSERT INTO absences(user_id, timetable_entry_id, status)
        VALUES (?,?, 'non_justifie')
        ON DUPLICATE KEY UPDATE status='non_justifie'
      ")->execute([$user_id,$entry_id]);

      $msg = "Absence marquée (non justifiée).";
    }
  }
}

// liste absences du prof (semaine+niveau)
$abs_list = [];
if ($niveau_id>0) {
  $st = $pdo->prepare("
    SELECT a.id, a.status, a.reason,
           u.id AS student_id, u.nom AS student_name, g.nom AS group_name,
           te.id AS entry_id, te.module_id, te.day_of_week, te.start_time, te.end_time, te.kind,
           m.code, m.titre
    FROM absences a
    JOIN users u ON u.id=a.user_id
    JOIN groupes g ON g.id=u.groupe_id
    JOIN timetable_entries te ON te.id=a.timetable_entry_id
    JOIN modules m ON m.id=te.module_id
    WHERE te.week_start=? AND te.teacher_id=? AND te.niveau_id=?
    ORDER BY te.day_of_week, te.start_time, g.nom, u.nom
  ");
  $st->execute([$week_start,$teacher_id,$niveau_id]);
  $abs_list = $st->fetchAll();
}

// ALERTS: compter non_justifie par étudiant+module (toutes semaines) pour ce prof
$alertMap = []; // [$student_id][$module_id] => count
$st = $pdo->prepare("
  SELECT a.user_id, te.module_id, COUNT(*) c
  FROM absences a
  JOIN timetable_entries te ON te.id=a.timetable_entry_id
  WHERE te.teacher_id=? AND a.status='non_justifie'
  GROUP BY a.user_id, te.module_id
");
$st->execute([$teacher_id]);
foreach($st->fetchAll() as $r){
  $sid = (int)$r['user_id'];
  $mid = (int)$r['module_id'];
  $alertMap[$sid][$mid] = (int)$r['c'];
}

$title="Absences (Prof)";
require __DIR__."/../includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0">Absences (mes séances)</h4>
    <div class="text-muted small">
      Semaine (lundi) : <b><?= e($week_start) ?></b> — Justification : <b>Admin seulement</b>
      — Alerte : <b><?= (int)$ALERT_THRESHOLD ?>+</b> non justifiées dans un module
    </div>
  </div>

  <form method="get" class="d-flex gap-2">
    <input type="date" class="form-control" name="week" value="<?= e($week_start) ?>">
    <select class="form-select" name="niveau_id" onchange="this.form.submit()">
      <?php foreach($levels as $lv): ?>
        <option value="<?= (int)$lv['id'] ?>" <?= ((int)$lv['id']===$niveau_id)?'selected':'' ?>>
          <?= e($lv['label']) ?>
        </option>
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
      <label class="form-label">Séance (mes séances)</label>
      <select name="entry_id" class="form-select" required>
        <option value="">-- choisir --</option>
        <?php foreach($entries as $e): ?>
          <option value="<?= (int)$e['id'] ?>"><?= e($e['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <button class="btn btn-dark">Marquer absent (non justifié)</button>
    </div>
  </form>
</div>

<div class="card p-3">
  <h5 class="mb-3">Liste des absences (semaine + niveau)</h5>
  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Étudiant</th><th>Groupe</th><th>Séance</th><th>Statut</th><th>Motif (admin)</th><th>Alerte module</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$abs_list): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Aucune absence.</td></tr>
        <?php else: foreach($abs_list as $a): ?>
          <?php
            $sid = (int)$a['student_id'];
            $mid = (int)$a['module_id'];
            $c = (int)($alertMap[$sid][$mid] ?? 0);
            $isAlert = $c >= $ALERT_THRESHOLD;
          ?>
          <tr>
            <td>
              <a href="/edt/teacher/student_absences.php?student_id=<?= (int)$sid ?>" class="text-decoration-none">
                <?= e($a['student_name']) ?>
              </a>
            </td>
            <td><?= e($a['group_name']) ?></td>
            <td>
              <?= e(day_name((int)$a['day_of_week'])) ?>
              <?= e(substr($a['start_time'],0,5)."-".substr($a['end_time'],0,5)) ?>
              — <?= e($a['code']." - ".$a['titre']) ?> (<?= e(strtoupper($a['kind'])) ?>)
            </td>
            <td><?= $a['status']==='justifie' ? 'Justifiée' : 'Non justifiée' ?></td>
            <td><?= e((string)$a['reason']) ?></td>
            <td>
              <?php if($isAlert): ?>
                <span class="badge bg-danger"><?= $c ?> non justifiées</span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__."/../includes/footer.php"; ?>
