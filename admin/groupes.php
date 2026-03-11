<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

// Liste filières
$filieres = $pdo->query("SELECT id, nom FROM filieres ORDER BY nom")->fetchAll();

// Filière sélectionnée (par défaut: première)
$selected_filiere_id = (int)($_GET['filiere_id'] ?? 0);
if ($selected_filiere_id <= 0 && !empty($filieres)) {
  $selected_filiere_id = (int)$filieres[0]['id'];
}

// Niveaux filtrés par filière
$niveaux = [];
if ($selected_filiere_id > 0) {
  $st = $pdo->prepare("SELECT id, nom FROM niveaux WHERE filiere_id=? ORDER BY nom");
  $st->execute([$selected_filiere_id]);
  $niveaux = $st->fetchAll();
}

// Ajouter groupe
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $niveau_id = (int)($_POST['niveau_id'] ?? 0);
  $nom = trim($_POST['nom'] ?? '');

  if ($niveau_id > 0 && $nom !== '') {
    $pdo->prepare("INSERT INTO groupes(niveau_id, nom) VALUES (?,?)")->execute([$niveau_id, $nom]);
  }

  redirect("/edt/admin/groupes.php?filiere_id=" . urlencode((string)$selected_filiere_id));
}

/* ========= Liste groupes ULTRA OPTIMALE DYNAMIQUE =========
   1 ligne par filière
   colonnes = niveaux dynamiques (S1..S4..)
   cellule = badges des groupes présents
*/
$rows = $pdo->query("
  SELECT f.nom AS filiere, n.nom AS niveau, g.nom AS groupe
  FROM groupes g
  JOIN niveaux n ON n.id = g.niveau_id
  JOIN filieres f ON f.id = n.filiere_id
  ORDER BY f.nom, n.nom, g.nom
")->fetchAll();

$levels_set = [];  // niveaux trouvés
$grid = [];        // $grid[filiere][niveau] = ['G1'=>true, ...]
foreach ($rows as $r) {
  $f = $r['filiere'];
  $n = $r['niveau'];
  $g = $r['groupe'];

  $levels_set[$n] = true;

  if (!isset($grid[$f])) $grid[$f] = [];
  if (!isset($grid[$f][$n])) $grid[$f][$n] = [];
  $grid[$f][$n][$g] = true;
}

// Colonnes niveaux dynamiques triées (ordre naturel S1,S2,S3...S10)
$levels = array_keys($levels_set);
usort($levels, function($a, $b) {
  return (int)preg_replace('/\D/', '', $a) <=> (int)preg_replace('/\D/', '', $b);
});

$title = "Groupes";
require __DIR__ . "/../includes/header.php";
?>

<div class="card p-3 mb-3">
  <h5 class="mb-3">Ajouter un groupe</h5>

  <form method="get" class="row g-2 mb-3">
    <div class="col-md-6">
      <label class="form-label">Filière</label>
      <select name="filiere_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($filieres as $f): ?>
          <option value="<?= (int)$f['id'] ?>" <?= ((int)$f['id'] === $selected_filiere_id) ? 'selected' : '' ?>>
            <?= e($f['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <form method="post" class="row g-2">
    <div class="col-md-5">
      <label class="form-label">Niveau</label>
      <select name="niveau_id" class="form-select" required>
        <option value="">-- choisir --</option>
        <?php foreach ($niveaux as $n): ?>
          <option value="<?= (int)$n['id'] ?>"><?= e($n['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($niveaux)): ?>
        <div class="text-danger small mt-1">Aucun niveau pour cette filière. Ajoute d’abord des niveaux.</div>
      <?php endif; ?>
    </div>

    <div class="col-md-5">
      <label class="form-label">Nom du groupe</label>
      <input name="nom" class="form-control" placeholder="ex: G1" required>
    </div>

    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-dark w-100" <?= empty($niveaux) ? 'disabled' : '' ?>>Ajouter</button>
    </div>
  </form>
</div>

<div class="card p-3">
  <h5 class="mb-3">Liste des groupes</h5>
  <div class="table-responsive">
    <table class="table table-bordered mb-0">
      <thead>
        <tr>
          <th>Filière</th>
          <?php foreach ($levels as $lv): ?>
            <th><?= e($lv) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($grid)): ?>
          <tr><td colspan="<?= 1 + count($levels) ?>" class="text-center text-muted py-4">Aucun groupe.</td></tr>
        <?php else: ?>
          <?php foreach ($grid as $filiere => $byLevel): ?>
            <tr>
              <td><?= e($filiere) ?></td>

              <?php foreach ($levels as $lv): ?>
                <td>
                  <?php if (!empty($byLevel[$lv])): ?>
                    <?php foreach (array_keys($byLevel[$lv]) as $gname): ?>
                      <span class="badge bg-secondary me-1"><?= e($gname) ?></span>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>

            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . "/../includes/footer.php"; ?>
