<?php
declare(strict_types=1);
require_once __DIR__ . "/../includes/auth.php";
require_role(['teacher']);
$title = "Dashboard Professeur";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="card p-4">
  <h4>Professeur</h4>
  <p class="text-muted mb-3">Consulte ton emploi du temps.</p>
  <a class="btn btn-dark" href="/edt/teacher/timetable.php">Mon emploi du temps</a>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
