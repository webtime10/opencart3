<?php
require_once __DIR__ . '/catalog-lib.php';

$page = 1;
$limit = 8;
$data = setka_catalog_page($page, $limit);
$subcats = [
  ['name' => 'Механічні', 'count' => 24, 'active' => true],
  ['name' => 'Кварцові', 'count' => 41, 'active' => false],
  ['name' => 'Смарт-годинники', 'count' => 18, 'active' => false],
  ['name' => 'Хронографи', 'count' => 12, 'active' => false],
];
$brands = ['Casio', 'Seiko', 'Orient', 'Tissot', 'Garmin'];
?>
<section
  class="category-page"
  data-category-ajax
  data-endpoint="api/category-products.php"
  data-page="<?= (int)$data['page'] ?>"
  data-pages="<?= (int)$data['pages'] ?>"
  data-limit="<?= (int)$data['limit'] ?>"
>
  <div class="container">
    <ul class="breadcrumb">
      <li><a href="index.php">Головна</a></li>
      <li><a href="category.php">Каталог</a></li>
      <li>Годинники</li>
    </ul>

    <div class="category-head">
      <h1>Годинники</h1>
      <p>Постранична навігація і стрілка підвантаження працюють через AJAX без перезавантаження сторінки.</p>
    </div>

    <div class="category-layout">
      <aside class="category-filters" aria-label="Фільтри">
        <h3>Фільтри</h3>

        <div class="filter-block">
          <h4>Підкатегорії</h4>
          <ul class="filter-list">
            <?php foreach ($subcats as $sc): ?>
            <li>
              <a href="#" class="<?= !empty($sc['active']) ? 'is-active' : '' ?>">
                <span><?= htmlspecialchars($sc['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="count"><?= (int)$sc['count'] ?></span>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <div class="filter-block">
          <h4>Бренд</h4>
          <?php foreach ($brands as $i => $brand): ?>
          <label class="filter-check">
            <input type="checkbox"<?= $i < 2 ? ' checked' : '' ?>>
            <?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="filter-block">
          <h4>Ціна, ₴</h4>
          <div class="filter-price">
            <input class="form-control" type="number" placeholder="Від" value="1000">
            <input class="form-control" type="number" placeholder="До" value="8000">
          </div>
          <button type="button" class="btn btn-primary btn-sm btn-block w-100" style="margin-top:10px">Застосувати</button>
        </div>
      </aside>

      <div class="category-main">
        <div class="category-toolbar">
          <div class="category-toolbar-left">
            <span class="meta" data-found-meta>Знайдено <?= (int)$data['total'] ?> товарів</span>
          </div>
          <div class="category-toolbar-right">
            <label>
              Сортування
              <select class="form-control" data-sort>
                <option value="popular">За популярністю</option>
                <option value="price_asc">Ціна: зростання</option>
                <option value="price_desc">Ціна: спадання</option>
                <option value="new">Новинки</option>
              </select>
            </label>
            <label>
              Показати
              <select class="form-control" data-limit>
                <option value="8" selected>8</option>
                <option value="16">16</option>
                <option value="24">24</option>
              </select>
            </label>
          </div>
        </div>

        <div class="products products-catalog" data-products-grid>
          <?= setka_render_product_cards($data['items']) ?>
        </div>

        <div class="category-pagination">
          <span class="meta" data-page-meta>Показано <?= (int)$data['from'] ?>–<?= (int)$data['to'] ?> з <?= (int)$data['total'] ?></span>
          <div data-pager-wrap>
            <?= setka_render_pagination($data['page'], $data['pages']) ?>
          </div>
        </div>

        <div class="category-loadmore">
          <button
            type="button"
            class="load-more-arrow"
            data-load-more
            title="Показати ще"
            aria-label="Показати ще"
            <?= $data['has_more'] ? '' : ' hidden' ?>
          >
            <svg class="icon" aria-hidden="true"><use href="#i-chevron"></use></svg>
          </button>
        </div>
      </div>
    </div>
  </div>
</section>
