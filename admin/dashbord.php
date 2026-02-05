<?php
declare(strict_types=1);
require_once __DIR__ . "/../includes/auth.php";
require_role(['admin']);
$title = "Dashboard Admin";
require_once __DIR__ . "/../includes/header.php";
?>
<div class="row g-3">
  <div class="col-12">
    <div class="card p-4">
      <h4 class="mb-1">Administration</h4>
      <p class="text-muted mb-0">Gère utilisateurs, filières, modules, salles, et emplois du temps.</p>
    </div>
  </div>

  <div class="col-md-4"><a class="btn btn-dark w-100" href="/edt/admin/users.php">Utilisateurs</a></div>
  <div class="col-md-4"><a class="btn btn-dark w-100" href="/edt/admin/filieres.php">Filières</a></div>
  <div class="col-md-4"><a class="btn btn-dark w-100" href="/edt/admin/niveaux.php">Niveaux</a></div>
  <div class="col-md-4"><a class="btn btn-dark w-100" href="/edt/admin/groupes.php">Groupes</a></div>
  <div class="col-md-4"><a class="btn btn-dark w-100" href="/edt/admin/modules.php">Modules</a></div>
  <div class="col-md-4"><a class="btn btn-dark w-100" href="/edt/admin/rooms.php">Salles</a></div>
  <div class="col-md-12"><a class="btn btn-warning w-100" href="/edt/admin/timetable.php">Emploi du temps + Conflits</a></div>
</div>
<?php require_once __DIR__ . "/../includes/footer.php"; ?>
