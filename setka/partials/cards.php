<?php
require_once __DIR__ . '/catalog-lib.php';

$recommended = setka_catalog_page(1, 10)['items'];
$hits = setka_catalog_page(2, 10)['items'];
?>
<section class="wt_section">
  <div class="wt_container">
    <div
      class="wt_slider_block"
      data-wt-slider-root
      data-per-view="5"
      data-gap="20"
      data-loop="true"
      data-autoplay="true"
      data-autoplay-delay="4500"
    >
      <div class="wt_section_top">
        <div>
          <h2 class="wt_section_title">Рекомендовані товари</h2>
        </div>
        <?= setka_slider_nav() ?>
      </div>
      <div class="wt_slider" data-wt-slider>
        <div class="wt_slider_viewport">
          <div class="wt_slider_track">
            <?= setka_render_product_slides($recommended) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="wt_section" style="padding-top:0">
  <div class="wt_container">
    <div
      class="wt_slider_block"
      data-wt-slider-root
      data-per-view="5"
      data-gap="20"
      data-loop="true"
      data-autoplay="false"
    >
      <div class="wt_section_top">
        <div>
          <h2 class="wt_section_title">Хіти продажу</h2>
        </div>
        <?= setka_slider_nav() ?>
      </div>
      <div class="wt_slider" data-wt-slider>
        <div class="wt_slider_viewport">
          <div class="wt_slider_track">
            <?= setka_render_product_slides($hits) ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
