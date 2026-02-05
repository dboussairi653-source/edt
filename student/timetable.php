<?php
require_once "../includes/auth.php";
require_once "../config/db.php";
require_role(['student']);
$u=current_user();
$e=$pdo->prepare("
SELECT * FROM timetable_entries WHERE group_id=? ORDER BY day_of_week,start_time
");
$e->execute([$u['groupe_id']]);
require "../includes/header.php";
?>
<h4>Emploi du temps</h4>
<table class="table">
<?php foreach($e as $x): ?>
<tr><td><?=$x['day_of_week']?></td><td><?=$x['start_time']?>-<?=$x['end_time']?></td></tr>
<?php endforeach ?>
</table>
<?php require "../includes/footer.php"; ?>
