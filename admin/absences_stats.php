<?php
declare(strict_types=1);
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/helpers.php";
require_role(['admin']);

$THRESHOLD = 3; // seuil d'alerte (non justifiées)

$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

// filtres filière/niveau
$filieres = $pdo->query("SELECT id, nom FROM filieres ORDER BY nom")->fetchAll();
$filiere_id = (int)($_GET['filiere_id'] ?? 0);
if ($filiere_id<=0 && $filieres) $filiere_id = (int)$filieres[0]['id'];

$niveaux = [];
if ($filiere_id>0) {
  $st = $pdo->prepare("SELECT id, nom FROM niveaux WHERE filiere_id=? ORDER BY nom");
  $st->execute([$filiere_id]);
  $niveaux = $st->fetchAll();
}

$niveau_id = (int)($_GET['niveau_id'] ?? 0);
if ($niveau_id<=0 && $niveaux) $niveau_id = (int)$niveaux[0]['id'];

// stats (semaine sélectionnée + niveau)
$stats = [];
if ($niveau_id>0) {
  $st = $pdo->prepare("
    SELECT
      u.id AS student_id,
      u.nom AS student_name,
      g.nom AS group_name,
      SUM(CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END) AS total_abs,
      SUM(CASE WHEN a.status='non_justifie' THEN 1 ELSE 0 END) AS non_justifie,
      SUM(CASE WHEN a.status='justifie' THEN 1 ELSE 0 END) AS justifie
    FROM users u
    JOIN groupes g ON g.id=u.groupe_id
    LEFT JOIN absences a ON a.user_id=u.id
    LEFT JOIN timetable_entries te ON te.id=a.timetable_entry_id
    WHERE u.role='student'
      AND g.niveau_id=?
      AND (te.week_start=? OR te.week_start IS NULL) -- garde étudiants même sans absence
    GROUP BY u.id, u.nom, g.nom
    ORDER BY g.nom, u.nom
  ");
  $st->execute([$niveau_id, $week_start]);
  $stats = $st->fetchAll();
}

$alerts = array_values(array_filter($stats, fn($r)=>(int)$r['non_justifie'] >= $THRESHOLD));

$title="Stats absences";
require __DIR__."/../includes/header.php";
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0">Statistiques absences + alertes</h4>
    <div class="text-muted small">Semaine (lundi) : <b><?= e($week_start) ?></b> — Seuil alerte : <b><?= (int)$THRESHOLD ?></b> non justifiées</div>
  </div>

  <form method="get" class="d-flex gap-2">
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
    <button class="btn btn-outline-dark">OK</button>
  </form>
</div>

<div class="row g-3">
  <div class="col-12">
    <div class="card p-3">
      <h5 class="mb-3">Alertes (≥ <?= (int)$THRESHOLD ?> non justifiées)</h5>
      <div class="table-responsive">
        <table class="table table-bordered mb-0">
          <thead>
            <tr>
              <th>Étudiant</th><th>Groupe</th><th>Total</th><th>Non justifiées</th><th>Justifiées</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$alerts): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Aucune alerte.</td></tr>
            <?php else: foreach($alerts as $r): ?>
              <tr>
                <td><?= e($r['student_name']) ?></td>
                <td><?= e($r['group_name']) ?></td>
                <td><?= (int)$r['total_abs'] ?></td>
                <td><span class="badge bg-danger"><?= (int)$r['non_justifie'] ?></span></td>
                <td><?= (int)$r['justifie'] ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card p-3">
      <h5 class="mb-3">Statistiques (semaine + niveau)</h5>
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>Étudiant</th><th>Groupe</th><th>Total</th><th>Non justifiées</th><th>Justifiées</th><th>Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$stats): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Aucune donnée.</td></tr>
            <?php else: foreach($stats as $r): ?>
              <?php $danger = ((int)$r['non_justifie'] >= $THRESHOLD); ?>
              <tr>
                <td><?= e($r['student_name']) ?></td>
                <td><?= e($r['group_name']) ?></td>
                <td><?= (int)$r['total_abs'] ?></td>
                <td><?= (int)$r['non_justifie'] ?></td>
                <td><?= (int)$r['justifie'] ?></td>
                <td><?= $danger ? '<span class="badge bg-danger">ALERTE</span>' : '<span class="badge bg-success">OK</span>' ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-3 text-muted small">
        Astuce : pour régler le seuil, change <code>$THRESHOLD</code> au début du fichier.
      </div>
    </div>
  </div>
</div>

<?php require __DIR__."/../includes/footer.php"; ?>
