<?php
declare(strict_types=1);
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/helpers.php";
require_role(['teacher']);

$u = current_user();
$teacher_id = (int)$u['id'];

$student_id = (int)($_GET['student_id'] ?? 0);
if ($student_id <= 0) exit("Étudiant invalide.");

$week = $_GET['week'] ?? '';
$week_start = $week ? week_start_from_date($week) : '';

$module_id = (int)($_GET['module_id'] ?? 0); // filtre module (optionnel)

$st = $pdo->prepare("SELECT id, nom FROM users WHERE id=? AND role='student'");
$st->execute([$student_id]);
$student = $st->fetch();
if (!$student) exit("Étudiant introuvable.");

// modules du prof pour alimenter le filtre
$mods = $pdo->prepare("
  SELECT DISTINCT m.id, CONCAT(m.code,' - ',m.titre) AS label
  FROM timetable_entries te
  JOIN modules m ON m.id=te.module_id
  WHERE te.teacher_id=?
  ORDER BY m.code
");
$mods->execute([$teacher_id]);
$modules = $mods->fetchAll();

// requête historique absences (uniquement séances du prof)
$params = [$student_id, $teacher_id];
$where = "";

if ($week_start) { $where .= " AND te.week_start=? "; $params[] = $week_start; }
if ($module_id > 0) { $where .= " AND te.module_id=? "; $params[] = $module_id; }

$rows = $pdo->prepare("
  SELECT a.status, a.reason,
         te.week_start, te.day_of_week, te.start_time, te.end_time, te.kind,
         m.code, m.titre,
         r.nom AS room_name,
         COALESCE(g.nom,'(commun)') AS group_name
  FROM absences a
  JOIN timetable_entries te ON te.id=a.timetable_entry_id
  JOIN modules m ON m.id=te.module_id
  JOIN rooms r ON r.id=te.room_id
  LEFT JOIN groupes g ON g.id=te.group_id
  WHERE a.user_id=? AND te.teacher_id=? $where
  ORDER BY te.week_start DESC, te.day_of_week, te.start_time
");
$rows->execute($params);
$list = $rows->fetchAll();

$title = "Absences étudiant";
require __DIR__."/../includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0">Absences — <?= e($student['nom']) ?></h4>
    <div class="text-muted small">Historique sur tes séances (lecture seule).</div>
  </div>

  <a class="btn btn-outline-secondary" href="/edt/teacher/absences.php">Retour</a>
</div>

<div class="card p-3 mb-3">
  <h5 class="mb-3">Filtres</h5>
  <form method="get" class="row g-2">
    <input type="hidden" name="student_id" value="<?= (int)$student_id ?>">

    <div class="col-md-3">
      <label class="form-label">Semaine (lundi)</label>
      <input type="date" class="form-control" name="week" value="<?= e($week_start) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Module</label>
      <select name="module_id" class="form-select">
        <option value="0">-- Tous les modules --</option>
        <?php foreach($modules as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ((int)$m['id']===$module_id)?'selected':'' ?>>
            <?= e($m['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-3 d-flex align-items-end gap-2">
      <button class="btn btn-dark w-100">Appliquer</button>
      <a class="btn btn-outline-dark w-100" href="/edt/teacher/student_absences.php?student_id=<?= (int)$student_id ?>">Reset</a>
    </div>
  </form>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Semaine</th><th>Jour</th><th>Heure</th><th>Type</th><th>Module</th><th>Salle</th><th>Groupe</th><th>Statut</th><th>Motif (admin)</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$list): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">Aucune absence avec ces filtres.</td></tr>
        <?php else: foreach($list as $a): ?>
          <tr>
            <td><?= e($a['week_start']) ?></td>
            <td><?= e(day_name((int)$a['day_of_week'])) ?></td>
            <td><?= e(substr($a['start_time'],0,5))."-".e(substr($a['end_time'],0,5)) ?></td>
            <td><?= e(strtoupper($a['kind'])) ?></td>
            <td><?= e($a['code']." - ".$a['titre']) ?></td>
            <td><?= e($a['room_name']) ?></td>
            <td><?= e($a['group_name']) ?></td>
            <td><?= $a['status']==='justifie' ? 'Justifiée' : 'Non justifiée' ?></td>
            <td><?= e((string)$a['reason']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__."/../includes/footer.php"; ?>
