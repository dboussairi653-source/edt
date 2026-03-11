<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/helpers.php";
require_role(['admin']);

$id = (int)($_GET['id'] ?? 0);

// pour revenir au bon filtre
$week = $_GET['week'] ?? date('Y-m-d');
$filiere_id = (int)($_GET['filiere_id'] ?? 0);
$niveau_id  = (int)($_GET['niveau_id'] ?? 0);

if ($id > 0) {
  $pdo->prepare("DELETE FROM timetable_entries WHERE id=?")->execute([$id]);
}

redirect("/edt/admin/timetable.php?week=" . urlencode($week) . "&filiere_id=" . (int)$filiere_id . "&niveau_id=" . (int)$niveau_id);
