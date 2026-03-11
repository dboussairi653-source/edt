<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

$err = $msg = "";

// filtres
$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

$filieres = $pdo->query("SELECT id, nom FROM filieres ORDER BY nom")->fetchAll();
$filiere_id = (int)($_GET['filiere_id'] ?? 0);
if ($filiere_id <= 0 && $filieres) $filiere_id = (int)$filieres[0]['id'];

$niveaux = [];
if ($filiere_id > 0) {
  $st = $pdo->prepare("SELECT id, nom FROM niveaux WHERE filiere_id=? ORDER BY nom");
  $st->execute([$filiere_id]);
  $niveaux = $st->fetchAll();
}

$niveau_id = (int)($_GET['niveau_id'] ?? 0);

// ✅ niveau doit appartenir à la filière
$niveau_ids = array_map(fn($x)=>(int)$x['id'], $niveaux);
if ($niveau_id<=0 || ($niveau_ids && !in_array($niveau_id, $niveau_ids, true))) {
  if ($niveaux) $niveau_id = (int)$niveaux[0]['id'];
}

// groupes du niveau
$groups = [];
if ($niveau_id > 0) {
  $st = $pdo->prepare("SELECT id, nom FROM groupes WHERE niveau_id=? ORDER BY nom");
  $st->execute([$niveau_id]);
  $groups = $st->fetchAll();
}

// modules (compat si pas de colonne code)
$cols = $pdo->query("SHOW COLUMNS FROM modules")->fetchAll(PDO::FETCH_COLUMN, 0);
$hasCode  = in_array('code', $cols, true);
$hasTitre = in_array('titre', $cols, true);

if ($hasCode && $hasTitre) {
  $modules = $pdo->query("
    SELECT id, CONCAT(COALESCE(code,''),' - ',COALESCE(titre,'')) AS label
    FROM modules
    ORDER BY code
  ")->fetchAll();
} elseif ($hasTitre) {
  $modules = $pdo->query("
    SELECT id, COALESCE(titre,'') AS label
    FROM modules
    ORDER BY titre
  ")->fetchAll();
} else {
  $modules = $pdo->query("
    SELECT id, CAST(id AS CHAR) AS label
    FROM modules
    ORDER BY id
  ")->fetchAll();
}


// profs filtrés par filière (selon ton choix actuel)
$teachers = [];
if ($filiere_id > 0) {
  $st = $pdo->prepare("SELECT id, nom FROM users WHERE role='teacher' AND filiere_id=? ORDER BY nom");
  $st->execute([$filiere_id]);
  $teachers = $st->fetchAll();
} else {
  $teachers = $pdo->query("SELECT id, nom FROM users WHERE role='teacher' ORDER BY nom")->fetchAll();
}

$rooms = $pdo->query("SELECT id, CONCAT(nom,' (',type,')') AS label, type FROM rooms ORDER BY nom")->fetchAll();

function overlap($s1,$e1,$s2,$e2): bool { return ($s1 < $e2) && ($s2 < $e1); }

function has_conflict(PDO $pdo, array $d): array|null {
  $sql = "
    SELECT te.*,
           u.nom AS teacher_name,
           r.nom AS room_name,
           COALESCE(g.nom,'(commun)') AS group_name
    FROM timetable_entries te
    JOIN users u ON u.id=te.teacher_id
    JOIN rooms r ON r.id=te.room_id
    LEFT JOIN groupes g ON g.id=te.group_id
    WHERE te.week_start=? AND te.day_of_week=?
      AND (
        te.teacher_id=? OR te.room_id=? OR
        (
          te.niveau_id=? AND
          (
            te.group_id IS NULL
            OR ? IS NULL
            OR te.group_id = ?
          )
        )
      )
    ORDER BY te.start_time
  ";

  $params = [
    $d['week_start'], $d['day_of_week'],
    $d['teacher_id'], $d['room_id'],
    $d['niveau_id'],
    $d['group_id'],
    $d['group_id'],
  ];

  $st = $pdo->prepare($sql);
  $st->execute($params);

  foreach ($st->fetchAll() as $r) {
    if (overlap($d['start_time'], $d['end_time'], $r['start_time'], $r['end_time'])) {
      return $r; // return the conflicting session
    }
  }
  return null;
}

// ✅ POST : un seul formulaire (cours/td/tp)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($niveau_id <= 0) $err = "Choisis d’abord une filière puis un niveau.";
  else {
    $kind = $_POST['kind'] ?? 'cours';
    if (!in_array($kind, ['cours','td','tp'], true)) $kind = 'cours';

    $group_id = null;
    if ($kind === 'tp') {
      $group_id = (int)($_POST['group_id'] ?? 0);
      if ($group_id <= 0) $err = "Pour un TP, tu dois choisir un groupe.";
    }

    $data = [
      'week_start' => $week_start,
      'day_of_week'=> (int)($_POST['day_of_week'] ?? 1),
      'start_time' => (string)($_POST['start_time'] ?? '08:00'),
      'end_time'   => (string)($_POST['end_time'] ?? '10:00'),
      'kind'       => $kind,
      'module_id'  => (int)($_POST['module_id'] ?? 0),
      'teacher_id' => (int)($_POST['teacher_id'] ?? 0),
      'room_id'    => (int)($_POST['room_id'] ?? 0),
      'niveau_id'  => $niveau_id,
      'group_id'   => $group_id,
      'notes'      => trim((string)($_POST['notes'] ?? '')),
    ];

    if ($err === '') {
      if ($data['module_id']<=0 || $data['teacher_id']<=0 || $data['room_id']<=0) $err="Module/Prof/Salle obligatoires.";
      elseif ($data['start_time'] >= $data['end_time']) $err="Heures invalides.";
     elseif ($c = has_conflict($pdo,$data)) {

  $new = find_next_free_slot($pdo, $data, 30);

  if (!$new) {
    // keep your detailed error if no slot found
    $err = "Conflit détecté avec: "
         . strtoupper((string)$c['kind']) . " "
         . substr((string)$c['start_time'],0,5) . "-" . substr((string)$c['end_time'],0,5)
         . " | Prof: " . ($c['teacher_name'] ?? '')
         . " | Salle: " . ($c['room_name'] ?? '')
         . " | Groupe: " . ($c['group_name'] ?? '')
         . " — Aucun créneau libre trouvé (08:00-18:00).";
  } else {
    // auto-fix: move + continue to insert
    $data = $new;

    $msg = "Conflit détecté → déplacé automatiquement à "
         . day_name((int)$data['day_of_week']) . " "
         . substr((string)$data['start_time'],0,5) . "-" . substr((string)$data['end_time'],0,5) . ".";
  }
}

if ($err === '') {
  $pdo->prepare("
    INSERT INTO timetable_entries
    (week_start,day_of_week,start_time,end_time,kind,module_id,teacher_id,room_id,niveau_id,group_id,notes)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)
  ")->execute([
    $data['week_start'],$data['day_of_week'],$data['start_time'],$data['end_time'],$data['kind'],
    $data['module_id'],$data['teacher_id'],$data['room_id'],$data['niveau_id'],$data['group_id'],$data['notes']
  ]);

  if ($msg === '') {
    $msg = ($kind === 'tp') ? "TP ajouté." : "Séance ajoutée.";
  }
}

    }
  }
}
function time_to_min(string $t): int {
  [$h,$m] = array_map('intval', explode(':', substr($t,0,5)));
  return $h*60 + $m;
}

function min_to_time(int $min): string {
  $h = intdiv($min, 60);
  $m = $min % 60;
  return sprintf('%02d:%02d:00', $h, $m);
}

/** has_conflict($pdo,$data) returns array|null */
function find_next_free_slot(PDO $pdo, array $d, int $stepMin = 30): ?array {
  $duration = time_to_min($d['end_time']) - time_to_min($d['start_time']);
  if ($duration <= 0) return null;

  $workStart = 8*60;   // 08:00
  $workEnd   = 18*60;  // 18:00

  $origDay = (int)$d['day_of_week'];
  $daysToTry = array_values(array_unique([$origDay, 1,2,3,4,5,6]));

  foreach ($daysToTry as $day) {
    $startMin = ($day === $origDay) ? time_to_min($d['start_time']) : $workStart;

    for ($s = $startMin; $s + $duration <= $workEnd; $s += $stepMin) {
      $cand = $d;
      $cand['day_of_week'] = $day;
      $cand['start_time']  = min_to_time($s);
      $cand['end_time']    = min_to_time($s + $duration);

      if (!has_conflict($pdo, $cand)) { // null => no conflict
        return $cand;
      }
    }
  }
  return null;
}


// séances affichage (compat code)
$colsM = $pdo->query("SHOW COLUMNS FROM modules")->fetchAll(PDO::FETCH_COLUMN, 0);
$hasMCode = in_array('code', $colsM, true);

$st = $pdo->prepare("
  SELECT te.*,
         ".($hasMCode ? "m.code," : "'' AS code,")."
         m.titre,
         u.nom AS teacher_name,
         r.nom AS room_name,
         r.type AS room_type,
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

$title = "Emploi du temps (Admin)";
$body_class = "page-timetable"; // ✅ enables animations ONLY here
require __DIR__ . "/../includes/header.php";
?>


<div class="page-title mb-4" data-aos="fade-up">
  <div>
    <h4>Gestion emploi du temps</h4>
    <div class="page-sub">Semaine du lundi : <strong><?= e($week_start) ?></strong></div>
  </div>

  <form method="get" class="d-flex gap-2 flex-wrap">
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
    <button class="btn btn-dark">OK</button>
  </form>
</div>

<?php if($msg): ?><div class="alert alert-success fade-up"><?= e($msg) ?></div><?php endif; ?>
<?php if($err): ?><div class="alert alert-danger fade-up"><?= e($err) ?></div><?php endif; ?>

<!-- ✅ UN SEUL FORMULAIRE -->
<div class="card p-4 mb-4" data-aos="fade-up">
  <h5 class="mb-3"><i class="fa-solid fa-calendar-plus me-2"></i>Ajouter une séance (Cours / TD / TP)</h5>
  <div class="text-muted small mb-3">
    Cours/TD = commun (tous les groupes). TP = choisir un groupe.
  </div>

  <form method="post" class="row g-2" id="addForm">
    <div class="col-md-2">
      <label class="form-label">Jour</label>
      <select name="day_of_week" class="form-select">
        <?php for($d=1;$d<=6;$d++): ?><option value="<?= $d ?>"><?= e(day_name($d)) ?></option><?php endfor; ?>
      </select>
    </div>

    <div class="col-md-2"><label class="form-label">Début</label><input type="time" name="start_time" class="form-control" value="08:00" required></div>
    <div class="col-md-2"><label class="form-label">Fin</label><input type="time" name="end_time" class="form-control" value="10:00" required></div>

    <div class="col-md-2">
      <label class="form-label">Type</label>
      <select name="kind" class="form-select" id="kindSelect">
        <option value="cours">Cours</option>
        <option value="td">TD</option>
        <option value="tp">TP</option>
      </select>
    </div>

    <div class="col-md-4" id="groupBox" style="display:none;">
      <label class="form-label">Groupe (TP)</label>
      <select name="group_id" class="form-select" id="groupSelect">
        <option value="">-- groupe --</option>
        <?php foreach($groups as $g): ?><option value="<?= (int)$g['id'] ?>"><?= e($g['nom']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Module</label>
      <select name="module_id" class="form-select" required>
        <option value="">-- module --</option>
        <?php foreach($modules as $m): ?><option value="<?= (int)$m['id'] ?>"><?= e($m['label']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Prof (filtré par filière)</label>
      <select name="teacher_id" class="form-select" required>
        <option value="">-- prof --</option>
        <?php foreach($teachers as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['nom']) ?></option><?php endforeach; ?>
      </select>
      <?php if($filiere_id>0 && !$teachers): ?>
        <div class="text-danger small mt-1">Aucun prof dans cette filière (users.filiere_id).</div>
      <?php endif; ?>
    </div>

    <div class="col-md-4">
      <label class="form-label">Salle</label>
      <select name="room_id" class="form-select" required>
        <option value="">-- salle --</option>
        <?php foreach($rooms as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['label']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Notes</label>
      <input name="notes" class="form-control" placeholder="optionnel">
    </div>

    <div class="col-12">
      <button class="btn btn-dark"><i class="fa-solid fa-plus me-1"></i>Ajouter</button>
    </div>
  </form>
</div>

<!-- AFFICHAGE UNIQUE -->
<div class="card p-3" data-aos="fade-up">
  <div class="table-toolbar mb-2">
    <strong><i class="fa-solid fa-calendar-week me-2"></i>Emploi du temps (Cours + TD + TP)</strong>
    <input id="q" class="form-control table-search" placeholder="Rechercher...">
  </div>

  <div class="table-responsive">
    <table id="t" class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Jour</th><th>Heure</th><th>Type</th><th>Module</th><th>Prof</th><th>Salle</th><th>Groupe</th><th>Actions</th>
        </tr>
      </thead>
<tbody>
<?php if(!$rows): ?>
  <tr><td colspan="8" class="text-center text-muted py-4">Aucune séance.</td></tr>
<?php else: ?>

  <?php
    // Group rows by day_of_week
    $byDay = [];
    foreach ($rows as $r) {
      $d = (int)$r['day_of_week'];
      $byDay[$d][] = $r;
    }
    ksort($byDay);
  ?>

  <?php foreach ($byDay as $day => $list): ?>
    <?php $rowspan = count($list); ?>

    <?php foreach ($list as $i => $r): ?>
      <?php $modLabel = trim((string)$r['code']) !== '' ? ($r['code']." - ".$r['titre']) : ($r['titre'] ?? ''); ?>

      <tr>
        <?php if ($i === 0): ?>
          <!-- Show the day only once with rowspan -->
          <td rowspan="<?= $rowspan ?>" class="fw-semibold align-middle">
            <?= e(day_name((int)$day)) ?>
          </td>
        <?php endif; ?>

        <td><?= e(substr($r['start_time'],0,5)) ?>-<?= e(substr($r['end_time'],0,5)) ?></td>
        <td><?= e(strtoupper((string)$r['kind'])) ?></td>
        <td><?= e($modLabel) ?></td>
        <td><?= e($r['teacher_name']) ?></td>
        <td><?= e($r['room_name']) ?></td>
        <td><?= e($r['group_name']) ?></td>

        <td class="d-flex gap-2 flex-wrap">
          <a class="btn btn-sm btn-outline-light"
             href="/edt/admin/timetable_edit.php?id=<?= (int)$r['id'] ?>&week=<?= e(urlencode($week)) ?>&filiere_id=<?= (int)$filiere_id ?>&niveau_id=<?= (int)$niveau_id ?>">
             Modifier
          </a>

          <a class="btn btn-sm btn-outline-light"
             onclick="return confirm('Supprimer cette séance ?')"
             href="/edt/admin/timetable_delete.php?id=<?= (int)$r['id'] ?>&week=<?= e(urlencode($week)) ?>&filiere_id=<?= (int)$filiere_id ?>&niveau_id=<?= (int)$niveau_id ?>">
             Supprimer
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
  <?php endforeach; ?>

<?php endif; ?>
</tbody>

    </table>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", ()=>{
  // table search
  if(window.UI) UI.tableSearch("#q", "#t");

  // show/hide group for TP
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
