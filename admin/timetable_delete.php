<?php
declare(strict_types=1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../config/db.php";
require_role(['admin']);

$id = (int)($_GET['id'] ?? 0);
$week = $_GET['week'] ?? date('Y-m-d');

if ($id > 0) {
  $stmt = $pdo->prepare("DELETE FROM timetable_entries WHERE id = ?");
  $stmt->execute([$id]);
}

header("Location: /edt/admin/timetable.php?week=" . urlencode($week));
exit;
