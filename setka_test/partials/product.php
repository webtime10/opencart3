<?php
$product_title = 'Батарея до ДБЖ Europower 12В 20Аг (EP12-20M5)';
$product_images = [
  [
    'src' => 'img/product/1.jpg',
    'width' => 2400,
    'height' => 1800,
    'alt' => 'Europower EP12-20M5 — фото 1',
  ],
  [
    'src' => 'img/product/2.jpg',
    'width' => 2400,
    'height' => 1800,
    'alt' => 'Europower EP12-20M5 — фото 2',
  ],
  [
    'src' => 'img/product/3.jpg',
    'width' => 2400,
    'height' => 1800,
    'alt' => 'Europower EP12-20M5 — фото 3',
  ],
  [
    'src' => 'img/product/4.jpg',
    'width' => 2400,
    'height' => 1800,
    'alt' => 'Europower EP12-20M5 — фото 4',
  ],
];
$gallery_items = array_map(static function ($img) {
  return [
    'src' => $img['src'],
    'width' => $img['width'],
    'height' => $img['height'],
    'alt' => $img['alt'],
  ];
}, $product_images);
?>
<section class="wt_product_page" data-product-page>
  <div class="wt_container">
    <ul class="wt_breadcrumb">
      <li><a href="index.php">Головна</a></li>
      <li><a href="category.php">Каталог</a></li>
      <li><a href="category.php">ДБЖ / батареї</a></li>
      <li>Europower EP12-20M5</li>
    </ul>

    <div class="wt_product_top">
      <div
        class="wt_product_gallery"
        data-product-gallery
        data-images="<?= htmlspecialchars(json_encode($gallery_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
      >
        <button type="button" class="wt_product_main" data-photo-open data-photo-index="0" aria-label="Відкрити фото">
          <img
            class="wt_product_main_img"
            data-main-img
            src="<?= htmlspecialchars($product_images[0]['src'], ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars($product_images[0]['alt'], ENT_QUOTES, 'UTF-8') ?>"
            width="800"
            height="600"
          >
        </button>
        <div class="wt_product_thumbs" role="list">
          <?php foreach ($product_images as $i => $img): ?>
            <button
              type="button"
              class="<?= $i === 0 ? 'is-active' : '' ?>"
              data-thumb
              data-photo-index="<?= (int) $i ?>"
              aria-label="Фото <?= (int) ($i + 1) ?>"
            >
              <img src="<?= htmlspecialchars($img['src'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="120" height="90" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="wt_product_buybox">
        <h1 class="wt_product_title"><?= htmlspecialchars($product_title, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="wt_product_meta">
          <span>Код: <strong>EP12-20M5</strong></span>
          <span class="wt_product_stock is-in">В наявності</span>
        </div>

        <div class="wt_product_price_row">
          <div class="wt_product_price">1 584 ₴</div>
          <div class="wt_product_old">1 790 ₴</div>
        </div>

        <div class="wt_product_cta">
          <button type="button" class="wt_btn wt_btn_lg wt_btn_block" data-cart-open>
            <svg class="wt_icon" aria-hidden="true"><use href="#i-cart"></use></svg>
            Купити
          </button>
          <button type="button" class="wt_btn wt_btn_secondary wt_btn_lg wt_btn_block">Купити в кредит</button>
          <button type="button" class="wt_btn wt_btn_secondary wt_btn_icon_lg" title="Wishlist" aria-label="Wishlist">
            <svg class="wt_icon"><use href="#i-heart"></use></svg>
          </button>
          <button type="button" class="wt_btn wt_btn_secondary wt_btn_icon_lg" title="Compare" aria-label="Compare">
            <svg class="wt_icon"><use href="#i-compare"></use></svg>
          </button>
        </div>

        <div class="wt_product_delivery">
          <h3>Доставка</h3>
          <ul>
            <li>
              <svg class="wt_icon wt_icon_md" aria-hidden="true"><use href="#i-truck"></use></svg>
              <div>
                <strong>Нова Пошта</strong>
                <span>Відділення / курʼєр · від 1–2 днів</span>
              </div>
            </li>
            <li>
              <svg class="wt_icon wt_icon_md" aria-hidden="true"><use href="#i-truck"></use></svg>
              <div>
                <strong>Укрпошта</strong>
                <span>По всій Україні · від 2–4 днів</span>
              </div>
            </li>
            <li>
              <svg class="wt_icon wt_icon_md" aria-hidden="true"><use href="#i-wallet"></use></svg>
              <div>
                <strong>Оплата</strong>
                <span>Онлайн / наложений платіж / безготівка</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="wt_product_tabs" id="product-tabs" data-tabs>
      <div class="wt_tabs_nav" role="tablist">
        <button type="button" class="is-active" role="tab" aria-selected="true" data-tab="desc">Опис</button>
        <button type="button" role="tab" aria-selected="false" data-tab="specs">Характеристики</button>
        <button type="button" role="tab" aria-selected="false" data-tab="reviews">Відгуки</button>
        <button type="button" role="tab" aria-selected="false" data-tab="photos">Фото</button>
      </div>

      <div class="wt_tabs_panels">
        <div class="wt_tab_panel is-active" id="tab-desc" role="tabpanel" data-panel="desc">
          <h2>Опис</h2>
          <p>Свинцево-кислотна батарея Europower EP12-20M5 для джерел безперебійного живлення. Технологія AGM забезпечує стабільну роботу, безпечну експлуатацію та тривалий ресурс.</p>
          <p>Підходить для домашніх і офісних ДБЖ, резервного живлення мережевого обладнання та систем відеоспостереження. Герметичний корпус дозволяє встановлювати батарею в будь-якій орієнтації (крім догори клемами вниз).</p>
          <ul class="wt_product_bullets">
            <li>Напруга 12 В, ємність 20 А·г</li>
            <li>AGM технологія, низький саморозряд</li>
            <li>Сумісність із більшістю ДБЖ середнього класу</li>
          </ul>
        </div>

        <div class="wt_tab_panel" id="tab-specs" role="tabpanel" data-panel="specs" hidden>
          <h2>Характеристики</h2>
          <table class="wt_specs">
            <tbody>
              <tr><th>Тип</th><td>Акумулятор для ДБЖ (AGM)</td></tr>
              <tr><th>Бренд</th><td>Europower</td></tr>
              <tr><th>Модель</th><td>EP12-20M5</td></tr>
              <tr><th>Напруга</th><td>12 В</td></tr>
              <tr><th>Ємність</th><td>20 А·г</td></tr>
              <tr><th>Клеми</th><td>M5</td></tr>
              <tr><th>Габарити</th><td>181 × 77 × 167 мм</td></tr>
              <tr><th>Вага</th><td>~5.8 кг</td></tr>
              <tr><th>Гарантія</th><td>12 місяців</td></tr>
            </tbody>
          </table>
        </div>

        <div class="wt_tab_panel" id="tab-reviews" role="tabpanel" data-panel="reviews" hidden>
          <h2>Відгуки</h2>
          <div class="wt_review">
            <strong>Олександр</strong>
            <span class="wt_review_meta">5 / 5 · 12.08.2026</span>
            <p>Працює тихо, тримає навантаження як заявлено. Для домашнього ДБЖ — ок.</p>
          </div>
          <div class="wt_review">
            <strong>Ірина</strong>
            <span class="wt_review_meta">4 / 5 · 02.08.2026</span>
            <p>Доставка швидка. Батарея важка, але це очікувано для 20Ah.</p>
          </div>

          <form class="wt_review_form" action="#" method="post">
            <h3>Залишити відгук</h3>
            <div class="wt_field">
              <label for="pros">Переваги</label>
              <input class="wt_input" id="pros" type="text" placeholder="Що сподобалось">
            </div>
            <div class="wt_field">
              <label for="cons">Недоліки</label>
              <input class="wt_input" id="cons" type="text" placeholder="Що можна покращити">
            </div>
            <div class="wt_field">
              <label for="comment">Коментар</label>
              <textarea class="wt_input" id="comment" rows="4" placeholder="Ваш відгук"></textarea>
            </div>
            <button type="submit" class="wt_btn">Надіслати</button>
          </form>
        </div>

        <div class="wt_tab_panel" id="tab-photos" role="tabpanel" data-panel="photos" hidden>
          <h2>Фото</h2>
          <div class="wt_product_photo_grid">
            <?php foreach ($product_images as $i => $img): ?>
              <button type="button" data-photo-open data-photo-index="<?= (int) $i ?>" aria-label="Відкрити фото <?= (int) ($i + 1) ?>">
                <img src="<?= htmlspecialchars($img['src'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($img['alt'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <section class="wt_product_related">
      <?php
      require_once __DIR__ . '/catalog-lib.php';
      $analogs = setka_catalog_page(1, 10)['items'];
      ?>
      <div
        class="wt_slider_block"
        data-wt-slider-root
        data-per-view="5"
        data-gap="20"
        data-loop="true"
        data-autoplay="false"
      >
        <div class="wt_section_top">
          <h2 class="wt_section_title">Аналоги</h2>
          <div class="wt_slider_nav">
            <button type="button" class="wt_slider_btn" data-wt-slider-prev aria-label="Назад">
              <svg class="wt_icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
            </button>
            <button type="button" class="wt_slider_btn" data-wt-slider-next aria-label="Далі">
              <svg class="wt_icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
            </button>
          </div>
        </div>
        <div class="wt_slider" data-wt-slider>
          <div class="wt_slider_viewport">
            <div class="wt_slider_track">
              <?php foreach ($analogs as $p): ?>
                <div class="wt_slider_slide"><?= setka_render_product_card($p) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</section>

<div class="wt_photo_modal" data-photo-modal hidden>
  <div class="wt_photo_modal_dialog" role="dialog" aria-modal="true" aria-label="Фото товару">
    <div class="wt_photo_modal_head">
      <strong>ФОТО · <?= htmlspecialchars($product_title, ENT_QUOTES, 'UTF-8') ?></strong>
      <button type="button" class="wt_photo_modal_close" data-photo-close aria-label="Закрити">×</button>
    </div>
    <div class="wt_photo_modal_body">
      <div class="wt_photo_modal_thumbs" role="list">
        <?php foreach ($product_images as $i => $img): ?>
          <button
            type="button"
            class="<?= $i === 0 ? 'is-active' : '' ?>"
            data-modal-thumb
            data-photo-index="<?= (int) $i ?>"
            aria-label="Фото <?= (int) ($i + 1) ?>"
          >
            <img src="<?= htmlspecialchars($img['src'], ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
          </button>
        <?php endforeach; ?>
      </div>
      <div class="wt_photo_modal_stage">
        <div class="wt_photo_modal_tools" data-zoom-tools>
          <button type="button" data-zoom-in aria-label="Збільшити">+</button>
          <button type="button" data-zoom-out aria-label="Зменшити">−</button>
          <button type="button" data-zoom-reset aria-label="Скинути">⟲</button>
        </div>
        <div class="wt_photo_modal_view" data-zoom-wrap>
          <img
            data-modal-img
            src="<?= htmlspecialchars($product_images[0]['src'], ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars($product_images[0]['alt'], ENT_QUOTES, 'UTF-8') ?>"
            draggable="false"
          >
        </div>
      </div>
    </div>
  </div>
</div>
