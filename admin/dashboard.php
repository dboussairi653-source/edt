<?php
declare(strict_types=1);
require_once __DIR__ . "/../includes/auth.php";
require_role(['admin']);
$title = "Dashboard Admin";
require_once __DIR__ . "/../includes/header.php";
?>

<div class="page-title mb-4" data-aos="fade-up">
  <div>
    <h4>Administration</h4>
    <div class="page-sub">Tableau de bord — gestion avancée</div>
  </div>
</div>

<div class="action-grid">
  <a href="/edt/admin/users.php" class="card action-card" data-aos="fade-up">
    <div class="top">
      <div class="title">
        <span class="chip"><i class="fa-solid fa-users"></i></span>
        Utilisateurs
      </div>
      <i class="fa-solid fa-arrow-right"></i>
    </div>
    <p class="desc">Étudiants & enseignants</p>
  </a>

  <a href="/edt/admin/filieres.php" class="card action-card" data-aos="fade-up" data-aos-delay="40">
    <div class="top">
      <div class="title">
        <span class="chip"><i class="fa-solid fa-diagram-project"></i></span>
        Filières
      </div>
      <i class="fa-solid fa-arrow-right"></i>
    </div>
    <p class="desc">Gestion des filières</p>
  </a>

  <a href="/edt/admin/niveaux.php" class="card action-card" data-aos="fade-up" data-aos-delay="80">
    <div class="top">
      <div class="title">
        <span class="chip"><i class="fa-solid fa-layer-group"></i></span>
        Niveaux
      </div>
      <i class="fa-solid fa-arrow-right"></i>
    </div>
    <p class="desc">S1, S2, S3…</p>
  </a>

  <a href="/edt/admin/groupes.php" class="card action-card" data-aos="fade-up" data-aos-delay="120">
    <div class="top">
      <div class="title">
        <span class="chip"><i class="fa-solid fa-people-group"></i></span>
        Groupes
      </div>
      <i class="fa-solid fa-arrow-right"></i>
    </div>
    <p class="desc">Organisation des groupes</p>
  </a>

  <a href="/edt/admin/modules.php" class="card action-card" data-aos="fade-up" data-aos-delay="160">
    <div class="top">
      <div class="title">
        <span class="chip"><i class="fa-solid fa-book"></i></span>
        Modules
      </div>
      <i class="fa-solid fa-arrow-right"></i>
    </div>
    <p class="desc">Cours & matières</p>
  </a>

  <a href="/edt/admin/rooms.php" class="card action-card" data-aos="fade-up" data-aos-delay="200">
    <div class="top">
      <div class="title">
        <span class="chip"><i class="fa-solid fa-door-open"></i></span>
        Salles
      </div>
      <i class="fa-solid fa-arrow-right"></i>
    </div>
    <p class="desc">Cours / TD / TP</p>
  </a>

  <a href="/edt/admin/timetable.php" class="card action-card glow-primary" data-aos="zoom-in" data-aos-delay="240">
    <div class="top">
      <div class="title">
        <span class="chip"><i class="fa-solid fa-calendar-days"></i></span>
        Emploi du temps
      </div>
      <i class="fa-solid fa-arrow-right"></i>
    </div>
    <p class="desc">Planification + conflits</p>
  </a>

  <!-- ✅ AJOUT : Absences -->
  <a href="/edt/admin/absences.php" class="card action-card" data-aos="zoom-in" data-aos-delay="280">
    <div class="top">
      <div class="title">
        <span class="chip"><i class="fa-solid fa-user-check"></i></span>
        Absences
      </div>
      <i class="fa-solid fa-arrow-right"></i>
    </div>
    <p class="desc">Marquer & justifier</p>
  </a>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
