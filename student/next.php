<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['student']);

$user = current_user();
$group_id = (int)($user['groupe_id'] ?? 0);

$title = "Prochaine séance";
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
$now_day = (int)date('N');
$now_time = date('H:i:s');

$next = null;
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
      AND (
        te.day_of_week > ?
        OR (te.day_of_week = ? AND te.start_time >= ?)
      )
    ORDER BY te.day_of_week, te.start_time
    LIMIT 1
  ");
  $st->execute([$week_start, $niveau_id, $group_id, $now_day, $now_day, $now_time]);
  $next = $st->fetch();
}
?>

<div class="card p-4" data-aos="fade-up">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4 class="mb-1">Prochaine séance</h4>
      <div class="text-muted">Semaine du lundi : <strong><?= e($week_start) ?></strong></div>
    </div>
    <a class="btn btn-outline-light" href="/edt/student/dashboard.php">Retour</a>
  </div>

  <hr class="my-3">

  <?php if($err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
  <?php elseif($next): ?>
    <div class="p-3 rounded" style="border:1px solid var(--border);">
      <div class="fw-semibold fs-5">
        <?= e(day_name((int)$next['day_of_week'])) ?>
        <?= e(substr((string)$next['start_time'],0,5)) ?>–<?= e(substr((string)$next['end_time'],0,5)) ?>
        • <?= e(strtoupper((string)$next['kind'])) ?>
      </div>
      <div class="mt-2">
        <div><strong>Module :</strong> <?= e($next['module_titre']) ?></div>
        <div><strong>Prof :</strong> <?= e($next['teacher_name']) ?></div>
        <div><strong>Salle :</strong> <?= e($next['room_name']) ?></div>
        <div><strong>Groupe :</strong> <?= e($next['group_name']) ?></div>
        <?php if(!empty($next['notes'])): ?>
          <div class="mt-2 text-muted"><strong>Note :</strong> <?= e($next['notes']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="text-muted">Aucune séance restante cette semaine.</div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
A