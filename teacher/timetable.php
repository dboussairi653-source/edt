<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_role(['teacher']);
$u=current_user();
$e=$pdo->prepare("
SELECT * FROM timetable_entries WHERE teacher_id=? ORDER BY day_of_week,start_time
");
$e->execute([$u['id']]);
require "../includes/header.php";
?>
<h4>Mon emploi du temps</h4>
<table class="table">
<?php foreach($e as $x): ?>
<tr><td><?=$x['day_of_week']?></td><td><?=$x['start_time']?>-<?=$x['end_time']?></td></tr>
<?php endforeach ?>
</table>
<?php require "../includes/footer.php"; ?>
