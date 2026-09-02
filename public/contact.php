<?php

declare(strict_types=1);

require __DIR__ . '/../config/bootstrap.php';

/** @var PDO $pdo */

use App\Security\Auth;

$auth = new Auth($pdo);

$title = 'Контакты';
$active = 'contact';

require __DIR__ . '/../templates/site/header.php';
?>
    <div class="hero-wrap" style="background-image: url('images/bg_1.jpg');">
      <div class="overlay"></div>
      <div class="container">
        <div class="row no-gutters slider-text d-flex align-itemd-end justify-content-center">
          <div class="col-md-9 ftco-animate text-center d-flex align-items-end justify-content-center">
            <div class="text">
              <h1 class="mb-4 bread">Контакты</h1>
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="ftco-section contact-section bg-light">
      <div class="container">
        <div class="row d-flex mb-5 contact-info">
          <div class="col-md-12 mb-4">
            <h2 class="h3">Контактная информация</h2>
          </div>
          <div class="w-100"></div>
          <div class="col-md-3 d-flex">
            <div class="info bg-white p-4">
              <p><span>Адрес:<br></span>г. Советский<br> ул. Озерная 1/А </p>
            </div>
          </div>
          <div class="col-md-3 d-flex">
            <div class="info bg-white p-4">
              <p><span>Телефон:</span> <a href="tel:+79227925626">+7-922-792-56-26</a></p>
            </div>
          </div>
          <div class="col-md-3 d-flex">
            <div class="info bg-white p-4">
              <p><span>Эл. почта:</span> <a href="mailto:info@hotel.localhost">info@hotel.localhost</a></p>
            </div>
          </div>
          <div class="col-md-3 d-flex">
            <div class="info bg-white p-4">
              <p><span>Сайт</span> <a href="https://hotel.localhost">hotel.localhost</a></p>
            </div>
          </div>
        </div>
        <div class="row block-9">
          <div class="col-md-6 d-flex">
            <div class="bg-white">
              <iframe src="https://www.google.com/maps/embed?pb=!1m10!1m8!1m3!1d7650.569339765921!2d63.54622245449409!3d61.3550305051893!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sru!2sru!4v1549807261874" width="600" height="450" frameborder="0" style="border:0" allowfullscreen loading="lazy" title="Карта гостиницы"></iframe>
            </div>
          </div>
        </div>
      </div>
    </section>
<?php
require __DIR__ . '/../templates/site/footer.php';
