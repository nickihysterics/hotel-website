<?php

declare(strict_types=1);
require __DIR__.'/../../config/bootstrap.php';
/** @var PDO $pdo */
use App\Security\Auth;

$auth = new Auth($pdo);
$auth->requireGuest();
$bookingId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT b.id,b.code,rt.name room_type_name FROM bookings b INNER JOIN room_types rt ON rt.id=b.room_type_id WHERE b.id=:id AND b.client_id=:client_id AND b.status='checked_out'");
$stmt->execute(['id' => $bookingId,'client_id' => $auth->clientId()]);
$booking = $stmt->fetch();
if (!$booking) {
    http_response_code(404);
    exit('Завершённое проживание не найдено.');
}$stmt = $pdo->prepare('SELECT * FROM reviews WHERE booking_id=:id');
$stmt->execute(['id' => $bookingId]);
$review = $stmt->fetch();
$error = null;
if (is_post()) {
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));
    if (!verify_csrf($_POST['_token'] ?? null)) {
        $error = 'Сессия формы истекла.';
    } elseif ($rating < 1 || $rating > 5 || strlen($comment) < 5) {
        $error = 'Поставьте оценку и напишите хотя бы несколько слов.';
    } else {
        $pdo->prepare('INSERT INTO reviews(booking_id,client_id,rating,comment,status) VALUES(:booking_id,:client_id,:rating,:comment,"pending") ON DUPLICATE KEY UPDATE rating=VALUES(rating),comment=VALUES(comment),status="pending"')->execute(['booking_id' => $bookingId,'client_id' => $auth->clientId(),'rating' => $rating,'comment' => $comment]);
        audit_log($pdo, 'review.submitted', 'booking', (string)$bookingId, ['rating' => $rating]);
        flash('success', 'Спасибо! Отзыв отправлен на модерацию.');
        redirect('/account/');
    }
}
$title = 'Отзыв';
$active = 'account';
require __DIR__.'/../../templates/site/header.php';
?>
<main class="product-page"><section class="product-container checkout-heading"><a class="back-link" href="/account/">← Мои поездки</a><span class="eyebrow">Бронь <?=e((string)$booking['code'])?></span><h1>Как прошло проживание?</h1></section><section class="product-container"><form class="product-card product-form" method="post"><?=csrf_field()?><input type="hidden" name="id" value="<?=e((string)$bookingId)?>"><?php if ($error):?><div class="form-alert"><?=e($error)?></div><?php endif;?><label>Оценка<select name="rating" required><option value="">Выберите</option><?php for ($i = 5;$i >= 1;--$i):?><option value="<?=$i?>" <?=(int)($review['rating'] ?? 0) === $i ? 'selected' : ''?>><?=str_repeat('★', $i)?> — <?=$i?> из 5</option><?php endfor;?></select></label><label>Отзыв<textarea name="comment" minlength="5" required><?=e((string)($review['comment'] ?? ''))?></textarea></label><button class="product-button">Отправить отзыв</button></form></section></main>
<?php require __DIR__.'/../../templates/site/footer.php';?>
