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
<section class="product-page" data-product-page>
  <div class="container">
    <ul class="breadcrumb">
      <li><a href="index.php">Головна</a></li>
      <li><a href="category.php">Каталог</a></li>
      <li><a href="category.php">ДБЖ / батареї</a></li>
      <li>Europower EP12-20M5</li>
    </ul>

    <div class="product-top">
      <div
        class="product-gallery"
        data-product-gallery
        data-images="<?= htmlspecialchars(json_encode($gallery_items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
      >
        <button type="button" class="product-main" data-photo-open data-photo-index="0" aria-label="Відкрити фото">
          <img
            class="product-main-img"
            data-main-img
            src="<?= htmlspecialchars($product_images[0]['src'], ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars($product_images[0]['alt'], ENT_QUOTES, 'UTF-8') ?>"
            width="800"
            height="600"
          >
        </button>
        <div class="product-thumbs" role="list">
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

      <div class="product-buybox">
        <h1 class="product-title"><?= htmlspecialchars($product_title, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="product-meta">
          <span>Код: <strong>EP12-20M5</strong></span>
          <span class="product-stock is-in">В наявності</span>
        </div>

        <div class="product-price-row">
          <div class="product-price">1 584 ₴</div>
          <div class="product-old">1 790 ₴</div>
        </div>

        <div class="product-cta">
          <button type="button" class="btn btn-primary btn-lg btn-block w-100" data-cart-open>
            <svg class="icon" aria-hidden="true"><use href="#i-cart"></use></svg>
            Купити
          </button>
          <button type="button" class="btn btn-secondary btn-lg btn-block w-100">Купити в кредит</button>
          <button type="button" class="btn btn-secondary btn btn-secondary btn-icon-lg" title="Wishlist" aria-label="Wishlist">
            <svg class="icon"><use href="#i-heart"></use></svg>
          </button>
          <button type="button" class="btn btn-secondary btn btn-secondary btn-icon-lg" title="Compare" aria-label="Compare">
            <svg class="icon"><use href="#i-compare"></use></svg>
          </button>
        </div>

        <div class="product-delivery">
          <h3>Доставка</h3>
          <ul>
            <li>
              <svg class="icon icon-md" aria-hidden="true"><use href="#i-truck"></use></svg>
              <div>
                <strong>Нова Пошта</strong>
                <span>Відділення / курʼєр · від 1–2 днів</span>
              </div>
            </li>
            <li>
              <svg class="icon icon-md" aria-hidden="true"><use href="#i-truck"></use></svg>
              <div>
                <strong>Укрпошта</strong>
                <span>По всій Україні · від 2–4 днів</span>
              </div>
            </li>
            <li>
              <svg class="icon icon-md" aria-hidden="true"><use href="#i-wallet"></use></svg>
              <div>
                <strong>Оплата</strong>
                <span>Онлайн / наложений платіж / безготівка</span>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="product-tabs" id="product-tabs" data-tabs>
      <div class="tabs-nav" role="tablist">
        <button type="button" class="is-active" role="tab" aria-selected="true" data-tab="desc">Опис</button>
        <button type="button" role="tab" aria-selected="false" data-tab="specs">Характеристики</button>
        <button type="button" role="tab" aria-selected="false" data-tab="reviews">Відгуки</button>
        <button type="button" role="tab" aria-selected="false" data-tab="photos">Фото</button>
      </div>

      <div class="tabs-panels">
        <div class="tab-panel is-active" id="tab-desc" role="tabpanel" data-panel="desc">
          <h2>Опис</h2>
          <p>Свинцево-кислотна батарея Europower EP12-20M5 для джерел безперебійного живлення. Технологія AGM забезпечує стабільну роботу, безпечну експлуатацію та тривалий ресурс.</p>
          <p>Підходить для домашніх і офісних ДБЖ, резервного живлення мережевого обладнання та систем відеоспостереження. Герметичний корпус дозволяє встановлювати батарею в будь-якій орієнтації (крім догори клемами вниз).</p>
          <ul class="product-bullets">
            <li>Напруга 12 В, ємність 20 А·г</li>
            <li>AGM технологія, низький саморозряд</li>
            <li>Сумісність із більшістю ДБЖ середнього класу</li>
          </ul>
        </div>

        <div class="tab-panel" id="tab-specs" role="tabpanel" data-panel="specs" hidden>
          <h2>Характеристики</h2>
          <table class="specs">
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

        <div class="tab-panel" id="tab-reviews" role="tabpanel" data-panel="reviews" hidden>
          <h2>Відгуки</h2>
          <div class="review">
            <strong>Олександр</strong>
            <span class="review-meta">5 / 5 · 12.08.2026</span>
            <p>Працює тихо, тримає навантаження як заявлено. Для домашнього ДБЖ — ок.</p>
          </div>
          <div class="review">
            <strong>Ірина</strong>
            <span class="review-meta">4 / 5 · 02.08.2026</span>
            <p>Доставка швидка. Батарея важка, але це очікувано для 20Ah.</p>
          </div>

          <form class="review-form" action="#" method="post">
            <h3>Залишити відгук</h3>
            <div class="field">
              <label for="pros">Переваги</label>
              <input class="form-control" id="pros" type="text" placeholder="Що сподобалось">
            </div>
            <div class="field">
              <label for="cons">Недоліки</label>
              <input class="form-control" id="cons" type="text" placeholder="Що можна покращити">
            </div>
            <div class="field">
              <label for="comment">Коментар</label>
              <textarea class="form-control" id="comment" rows="4" placeholder="Ваш відгук"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Надіслати</button>
          </form>
        </div>

        <div class="tab-panel" id="tab-photos" role="tabpanel" data-panel="photos" hidden>
          <h2>Фото</h2>
          <div class="product-photo-grid">
            <?php foreach ($product_images as $i => $img): ?>
              <button type="button" data-photo-open data-photo-index="<?= (int) $i ?>" aria-label="Відкрити фото <?= (int) ($i + 1) ?>">
                <img src="<?= htmlspecialchars($img['src'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($img['alt'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
              </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <section class="product-related">
      <?php
      require_once __DIR__ . '/catalog-lib.php';
      $analogs = setka_catalog_page(1, 10)['items'];
      ?>
      <div
        class="slider-block"
        data-wt-slider-root
        data-per-view="5"
        data-gap="20"
        data-loop="true"
        data-autoplay="false"
      >
        <div class="section-top">
          <h2 class="section-title">Аналоги</h2>
          <div class="slider-nav">
            <button type="button" class="slider-btn" data-wt-slider-prev aria-label="Назад">
              <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
            </button>
            <button type="button" class="slider-btn" data-wt-slider-next aria-label="Далі">
              <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
            </button>
          </div>
        </div>
        <div class="slider" data-wt-slider>
          <div class="slider-viewport">
            <div class="slider-track">
              <?php foreach ($analogs as $p): ?>
                <div class="slider-slide"><?= setka_render_product_card($p) ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</section>

<div class="photo-modal" data-photo-modal hidden>
  <div class="photo-modal-dialog" role="dialog" aria-modal="true" aria-label="Фото товару">
    <div class="photo-modal-head">
      <strong>ФОТО · <?= htmlspecialchars($product_title, ENT_QUOTES, 'UTF-8') ?></strong>
      <button type="button" class="photo-modal-close" data-photo-close aria-label="Закрити">×</button>
    </div>
    <div class="photo-modal-body">
      <div class="photo-modal-thumbs" role="list">
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
      <div class="photo-modal-stage">
        <div class="photo-modal-tools" data-zoom-tools>
          <button type="button" data-zoom-in aria-label="Збільшити">+</button>
          <button type="button" data-zoom-out aria-label="Зменшити">−</button>
          <button type="button" data-zoom-reset aria-label="Скинути">⟲</button>
        </div>
        <div class="photo-modal-view" data-zoom-wrap>
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
