<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['student']);

$user = current_user();
$group_id = (int)($user['groupe_id'] ?? 0);

$title = "Dashboard Étudiant";
require_once __DIR__ . "/../includes/header.php";

$err = "";

// Récupérer niveau_id depuis le groupe
$niveau_id = 0;
if ($group_id > 0) {
  $st = $pdo->prepare("SELECT niveau_id FROM groupes WHERE id=?");
  $st->execute([$group_id]);
  $niveau_id = (int)($st->fetchColumn() ?: 0);
}
if ($niveau_id <= 0) {
  $err = "Ton compte n’est pas rattaché à un groupe/niveau.";
}

// semaine actuelle
$today = date('Y-m-d');
$week_start = week_start_from_date($today);
$now_day = (int)date('N'); // 1..7
$now_time = date('H:i:s');

// Prochaine séance
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

// Notifications (basées sur notes)
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
    LIMIT 5
  ");
  $st->execute([$week_start, $niveau_id, $group_id]);
  $notifs = $st->fetchAll();
}
?>

<div class="card p-4" data-aos="fade-up">
  <h4>Étudiant</h4>
  <p class="text-muted mb-3">Consulte l’emploi du temps de ton groupe.</p>

  <?php if($err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
  <?php endif; ?>

  <div class="d-flex gap-2 flex-wrap mb-4">
    <a class="btn btn-dark" href="/edt/student/timetable.php">Emploi du temps</a>
    <a class="btn btn-outline-light" href="/edt/student/absences.php">Mes absences</a>
  </div>

  <div class="row g-3">
    <!-- Prochaine séance -->
    <div class="col-md-6">
      <div class="card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="mb-0">Prochaine séance</h5>
          <a class="btn btn-sm btn-outline-light" href="/edt/student/next.php">Voir</a>
        </div>

        <div class="text-muted small mt-1">Basée sur ton groupe + séances communes.</div>

        <hr class="my-3">

        <?php if(!$err && $next): ?>
          <div>
            <div class="fw-semibold">
              <?= e(day_name((int)$next['day_of_week'])) ?>
              <?= e(substr((string)$next['start_time'],0,5)) ?>–<?= e(substr((string)$next['end_time'],0,5)) ?>
              • <?= e(strtoupper((string)$next['kind'])) ?>
            </div>
            <div class="mt-1">
              <?= e($next['module_titre']) ?>
              <span class="text-muted">— <?= e($next['teacher_name']) ?></span>
            </div>
            <div class="text-muted mt-1">
              Salle: <?= e($next['room_name']) ?> • Groupe: <?= e($next['group_name']) ?>
            </div>
          </div>
        <?php elseif(!$err): ?>
          <div class="text-muted">Aucune séance restante cette semaine.</div>
        <?php else: ?>
          <div class="text-muted">—</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Notifications -->
    <div class="col-md-6">
      <div class="card p-3 h-100">
        <div class="d-flex align-items-center justify-content-between">
          <h5 class="mb-0">Notifications</h5>
          <a class="btn btn-sm btn-outline-light" href="/edt/student/notifications.php">Voir</a>
        </div>

        <div class="text-muted small mt-1">Affiche les séances qui contiennent une note.</div>

        <hr class="my-3">

        <?php if(!$err && $notifs): ?>
          <div class="vstack gap-2">
            <?php foreach($notifs as $n): ?>
              <div class="p-2 rounded" style="border:1px solid var(--border); background: color-mix(in srgb, var(--surface) 88%, transparent);">
                <div class="small text-muted">
                  <?= e(day_name((int)$n['day_of_week'])) ?>
                  <?= e(substr((string)$n['start_time'],0,5)) ?>–<?= e(substr((string)$n['end_time'],0,5)) ?>
                  • <?= e(strtoupper((string)$n['kind'])) ?>
                  • <?= e($n['group_name']) ?>
                </div>
                <div class="fw-semibold"><?= e($n['module_titre']) ?></div>
                <div class="text-muted small"><?= e($n['notes']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php elseif(!$err): ?>
          <div class="text-muted">Aucune notification pour cette semaine.</div>
        <?php else: ?>
          <div class="text-muted">—</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
