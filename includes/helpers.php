<?php
// includes/helpers.php
declare(strict_types=1);

function e($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
  header("Location: $path");
  exit;
}

function week_start_from_date(string $date): string {
  $dt = new DateTime($date);
  $day = (int)$dt->format('N'); // 1..7
  $dt->modify('-' . ($day - 1) . ' days');
  return $dt->format('Y-m-d');
}

function is_time_overlap(string $startA, string $endA, string $startB, string $endB): bool {
  return ($startA < $endB) && ($startB < $endA);
}

function day_name(int $d): string {
  return match($d) {
    1 => "Lundi",
    2 => "Mardi",
    3 => "Mercredi",
    4 => "Jeudi",
    5 => "Vendredi",
    6 => "Samedi",
    7 => "Dimanche",
    default => "?"
  };
}
