<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";

require_role(['teacher']);

$u = current_user();
$teacher_id = (int)($u['id'] ?? 0);

// Semaine (lundi) par défaut = semaine actuelle
$week = $_GET['week'] ?? date('Y-m-d');
$week_start = week_start_from_date($week);

$st = $pdo->prepare("
  SELECT te.*,
         m.titre AS module_titre,
         r.nom AS room_name,
         COALESCE(g.nom,'(commun)') AS group_name
  FROM timetable_entries te
  JOIN modules m ON m.id = te.module_id
  JOIN rooms r ON r.id = te.room_id
  LEFT JOIN groupes g ON g.id = te.group_id
  WHERE te.week_start = ?
    AND te.teacher_id = ?
  ORDER BY te.day_of_week, te.start_time
");
$st->execute([$week_start, $teacher_id]);
$rows = $st->fetchAll();

$title = "Impression EDT";
require __DIR__ . "/../includes/header.php";
?>

<style>
@media print {
  .no-print { display:none !important; }
  body { background: #fff !important; }
  .card { box-shadow:none !important; border:1px solid #ddd !important; }
}
</style>

<div class="no-print d-flex justify-content-between align-items-center mb-3">
  <div>
    <h4 class="mb-0">Emploi du temps (Prof)</h4>
    <div class="text-muted">Semaine du lundi : <strong><?= e($week_start) ?></strong></div>
  </div>
  <button class="btn btn-dark" onclick="window.print()">Imprimer</button>
</div>

<div class="card p-4">
  <?php if(!$rows): ?>
    <div class="alert alert-info mb-0">Aucune séance trouvée pour cette semaine.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Jour</th>
            <th>Heure</th>
            <th>Type</th>
            <th>Module</th>
            <th>Salle</th>
            <th>Groupe</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?= e(day_name((int)$r['day_of_week'])) ?></td>
              <td><?= e(substr((string)$r['start_time'],0,5)) ?>-<?= e(substr((string)$r['end_time'],0,5)) ?></td>
              <td><?= e(strtoupper((string)$r['kind'])) ?></td>
              <td><?= e($r['module_titre']) ?></td>
              <td><?= e($r['room_name']) ?></td>
              <td><?= e($r['group_name']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . "/../includes/footer.php"; ?>
