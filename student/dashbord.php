<?php
declare(strict_types=1);
require_once __DIR__ . "/../includes/auth.php";
require_role(['student']);
$title = "Dashboard Étudiant";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="card p-4">
  <h4>Étudiant</h4>
  <p class="text-muted mb-3">Consulte l’emploi du temps de ton groupe.</p>
  <a class="btn btn-dark" href="/edt/student/timetable.php">Emploi du temps</a>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
