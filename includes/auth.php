<?php
// includes/auth.php
declare(strict_types=1);

session_start();

function require_login(): void {
  if (empty($_SESSION['user'])) {
    header("Location: /edt/login.php");
    exit;
  }
}

function require_role(array $roles): void {
  require_login();
  $role = $_SESSION['user']['role'] ?? '';
  if (!in_array($role, $roles, true)) {
    http_response_code(403);
    exit("Accès refusé.");
  }
}

function current_user(): ?array {
  return $_SESSION['user'] ?? null;
}
