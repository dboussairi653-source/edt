<?php
declare(strict_types=1);
require_once __DIR__ . "/../includes/auth.php";
require_role(['teacher']);
$title = "Espace Professeur";
require_once __DIR__ . "/../includes/header.php";
?>

<div class="card p-4" data-aos="fade-up">
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
      <h4 class="mb-1">Espace Professeur</h4>
      <p class="text-muted mb-0">
        Accédez rapidement à votre emploi du temps, gérez les absences et générez votre PDF.
      </p>
    </div>
  </div>

  <hr class="my-3">

  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-dark" href="/edt/teacher/timetable.php">
      <i class="fa-solid fa-calendar-week me-2"></i>Mon emploi du temps
    </a>

    <a class="btn btn-outline-light" href="/edt/teacher/absences.php">
      <i class="fa-solid fa-user-xmark me-2"></i>Absences (mes séances)
    </a>

    <a class="btn btn-outline-light" href="/edt/teacher/timetable_print.php">
      <i class="fa-solid fa-print me-2"></i>Imprimer EDT (PDF)
    </a>
  </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
