<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

$msg = "";

// Ajouter niveau
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $filiere_id = (int)($_POST['filiere_id'] ?? 0);
  $nom = trim($_POST['nom'] ?? '');

  if ($filiere_id > 0 && $nom !== '') {
    $pdo->prepare("INSERT INTO niveaux(filiere_id, nom) VALUES (?,?)")->execute([$filiere_id, $nom]);
    $msg = "Niveau ajouté.";
  }
}

// Supprimer niveau
if (isset($_GET['del'])) {
  $id = (int)$_GET['del'];
  if ($id > 0) {
    $pdo->prepare("DELETE FROM niveaux WHERE id=?")->execute([$id]);
    $msg = "Niveau supprimé.";
  }
}

$filieres = $pdo->query("SELECT id, nom FROM filieres ORDER BY nom")->fetchAll();

// Récupérer niveaux groupés par filière
$rows = $pdo->query("
  SELECT n.id, n.nom AS niveau, f.id AS filiere_id, f.nom AS filiere
  FROM niveaux n
  JOIN filieres f ON f.id = n.filiere_id
  ORDER BY f.nom, n.nom
")->fetchAll();

$map = []; // [filiere_id][S1]=['id'=>..,'niveau'=>..]
foreach ($rows as $r) {
  $map[(int)$r['filiere_id']][(string)$r['niveau']] = ['id' => (int)$r['id'], 'niveau' => $r['niveau']];
}

$title = "Niveaux";
require __DIR__ . "/../includes/header.php";
?>

<?php if ($msg): ?>
  <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>

<div class="card p-3 mb-3">
  <h5 class="mb-3">Ajouter un niveau</h5>
  <form method="post" class="row g-2">
    <div class="col-md-6">
      <label class="form-label">Filière</label>
      <select name="filiere_id" class="form-select" required>
        <option value="">-- choisir --</option>
        <?php foreach ($filieres as $f): ?>
          <option value="<?= (int)$f['id'] ?>"><?= e($f['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Niveau</label>
      <input name="nom" class="form-control" placeholder="ex: S1" required>
    </div>
    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-dark w-100">Ajouter</button>
    </div>
  </form>
</div>

<div class="card p-3">
  <h5 class="mb-3">Niveaux par filière</h5>
  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Filière</th>
          <th>S1</th>
          <th>S2</th>
          <th>S3</th>
          <th>S4</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($filieres as $f): 
          $fid = (int)$f['id'];
        ?>
          <tr>
            <td><?= e($f['nom']) ?></td>

            <?php foreach (['S1','S2','S3','S4'] as $sx): ?>
              <td>
                <?php if (isset($map[$fid][$sx])): ?>
                  <span class="me-2"><?= e($sx) ?></span>
                  <a class="text-danger" href="/edt/admin/niveaux.php?del=<?= (int)$map[$fid][$sx]['id'] ?>"
                     onclick="return confirm('Supprimer <?= e($sx) ?> ?')">🗑</a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>

          </tr>
        <?php endforeach; ?>

        <?php if (!$filieres): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">Aucune filière.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . "/../includes/footer.php"; ?>
