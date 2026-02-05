<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

$err = "";
$msg = "";

// Choix semaine (date quelconque) -> on calcule le lundi de la semaine
$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

// Listes pour les selects
$modules = $pdo->query("SELECT id, CONCAT(code,' - ',titre) AS label FROM modules ORDER BY code")->fetchAll();
$teachers = $pdo->query("SELECT id, nom FROM users WHERE role='teacher' ORDER BY nom")->fetchAll();
$groups = $pdo->query("
  SELECT g.id, CONCAT(f.nom,' / ',n.nom,' / ',g.nom) AS label
  FROM groupes g
  JOIN niveaux n ON n.id=g.niveau_id
  JOIN filieres f ON f.id=n.filiere_id
  ORDER BY f.nom,n.nom,g.nom
")->fetchAll();
$rooms = $pdo->query("SELECT id, CONCAT(nom,' (',capacite,')') AS label FROM rooms ORDER BY nom")->fetchAll();

/**
 * Vérifie conflits: même semaine+jour et overlap horaire
 * - même prof OU même salle OU même groupe
 */
function has_conflict(PDO $pdo, array $d, int $ignore_id = 0): bool {
  $sql = "
    SELECT te.start_time, te.end_time, te.teacher_id, te.room_id, te.group_id
    FROM timetable_entries te
    WHERE te.week_start=? AND te.day_of_week=? AND te.id<>?
      AND (te.teacher_id=? OR te.room_id=? OR te.group_id=?)
  ";
  $st = $pdo->prepare($sql);
  $st->execute([
    $d['week_start'],
    $d['day_of_week'],
    $ignore_id,
    $d['teacher_id'],
    $d['room_id'],
    $d['group_id']
  ]);

  $rows = $st->fetchAll();
  foreach ($rows as $r) {
    if (is_time_overlap($d['start_time'], $d['end_time'], $r['start_time'], $r['end_time'])) {
      return true;
    }
  }
  return false;
}

// Ajout d'une séance
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = [
    'week_start' => $week_start,
    'day_of_week' => (int)($_POST['day_of_week'] ?? 1),
    'start_time' => $_POST['start_time'] ?? '08:30',
    'end_time' => $_POST['end_time'] ?? '10:30',
    'module_id' => (int)($_POST['module_id'] ?? 0),
    'teacher_id' => (int)($_POST['teacher_id'] ?? 0),
    'group_id' => (int)($_POST['group_id'] ?? 0),
    'room_id' => (int)($_POST['room_id'] ?? 0),
    'notes' => trim($_POST['notes'] ?? '')
  ];

  if ($data['module_id'] <= 0 || $data['teacher_id'] <= 0 || $data['group_id'] <= 0 || $data['room_id'] <= 0) {
    $err = "Veuillez remplir tous les champs (module, prof, groupe, salle).";
  } elseif (!($data['start_time'] < $data['end_time'])) {
    $err = "Heures invalides : l'heure de début doit être avant l'heure de fin.";
  } else {
    if (has_conflict($pdo, $data, 0)) {
      $err = "Conflit détecté (prof/salle/groupe déjà occupé sur ce créneau).";
    } else {
      $pdo->prepare("
        INSERT INTO timetable_entries
        (week_start, day_of_week, start_time, end_time, module_id, teacher_id, group_id, room_id, notes)
        VALUES (?,?,?,?,?,?,?,?,?)
      ")->execute([
        $data['week_start'],
        $data['day_of_week'],
        $data['start_time'],
        $data['end_time'],
        $data['module_id'],
        $data['teacher_id'],
        $data['group_id'],
        $data['room_id'],
        $data['notes']
      ]);
      $msg = "Séance ajoutée avec succès.";
    }
  }
}

// Récupération des séances de la semaine
$st = $pdo->prepare("
  SELECT te.*,
         m.code, m.titre,
         u.nom AS teacher_name,
         r.nom AS room_name,
         CONCAT(f.nom,' / ',n.nom,' / ',g.nom) AS group_name
  FROM timetable_entries te
  JOIN modules m ON m.id=te.module_id
  JOIN users u ON u.id=te.teacher_id
  JOIN rooms r ON r.id=te.room_id
  JOIN groupes g ON g.id=te.group_id
  JOIN niveaux n ON n.id=g.niveau_id
  JOIN filieres f ON f.id=n.filiere_id
  WHERE te.week_start=?
  ORDER BY te.day_of_week, te.start_time
");
$st->execute([$week_start]);
$rows = $st->fetchAll();

$title = "Emploi du temps (Admin)";
require_once __DIR__ . "/../includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0">Emploi du temps</h4>
    <div class="text-muted small">Semaine du lundi : <b><?= e($week_start) ?></b></div>
  </div>
  <form class="d-flex gap-2" method="get">
    <input class="form-control" type="date" name="week" value="<?= e($week_start) ?>">
    <button class="btn btn-outline-dark">Aller</button>
  </form>
</div>

<?php if ($msg): ?>
  <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endif; ?>

<div class="card p-4 mb-3">
  <h5 class="mb-3">Ajouter une séance (conflits auto)</h5>
  <form method="post" class="row g-3">
    <div class="col-md-2">
      <label class="form-label">Jour</label>
      <select class="form-select" name="day_of_week" required>
        <?php for ($d=1; $d<=6; $d++): ?>
          <option value="<?= $d ?>"><?= e(day_name($d)) ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">Début</label>
      <input class="form-control" type="time" name="start_time" value="08:30" required>
    </div>

    <div class="col-md-2">
      <label class="form-label">Fin</label>
      <input class="form-control" type="time" name="end_time" value="10:30" required>
    </div>

    <div class="col-md-6">
      <label class="form-label">Module</label>
      <select class="form-select" name="module_id" required>
        <option value="">-- choisir --</option>
        <?php foreach ($modules as $m): ?>
          <option value="<?= (int)$m['id'] ?>"><?= e($m['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Professeur</label>
      <select class="form-select" name="teacher_id" required>
        <option value="">-- choisir --</option>
        <?php foreach ($teachers as $t): ?>
          <option value="<?= (int)$t['id'] ?>"><?= e($t['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Groupe</label>
      <select class="form-select" name="group_id" required>
        <option value="">-- choisir --</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int)$g['id'] ?>"><?= e($g['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Salle</label>
      <select class="form-select" name="room_id" required>
        <option value="">-- choisir --</option>
        <?php foreach ($rooms as $r): ?>
          <option value="<?= (int)$r['id'] ?>"><?= e($r['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <label class="form-label">Notes (optionnel)</label>
      <input class="form-control" name="notes" placeholder="ex: Cours / TP / ...">
    </div>

    <div class="col-12">
      <button class="btn btn-dark">Ajouter</button>
    </div>
  </form>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Jour</th>
          <th>Heure</th>
          <th>Module</th>
          <th>Prof</th>
          <th>Groupe</th>
          <th>Salle</th>
          <th>Notes</th>
          <th style="width:190px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e(day_name((int)$r['day_of_week'])) ?></td>
            <td><?= e(substr($r['start_time'],0,5)) ?> - <?= e(substr($r['end_time'],0,5)) ?></td>
            <td><?= e($r['code']." - ".$r['titre']) ?></td>
            <td><?= e($r['teacher_name']) ?></td>
            <td><?= e($r['group_name']) ?></td>
            <td><?= e($r['room_name']) ?></td>
            <td><?= e($r['notes'] ?? '') ?></td>
            <td class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-dark"
                 href="/edt/admin/timetable_edit.php?id=<?= (int)$r['id'] ?>&week=<?= e($week_start) ?>">
                 Modifier
              </a>
              <a class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Supprimer cette séance ?')"
                 href="/edt/admin/timetable_delete.php?id=<?= (int)$r['id'] ?>&week=<?= e($week_start) ?>">
                 Supprimer
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Aucune séance cette semaine.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
