<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

$id = (int)($_GET['id'] ?? 0);
$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

// Récupération séance
$stmt = $pdo->prepare("SELECT * FROM timetable_entries WHERE id=?");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) exit("Séance introuvable");

// Données pour listes
$modules = $pdo->query("SELECT id, CONCAT(code,' - ',titre) label FROM modules")->fetchAll();
$teachers = $pdo->query("SELECT id, nom FROM users WHERE role='teacher'")->fetchAll();
$groups = $pdo->query("
  SELECT g.id, CONCAT(f.nom,' / ',n.nom,' / ',g.nom) label
  FROM groupes g
  JOIN niveaux n ON n.id=g.niveau_id
  JOIN filieres f ON f.id=n.filiere_id
")->fetchAll();
$rooms = $pdo->query("SELECT id, CONCAT(nom,' (',capacite,')') label FROM rooms")->fetchAll();

/* Vérification conflits */
function has_conflict(PDO $pdo, array $d, int $ignore_id): bool {
  $sql = "
    SELECT start_time, end_time
    FROM timetable_entries
    WHERE week_start=? AND day_of_week=? AND id<>?
    AND (teacher_id=? OR room_id=? OR group_id=?)
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

  foreach ($st->fetchAll() as $r) {
    if (is_time_overlap($d['start_time'], $d['end_time'], $r['start_time'], $r['end_time'])) {
      return true;
    }
  }
  return false;
}

$err = $msg = "";

// Enregistrement modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data = [
    'week_start' => $week_start,
    'day_of_week' => (int)$_POST['day_of_week'],
    'start_time' => $_POST['start_time'],
    'end_time' => $_POST['end_time'],
    'module_id' => (int)$_POST['module_id'],
    'teacher_id' => (int)$_POST['teacher_id'],
    'group_id' => (int)$_POST['group_id'],
    'room_id' => (int)$_POST['room_id'],
    'notes' => trim($_POST['notes'])
  ];

  if ($data['start_time'] >= $data['end_time']) {
    $err = "Heures invalides.";
  } elseif (has_conflict($pdo, $data, $id)) {
    $err = "Conflit détecté (prof/salle/groupe déjà occupé).";
  } else {
    $pdo->prepare("
      UPDATE timetable_entries SET
      week_start=?, day_of_week=?, start_time=?, end_time=?,
      module_id=?, teacher_id=?, group_id=?, room_id=?, notes=?
      WHERE id=?
    ")->execute([
      $data['week_start'],
      $data['day_of_week'],
      $data['start_time'],
      $data['end_time'],
      $data['module_id'],
      $data['teacher_id'],
      $data['group_id'],
      $data['room_id'],
      $data['notes'],
      $id
    ]);
    $msg = "Séance modifiée avec succès.";
    $row = array_merge($row, $data);
  }
}

$title = "Modifier séance";
require "../includes/header.php";
?>

<div class="mb-3 d-flex justify-content-between">
  <h4>Modifier une séance</h4>
  <a class="btn btn-outline-secondary" href="/edt/admin/timetable.php?week=<?= e($week_start) ?>">Retour</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif ?>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif ?>

<form method="post" class="card p-4 row g-3">
  <div class="col-md-2">
    <label class="form-label">Jour</label>
    <select name="day_of_week" class="form-select">
      <?php for ($d=1;$d<=6;$d++): ?>
        <option value="<?= $d ?>" <?= $row['day_of_week']==$d?'selected':'' ?>>
          <?= e(day_name($d)) ?>
        </option>
      <?php endfor ?>
    </select>
  </div>

  <div class="col-md-2">
    <label class="form-label">Début</label>
    <input type="time" name="start_time" class="form-control"
           value="<?= substr($row['start_time'],0,5) ?>" required>
  </div>

  <div class="col-md-2">
    <label class="form-label">Fin</label>
    <input type="time" name="end_time" class="form-control"
           value="<?= substr($row['end_time'],0,5) ?>" required>
  </div>

  <div class="col-md-6">
    <label class="form-label">Module</label>
    <select name="module_id" class="form-select">
      <?php foreach ($modules as $m): ?>
        <option value="<?= $m['id'] ?>" <?= $row['module_id']==$m['id']?'selected':'' ?>>
          <?= e($m['label']) ?>
        </option>
      <?php endforeach ?>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label">Professeur</label>
    <select name="teacher_id" class="form-select">
      <?php foreach ($teachers as $t): ?>
        <option value="<?= $t['id'] ?>" <?= $row['teacher_id']==$t['id']?'selected':'' ?>>
          <?= e($t['nom']) ?>
        </option>
      <?php endforeach ?>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label">Groupe</label>
    <select name="group_id" class="form-select">
      <?php foreach ($groups as $g): ?>
        <option value="<?= $g['id'] ?>" <?= $row['group_id']==$g['id']?'selected':'' ?>>
          <?= e($g['label']) ?>
        </option>
      <?php endforeach ?>
    </select>
  </div>

  <div class="col-md-4">
    <label class="form-label">Salle</label>
    <select name="room_id" class="form-select">
      <?php foreach ($rooms as $r): ?>
        <option value="<?= $r['id'] ?>" <?= $row['room_id']==$r['id']?'selected':'' ?>>
          <?= e($r['label']) ?>
        </option>
      <?php endforeach ?>
    </select>
  </div>

  <div class="col-12">
    <label class="form-label">Notes</label>
    <input name="notes" class="form-control" value="<?= e($row['notes'] ?? '') ?>">
  </div>

  <div class="col-12">
    <button class="btn btn-dark">Enregistrer</button>
  </div>
</form>

<?php require "../includes/footer.php"; ?>
