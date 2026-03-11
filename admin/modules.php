<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

$err = "";
$msg = "";

// Listes
$niveaux = $pdo->query("
  SELECT n.id, CONCAT(f.nom,' / ',n.nom) AS label
  FROM niveaux n
  JOIN filieres f ON f.id=n.filiere_id
  ORDER BY f.nom, n.nom
")->fetchAll();

$teachers = $pdo->query("
  SELECT id, nom, email
  FROM users
  WHERE role='teacher'
  ORDER BY nom
")->fetchAll();

function module_label(array $m): string {
  return (string)($m['titre'] ?? '');
}

// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Affectation profs au module
  if ($action === 'assign' && $id > 0) {
    $teacher_ids = $_POST['teacher_ids'] ?? [];

    $pdo->prepare("DELETE FROM module_teachers WHERE module_id=?")->execute([$id]);

    $ins = $pdo->prepare("INSERT INTO module_teachers(module_id, teacher_id) VALUES (?,?)");
    foreach ($teacher_ids as $tid) {
      $ins->execute([$id, (int)$tid]);
    }

    $msg = "Affectation des professeurs enregistrée.";
    $action = 'list';
  }

  // CRUD module (SANS CODE)
  else {
    $titre = trim($_POST['titre'] ?? '');
    $niveau_id = (int)($_POST['niveau_id'] ?? 0);

    if ($titre === '' || $niveau_id <= 0) {
      $err = "Champs invalides (titre, niveau).";
    } else {
      if ($action === 'add') {
        $pdo->prepare("INSERT INTO modules(titre, niveau_id) VALUES (?,?)")
            ->execute([$titre, $niveau_id]);
        $msg = "Module ajouté.";
        $action = 'list';
      } elseif ($action === 'edit' && $id > 0) {
        $pdo->prepare("UPDATE modules SET titre=?, niveau_id=? WHERE id=?")
            ->execute([$titre, $niveau_id, $id]);
        $msg = "Module modifié.";
        $action = 'list';
      }
    }
  }
}

// DELETE
if ($action === 'delete' && $id > 0) {
  $pdo->prepare("DELETE FROM modules WHERE id=?")->execute([$id]);
  redirect("/edt/admin/modules.php");
}

$title = "Modules";
require_once __DIR__ . "/../includes/header.php";

if ($msg) echo '<div class="alert alert-success">'.e($msg).'</div>';
if ($err) echo '<div class="alert alert-danger">'.e($err).'</div>';

// ============ FORM ADD/EDIT ============
if ($action === 'add' || ($action === 'edit' && $id > 0)) {

  $row = ['titre' => '', 'niveau_id' => ($niveaux[0]['id'] ?? 0)];

  if ($action === 'edit') {
    $st = $pdo->prepare("SELECT * FROM modules WHERE id=?");
    $st->execute([$id]);
    $row = $st->fetch() ?: $row;
  }
?>
  <div class="card p-4">
    <h5 class="mb-3"><?= $action === 'add' ? 'Ajouter' : 'Modifier' ?> un module</h5>
    <form method="post" class="row g-3">

      <div class="col-md-8">
        <label class="form-label">Titre</label>
        <input class="form-control" name="titre" value="<?= e($row['titre']) ?>" required>
      </div>

      <div class="col-md-4">
        <label class="form-label">Niveau</label>
        <select class="form-select" name="niveau_id" required>
          <?php foreach ($niveaux as $n): ?>
            <option value="<?= (int)$n['id'] ?>" <?= ((int)$row['niveau_id'] === (int)$n['id']) ? 'selected' : '' ?>>
              <?= e($n['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-dark">Enregistrer</button>
        <a class="btn btn-outline-secondary" href="/edt/admin/modules.php">Retour</a>
      </div>
    </form>
  </div>

<?php
  require_once __DIR__ . "/../includes/footer.php";
  exit;
}

// ============ ASSIGN TEACHERS ============
if ($action === 'assign' && $id > 0) {

  $st = $pdo->prepare("SELECT * FROM modules WHERE id=?");
  $st->execute([$id]);
  $m = $st->fetch();
  if (!$m) exit("Module introuvable.");

  $assigned = $pdo->prepare("SELECT teacher_id FROM module_teachers WHERE module_id=?");
  $assigned->execute([$id]);
  $set = array_flip(array_map(fn($x) => (string)$x['teacher_id'], $assigned->fetchAll()));
?>
  <div class="card p-4">
    <h5 class="mb-3">Affecter professeurs au module</h5>
    <div class="text-muted mb-3">
      <b><?= e(module_label($m)) ?></b>
    </div>

    <form method="post">
      <div class="row g-2">
        <?php foreach ($teachers as $t): ?>
          <div class="col-md-4">
            <label class="form-check-label d-flex align-items-center gap-2">
              <input class="form-check-input" type="checkbox" name="teacher_ids[]"
                     value="<?= (int)$t['id'] ?>" <?= isset($set[(string)$t['id']]) ? 'checked' : '' ?>>
              <span><?= e($t['nom']) ?> <span class="text-muted small">(<?= e($t['email']) ?>)</span></span>
            </label>
          </div>
        <?php endforeach; ?>

        <?php if (!$teachers): ?>
          <div class="col-12 text-muted">Aucun professeur trouvé. Ajoute d’abord des utilisateurs (role=teacher).</div>
        <?php endif; ?>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-dark">Enregistrer</button>
        <a class="btn btn-outline-secondary" href="/edt/admin/modules.php">Retour</a>
      </div>
    </form>
  </div>

<?php
  require_once __DIR__ . "/../includes/footer.php";
  exit;
}

// ============ LIST ============
$rows = $pdo->query("
  SELECT m.id, m.titre,
         CONCAT(f.nom,' / ',n.nom) AS niveau,
         GROUP_CONCAT(u.nom SEPARATOR ', ') AS profs
  FROM modules m
  JOIN niveaux n ON n.id=m.niveau_id
  JOIN filieres f ON f.id=n.filiere_id
  LEFT JOIN module_teachers mt ON mt.module_id = m.id
  LEFT JOIN users u ON u.id = mt.teacher_id
  GROUP BY m.id
  ORDER BY f.nom, n.nom, m.titre
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Modules</h4>
  <a class="btn btn-dark" href="/edt/admin/modules.php?action=add">+ Ajouter</a>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Niveau</th>
          <th>Titre</th>
          <th>Profs</th>
          <th style="width:280px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e($r['niveau']) ?></td>
            <td><?= e($r['titre']) ?></td>
           <td>
<?php
if (!empty($r['profs'])) {
  $names = explode(', ', $r['profs']);
  foreach ($names as $n) {
    echo '<span class="badge bg-primary me-1">'.e($n).'</span>';
  }
} else {
  echo '<span class="text-muted small">Aucun</span>';
}
?>
</td>


            <td class="d-flex gap-2 flex-wrap">
              <a class="btn btn-sm btn-outline-primary"
                 href="/edt/admin/modules.php?action=assign&id=<?= (int)$r['id'] ?>">
                 Affecter profs
              </a>
              <a class="btn btn-sm btn-outline-dark"
                 href="/edt/admin/modules.php?action=edit&id=<?= (int)$r['id'] ?>">
                 Modifier
              </a>
              <a class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Supprimer ce module ?')"
                 href="/edt/admin/modules.php?action=delete&id=<?= (int)$r['id'] ?>">
                 Supprimer
              </a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$rows): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">Aucun module.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
