<?php
// includes/header.php
declare(strict_types=1);
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/helpers.php";
$user = current_user();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= e($title ?? "EDT") ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="/edt/assets/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container">
    <a class="navbar-brand" href="/edt/index.php">EDT</a>
    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if ($user): ?>
        <span class="text-white-50 small"><?= e($user['nom']) ?> (<?= e($user['role']) ?>)</span>
        <a class="btn btn-outline-light btn-sm" href="/edt/logout.php">Déconnexion</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="container py-4">
