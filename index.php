<?php
declare(strict_types=1);
require_once __DIR__ . "/includes/auth.php";

if (empty($_SESSION['user'])) {
  header("Location: /edt/login.php");
  exit;
}

$role = $_SESSION['user']['role'];
if ($role === 'admin') header("Location: /edt/admin/dashboard.php");
elseif ($role === 'teacher') header("Location: /edt/teacher/dashboard.php");
else header("Location: /edt/student/dashboard.php");
exit;
