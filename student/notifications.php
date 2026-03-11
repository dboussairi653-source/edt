<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['student']);

$user = current_user();
$group_id = (int)($user['groupe_id'] ?? 0);

$title = "Notifications";
require_once __DIR__ . "/../includes/header.php";

$err = "";
$niveau_id = 0;

if ($group_id > 0) {
  $st = $pdo->prepare("SELECT niveau_id FROM groupes WHERE id=?");
  $st->execute([$group_id]);
  $niveau_id = (int)($st->fetchColumn() ?: 0);
}
if ($niveau_id <= 0) $err = "Ton compte n’est pas rattaché à un groupe/niveau.";

$today = date('Y-m-d');
$week_start = week_start_from_date($today);

$notifs = [];
if (!$err) {
  $st = $pdo->prepare("
    SELECT te.*,
           m.titre AS module_titre,
           u.nom AS teacher_name,
           r.nom AS room_name,
           COALESCE(g.nom,'(commun)') AS group_name
    FROM timetable_entries te
    JOIN modules m ON m.id = te.module_id
    JOIN users u   ON u.id = te.teacher_id
    JOIN rooms r   ON r.id = te.room_id
    LEFT JOIN groupes g ON g.id = te.group_id
    WHERE te.week_start = ?
      AND te.niveau_id = ?
      AND (te.group_id IS NULL OR te.group_id = ?)
      AND te.notes IS NOT NULL AND te.notes <> ''
    ORDER BY te.day_of_week, te.start_time
  ");
  $st->execute([$week_start, $niveau_id, $group_id]);
  $notifs = $st->fetchAll();
}
?>

<div class="card p-4" data-aos="fade-up">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4 class="mb-1">Notifications</h4>
      <div class="text-muted">Semaine du lundi : <strong><?= e($week_start) ?></strong></div>
    </div>
    <a class="btn btn-outline-light" href="/edt/student/dashboard.php">Retour</a>
  </div>

  <hr class="my-3">

  <?php if($err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
  <?php elseif(!$notifs): ?>
    <div class="text-muted">Aucune notification pour cette semaine.</div>
  <?php else: ?>
    <div class="vstack gap-2">
      <?php foreach($notifs as $n): ?>
        <div class="p-3 rounded" style="border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 88%, transparent);">
          <div class="small text-muted">
            <?= e(day_name((int)$n['day_of_week'])) ?>
            <?= e(substr((string)$n['start_time'],0,5)) ?>–<?= e(substr((string)$n['end_time'],0,5)) ?>
            • <?= e(strtoupper((string)$n['kind'])) ?>
            • <?= e($n['group_name']) ?>
          </div>
          <div class="fw-semibold"><?= e($n['module_titre']) ?></div>
          <div class="text-muted"><?= e($n['notes']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
