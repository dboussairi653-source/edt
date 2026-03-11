<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// DELETE
if ($action === 'delete' && $id > 0) {
  $pdo->prepare("DELETE FROM rooms WHERE id=?")->execute([$id]);
  redirect("/edt/admin/rooms.php");
}

$err = "";

// ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nom = trim($_POST['nom'] ?? '');
  $capacite = (int)($_POST['capacite'] ?? 0);
  $type = $_POST['type'] ?? 'cours';

  $allowed = ['cours','td','tp'];
  if (!in_array($type, $allowed, true)) $type = 'cours';

  if ($nom === '' || $capacite <= 0) {
    $err = "Nom ou capacité invalide.";
  } else {
    if ($action === 'add') {
      $pdo->prepare("INSERT INTO rooms(nom, capacite, type) VALUES (?,?,?)")
          ->execute([$nom, $capacite, $type]);
      redirect("/edt/admin/rooms.php");
    }
    if ($action === 'edit' && $id > 0) {
      $pdo->prepare("UPDATE rooms SET nom=?, capacite=?, type=? WHERE id=?")
          ->execute([$nom, $capacite, $type, $id]);
      redirect("/edt/admin/rooms.php");
    }
  }
}

$title = "Salles";
require __DIR__ . "/../includes/header.php";

// Pré-remplir en edit
$row = ['nom' => '', 'capacite' => 30, 'type' => 'cours'];
if ($action === 'edit' && $id > 0) {
  $st = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
  $st->execute([$id]);
  $row = $st->fetch() ?: $row;
}

function type_label(string $t): string {
  return match($t){
    'tp' => 'TP',
    'td' => 'TD',
    default => 'Cours',
  };
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Salles</h4>
  <a class="btn btn-dark" href="/edt/admin/rooms.php?action=add">+ Ajouter</a>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= e($err) ?></div>
<?php endif; ?>

<?php if ($action === 'add' || ($action === 'edit' && $id > 0)): ?>
  <form method="post" class="card p-4 row g-3 mb-3">
    <div class="col-md-6">
      <label class="form-label">Nom</label>
      <input name="nom" class="form-control" value="<?= e($row['nom']) ?>" placeholder="ex: Salle A1" required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Capacité</label>
      <input name="capacite" type="number" min="1" class="form-control" value="<?= (int)$row['capacite'] ?>" required>
    </div>

    <div class="col-md-3">
      <label class="form-label">Type</label>
      <select name="type" class="form-select" required>
        <option value="cours" <?= ($row['type']==='cours')?'selected':'' ?>>Cours</option>
        <option value="td" <?= ($row['type']==='td')?'selected':'' ?>>TD</option>
        <option value="tp" <?= ($row['type']==='tp')?'selected':'' ?>>TP</option>
      </select>
    </div>

    <div class="col-12 d-flex gap-2">
      <button class="btn btn-success">Enregistrer</button>
      <a class="btn btn-outline-secondary" href="/edt/admin/rooms.php">Retour</a>
    </div>
  </form>
<?php endif; ?>

<?php
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY nom")->fetchAll();
?>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          <th>Nom</th>
          <th>Type</th>
          <th>Capacité</th>
          <th style="width:220px;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rooms as $r): ?>
          <tr>
            <td><?= e($r['nom']) ?></td>
            <td><?= e(type_label((string)$r['type'])) ?></td>
            <td><?= (int)$r['capacite'] ?></td>
            <td class="d-flex gap-2 flex-wrap">
              <a class="btn btn-sm btn-outline-dark"
                 href="/edt/admin/rooms.php?action=edit&id=<?= (int)$r['id'] ?>">Modifier</a>

              <a class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Supprimer cette salle ?')"
                 href="/edt/admin/rooms.php?action=delete&id=<?= (int)$r['id'] ?>">Supprimer</a>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (!$rooms): ?>
          <tr><td colspan="4" class="text-center text-muted py-4">Aucune salle.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . "/../includes/footer.php"; ?>
