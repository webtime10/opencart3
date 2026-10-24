<?php
require_once __DIR__ . '/catalog-lib.php';

$recommended = setka_catalog_page(1, 10)['items'];
$hits = setka_catalog_page(2, 10)['items'];
?>
<section class="section">
  <div class="container">
    <div
      class="slider-block"
      data-wt-slider-root
      data-per-view="5"
      data-gap="20"
      data-loop="true"
      data-autoplay="true"
      data-autoplay-delay="4500"
    >
      <div class="section-top">
        <div>
          <h2 class="section-title">Рекомендовані товари</h2>
        </div>
        <?= setka_slider_nav() ?>
      </div>
      <div class="slider" data-wt-slider>
        <div class="slider-viewport">
          <div class="slider-track">
            <?= setka_render_product_slides($recommended) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0">
  <div class="container">
    <div
      class="slider-block"
      data-wt-slider-root
      data-per-view="5"
      data-gap="20"
      data-loop="true"
      data-autoplay="false"
    >
      <div class="section-top">
        <div>
          <h2 class="section-title">Хіти продажу</h2>
        </div>
        <?= setka_slider_nav() ?>
      </div>
      <div class="slider" data-wt-slider>
        <div class="slider-viewport">
          <div class="slider-track">
            <?= setka_render_product_slides($hits) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
