<?php
declare(strict_types=1);
require_once __DIR__."/../includes/auth.php";
require_once __DIR__."/../config/db.php";
require_once __DIR__."/../includes/helpers.php";
require_role(['student']);

$u = current_user();
$uid = (int)$u['id'];

$rows = $pdo->prepare("
  SELECT a.*,
         te.week_start, te.day_of_week, te.start_time, te.end_time, te.kind,
         m.code, m.titre
  FROM absences a
  JOIN timetable_entries te ON te.id = a.timetable_entry_id
  JOIN modules m ON m.id = te.module_id
  WHERE a.user_id=?
  ORDER BY te.week_start DESC, te.day_of_week, te.start_time
");
$rows->execute([$uid]);
$abs = $rows->fetchAll();

$just = array_values(array_filter($abs, fn($x)=>$x['status']==='justifie'));
$non  = array_values(array_filter($abs, fn($x)=>$x['status']==='non_justifie'));

$title = "Mes absences";
require __DIR__."/../includes/header.php";
?>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card p-3">
      <h5 class="mb-2">Non justifiées (<?= count($non) ?>)</h5>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Semaine</th><th>Jour</th><th>Heure</th><th>Module</th><th>Type</th></tr></thead>
          <tbody>
            <?php if(!$non): ?>
              <tr><td colspan="5" class="text-muted text-center py-3">Aucune</td></tr>
            <?php else: foreach($non as $a): ?>
              <tr>
                <td><?= e($a['week_start']) ?></td>
                <td><?= e(day_name((int)$a['day_of_week'])) ?></td>
                <td><?= e(substr($a['start_time'],0,5))."-".e(substr($a['end_time'],0,5)) ?></td>
                <td><?= e($a['code']." - ".$a['titre']) ?></td>
                <td><?= e(strtoupper($a['kind'])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card p-3">
      <h5 class="mb-2">Justifiées (<?= count($just) ?>)</h5>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead><tr><th>Semaine</th><th>Jour</th><th>Heure</th><th>Module</th><th>Motif</th></tr></thead>
          <tbody>
            <?php if(!$just): ?>
              <tr><td colspan="5" class="text-muted text-center py-3">Aucune</td></tr>
            <?php else: foreach($just as $a): ?>
              <tr>
                <td><?= e($a['week_start']) ?></td>
                <td><?= e(day_name((int)$a['day_of_week'])) ?></td>
                <td><?= e(substr($a['start_time'],0,5))."-".e(substr($a['end_time'],0,5)) ?></td>
                <td><?= e($a['code']." - ".$a['titre']) ?></td>
                <td><?= e((string)$a['reason']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__."/../includes/footer.php"; ?>
