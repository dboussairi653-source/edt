<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { exit("ID invalide."); }

$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

$filiere_id = (int)($_GET['filiere_id'] ?? 0);
$niveau_id  = (int)($_GET['niveau_id'] ?? 0);

$err = $msg = "";

/* ========= charger la séance ========= */
$st = $pdo->prepare("SELECT * FROM timetable_entries WHERE id=?");
$st->execute([$id]);
$te = $st->fetch();
if (!$te) exit("Séance introuvable.");

/* ========= helper: choisir colonne label modules ========= */
function module_label_query(PDO $pdo): array {
  $cols = $pdo->query("SHOW COLUMNS FROM modules")->fetchAll(PDO::FETCH_COLUMN, 0);

  $hasCode  = in_array('code', $cols, true);
  $hasTitre = in_array('titre', $cols, true);

  // si pas "titre", essayer d’autres colonnes fréquentes
  $textCandidates = ['titre','nom','libelle','intitule','name','module'];
  $textCol = null;
  foreach ($textCandidates as $c) {
    if (in_array($c, $cols, true)) { $textCol = $c; break; }
  }

  if ($hasCode && $hasTitre) {
    return [
      "SELECT id, CONCAT(code,' - ',titre) AS label FROM modules ORDER BY code, titre",
      'label'
    ];
  }

  if ($textCol) {
    return [
      "SELECT id, $textCol AS label FROM modules ORDER BY $textCol",
      'label'
    ];
  }

  // dernier fallback (au moins rien ne casse)
  return [
    "SELECT id, CAST(id AS CHAR) AS label FROM modules ORDER BY id",
    'label'
  ];
}

/* ========= listes (modules / profs / salles / groupes) ========= */

// modules (robuste)
[$sqlModules, $labelKey] = module_label_query($pdo);
$modules = $pdo->query($sqlModules)->fetchAll();

// teachers filtrés par filière si filiere_id fourni (sinon tous)
if ($filiere_id > 0) {
  $st = $pdo->prepare("SELECT id, nom FROM users WHERE role='teacher' AND filiere_id=? ORDER BY nom");
  $st->execute([$filiere_id]);
  $teachers = $st->fetchAll();
} else {
  $teachers = $pdo->query("SELECT id, nom FROM users WHERE role='teacher' ORDER BY nom")->fetchAll();
}

// rooms
$rooms = $pdo->query("SELECT id, CONCAT(nom,' (',type,')') AS label FROM rooms ORDER BY nom")->fetchAll();

// groupes (pour TP) : on se base sur niveau_id de la séance
$groups = [];
if ((int)$te['niveau_id'] > 0) {
  $st = $pdo->prepare("SELECT id, nom FROM groupes WHERE niveau_id=? ORDER BY nom");
  $st->execute([(int)$te['niveau_id']]);
  $groups = $st->fetchAll();
}

/* ========= POST update ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $day_of_week = (int)($_POST['day_of_week'] ?? 1);
  $start_time  = (string)($_POST['start_time'] ?? '08:00');
  $end_time    = (string)($_POST['end_time'] ?? '10:00');
  $kind        = $_POST['kind'] ?? 'cours';
  if (!in_array($kind, ['cours','td','tp'], true)) $kind = 'cours';

  $module_id   = (int)($_POST['module_id'] ?? 0);
  $teacher_id  = (int)($_POST['teacher_id'] ?? 0);
  $room_id     = (int)($_POST['room_id'] ?? 0);
  $notes       = trim((string)($_POST['notes'] ?? ''));

  $group_id = null;
  if ($kind === 'tp') {
    $group_id = (int)($_POST['group_id'] ?? 0);
    if ($group_id <= 0) $err = "Pour un TP, choisis un groupe.";
  }

  if ($err === '') {
    if ($module_id<=0 || $teacher_id<=0 || $room_id<=0) $err = "Module/Prof/Salle obligatoires.";
    elseif ($start_time >= $end_time) $err = "Heures invalides.";
    else {
      $pdo->prepare("
        UPDATE timetable_entries
        SET day_of_week=?, start_time=?, end_time=?, kind=?, module_id=?, teacher_id=?, room_id=?, group_id=?, notes=?
        WHERE id=?
      ")->execute([
        $day_of_week, $start_time, $end_time, $kind, $module_id, $teacher_id, $room_id,
        $group_id, ($notes !== '' ? $notes : null),
        $id
      ]);

      // refresh
      $st = $pdo->prepare("SELECT * FROM timetable_entries WHERE id=?");
      $st->execute([$id]);
      $te = $st->fetch();

      $msg = "Séance modifiée.";
    }
  }
}

$title = "Modifier séance";
require __DIR__ . "/../includes/header.php";
?>

<div class="page-title mb-4" data-aos="fade-up">
  <div>
    <h4 class="mb-0">Modifier une séance</h4>
    <div class="page-sub">Semaine du lundi : <strong><?= e($week_start) ?></strong></div>
  </div>
</div>

<?php if($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<div class="card p-4" data-aos="fade-up">
  <form method="post" class="row g-2" id="editForm">

    <div class="col-md-2">
      <label class="form-label">Jour</label>
      <select name="day_of_week" class="form-select">
        <?php for($d=1;$d<=6;$d++): ?>
          <option value="<?= $d ?>" <?= ((int)$te['day_of_week']===$d)?'selected':'' ?>>
            <?= e(day_name($d)) ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="col-md-2">
      <label class="form-label">Début</label>
      <input type="time" name="start_time" class="form-control" value="<?= e(substr((string)$te['start_time'],0,5)) ?>" required>
    </div>

    <div class="col-md-2">
      <label class="form-label">Fin</label>
      <input type="time" name="end_time" class="form-control" value="<?= e(substr((string)$te['end_time'],0,5)) ?>" required>
    </div>

    <div class="col-md-2">
      <label class="form-label">Type</label>
      <select name="kind" class="form-select" id="kindSelect">
        <option value="cours" <?= ($te['kind']==='cours')?'selected':'' ?>>Cours</option>
        <option value="td"    <?= ($te['kind']==='td')?'selected':'' ?>>TD</option>
        <option value="tp"    <?= ($te['kind']==='tp')?'selected':'' ?>>TP</option>
      </select>
    </div>

    <div class="col-md-4" id="groupBox" style="display:none;">
      <label class="form-label">Groupe (TP)</label>
      <select name="group_id" class="form-select" id="groupSelect">
        <option value="">-- groupe --</option>
        <?php foreach($groups as $g): ?>
          <option value="<?= (int)$g['id'] ?>" <?= ((int)$te['group_id']===(int)$g['id'])?'selected':'' ?>>
            <?= e($g['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Module</label>
      <select name="module_id" class="form-select" required>
        <option value="">-- module --</option>
        <?php foreach($modules as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ((int)$te['module_id']===(int)$m['id'])?'selected':'' ?>>
            <?= e((string)$m['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Prof</label>
      <select name="teacher_id" class="form-select" required>
        <option value="">-- prof --</option>
        <?php foreach($teachers as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= ((int)$te['teacher_id']===(int)$t['id'])?'selected':'' ?>>
            <?= e($t['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Salle</label>
      <select name="room_id" class="form-select" required>
        <option value="">-- salle --</option>
        <?php foreach($rooms as $r): ?>
          <option value="<?= (int)$r['id'] ?>" <?= ((int)$te['room_id']===(int)$r['id'])?'selected':'' ?>>
            <?= e($r['label']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Notes</label>
      <input name="notes" class="form-control" value="<?= e((string)$te['notes']) ?>" placeholder="optionnel">
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-dark">Enregistrer</button>
      <a class="btn btn-outline-secondary"
         href="/edt/admin/timetable.php?week=<?= e(urlencode($week)) ?>&filiere_id=<?= (int)$filiere_id ?>&niveau_id=<?= (int)$niveau_id ?>">
         Retour
      </a>
    </div>
  </form>
</div>

<script>
document.addEventListener("DOMContentLoaded", ()=>{
  const kind = document.getElementById("kindSelect");
  const box  = document.getElementById("groupBox");
  const grp  = document.getElementById("groupSelect");

  function applyKind(){
    const isTP = kind.value === "tp";
    box.style.display = isTP ? "" : "none";
    if (!isTP && grp) grp.value = "";
  }
  kind.addEventListener("change", applyKind);
  applyKind();
});
</script>

<?php require __DIR__ . "/../includes/footer.php"; ?>
