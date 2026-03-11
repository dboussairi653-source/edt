<?php
// includes/header.php
declare(strict_types=1);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/helpers.php";
$user = current_user();

// ✅ détecter page login (pour charger le style/FX uniquement là)
$is_login = (($body_class ?? '') === 'page-login');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= e($title ?? "EDT") ?></title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Icons + animations libs -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">

  <!-- Your CSS -->
  <link rel="stylesheet" href="/edt/assets/css/app.css?v=9999">
  <link rel="stylesheet" href="/edt/assets/css/dashboard.css?v=3">
  <link rel="stylesheet" href="/edt/assets/css/animations.css?v=3">

  <!-- ✅ Login (only on login page) -->
  <?php if ($is_login): ?>
    <link rel="stylesheet" href="/edt/assets/css/login.css?v=1">
  <?php endif; ?>

  <!-- Apply saved theme BEFORE page loads (no flicker, no light reset) -->
  <script>
    (function(){
      const saved = localStorage.getItem("theme");
      if (saved === "light" || saved === "dark") {
        document.documentElement.setAttribute("data-theme", saved);
      } else {
        document.documentElement.setAttribute("data-theme", "dark"); // default
      }
    })();
  </script>

  <!-- Theme JS -->
  <script src="/edt/assets/js/theme.js" defer></script>

  <!-- Libs -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js" defer></script>

  <!-- Your JS -->
  <script src="/edt/assets/js/ui.js" defer></script>
  <script src="/edt/assets/js/fx.js" defer></script>
  <script src="/edt/assets/js/charts.js" defer></script>
  <script src="/edt/assets/js/app.js" defer></script>

  <!-- ✅ Login FX (only on login page) -->
  <?php if ($is_login): ?>
    <script src="/edt/assets/js/login_fx.js?v=1" defer></script>
  <?php endif; ?>
</head>

<body class="<?= e($body_class ?? '') ?>">
<nav class="navbar navbar-expand-lg navbar-dark px-3 nav-glass sticky-top">
  <div class="container">
    <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="/edt/index.php">
      <i class="fa-solid fa-calendar-days"></i>EDT
    </a>

    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if ($user): ?>
        <span class="badge badge-soft">
          <i class="fa-solid fa-user me-1"></i>
          <?= e($user['nom']) ?> (<?= e($user['role']) ?>)
        </span>

        <a class="btn btn-outline-light btn-sm" href="/edt/logout.php">
          <i class="fa-solid fa-right-from-bracket me-1"></i>Déconnexion
        </a>
      <?php endif; ?>

      <!-- Theme toggle -->
      <button class="btn btn-outline-light btn-sm"
              type="button"
              data-theme-toggle
              title="Changer thème">
        <i class="fa-solid fa-moon" data-theme-icon></i>
      </button>

    </div>
  </div>
</nav>

<main class="container py-4">
  <?php if (!isset($hide_back_button)): ?>
  <div class="mb-3">
    <button onclick="goBack()" class="btn btn-back">
      <i class="fa-solid fa-arrow-left me-2"></i>
      Retour
    </button>
  </div>
<?php endif; ?>

