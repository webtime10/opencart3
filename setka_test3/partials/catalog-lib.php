<?php
function setka_catalog_base_products(): array {
  return [
    ['name' => 'Classic Leather', 'price' => '2 490 ₴', 'text' => 'Механіка, шкіряний ремінець, 40 мм'],
    ['name' => 'Navy Pilot', 'price' => '3 180 ₴', 'text' => 'Сапфірове скло, водозахист 100м'],
    ['name' => 'Minimal White', 'price' => '1 950 ₴', 'text' => 'Тонкий корпус, кварц, унісекс'],
    ['name' => 'Chrono Black', 'price' => '4 220 ₴', 'text' => 'Хронограф, сталь, браслет'],
    ['name' => 'Sport Titanium', 'price' => '5 640 ₴', 'text' => 'Титан, GPS, спорт-режим'],
    ['name' => 'Gold Dress', 'price' => '6 120 ₴', 'text' => 'Позолота, сапфір, 36 мм'],
    ['name' => 'Dive Pro', 'price' => '7 890 ₴', 'text' => 'Водозахист 300м, кераміка'],
    ['name' => 'Urban Mesh', 'price' => '2 780 ₴', 'text' => 'Міланський браслет, кварц'],
  ];
}

function setka_catalog_total(): int {
  return 48;
}

function setka_catalog_page(int $page, int $limit): array {
  $base = setka_catalog_base_products();
  $total = setka_catalog_total();
  $pages = max(1, (int)ceil($total / $limit));
  $page = max(1, min($page, $pages));
  $offset = ($page - 1) * $limit;
  $items = [];

  for ($i = 0; $i < $limit; $i++) {
    $index = $offset + $i;
    if ($index >= $total) {
      break;
    }
    $tpl = $base[$index % count($base)];
    $n = $index + 1;
    $items[] = [
      'n' => $n,
      'name' => $tpl['name'] . ' #' . $n,
      'price' => $tpl['price'],
      'text' => $tpl['text'],
    ];
  }

  $from = $total ? $offset + 1 : 0;
  $to = $offset + count($items);

  return [
    'items' => $items,
    'page' => $page,
    'pages' => $pages,
    'limit' => $limit,
    'total' => $total,
    'from' => $from,
    'to' => $to,
    'has_more' => $page < $pages,
  ];
}

function setka_render_product_card(array $p): string {
  $name = htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8');
  $text = htmlspecialchars($p['text'], ENT_QUOTES, 'UTF-8');
  $price = htmlspecialchars($p['price'], ENT_QUOTES, 'UTF-8');
  $n = (int)$p['n'];

  return <<<HTML
<article class="card">
  <a class="card-media" href="product.php">WATCH {$n}</a>
  <div class="card-body">
    <h3 class="card-title"><a href="product.php">{$name}</a></h3>
    <p class="card-text">{$text}</p>
    <div class="card-price">{$price}</div>
  </div>
  <div class="card-actions">
    <button type="button" class="btn btn-primary btn-sm" data-cart-open>
      <svg class="icon icon-sm" aria-hidden="true"><use href="#i-cart"></use></svg>
      <span>В кошик</span>
    </button>
    <button type="button" class="btn btn-default btn-sm btn-icon" title="Wishlist" aria-label="Wishlist">
      <svg class="icon icon-sm" aria-hidden="true"><use href="#i-heart"></use></svg>
    </button>
    <button type="button" class="btn btn-default btn-sm btn-icon" title="Compare" aria-label="Compare">
      <svg class="icon icon-sm" aria-hidden="true"><use href="#i-compare"></use></svg>
    </button>
  </div>
</article>
HTML;
}

function setka_render_product_cards(array $items): string {
  $html = '';
  foreach ($items as $p) {
    $html .= setka_render_product_card($p);
  }
  return $html;
}

function setka_render_product_slides(array $items): string {
  $html = '';
  foreach ($items as $p) {
    $html .= '<div class="slider-slide">' . setka_render_product_card($p) . '</div>';
  }
  return $html;
}

function setka_slider_nav(string $size = ''): string {
  $sm = $size === 'sm' ? ' slider-btn--sm' : '';
  return <<<HTML
<div class="slider-nav">
  <button type="button" class="slider-btn{$sm}" data-wt-slider-prev aria-label="Назад">
    <svg class="icon" aria-hidden="true"><use href="#i-chevron-left"></use></svg>
  </button>
  <button type="button" class="slider-btn{$sm}" data-wt-slider-next aria-label="Далі">
    <svg class="icon" aria-hidden="true"><use href="#i-chevron-right"></use></svg>
  </button>
</div>
HTML;
}

function setka_render_pagination(int $page, int $pages): string {
  if ($pages < 1) {
    return '';
  }

  $prevDisabled = $page <= 1;
  $nextDisabled = $page >= $pages;
  $html = '<ul class="pagination" data-ajax-pager>';
  $html .= '<li' . ($prevDisabled ? ' class="disabled"' : '') . '><a href="#" data-page="' . max(1, $page - 1) . '">&laquo;</a></li>';
  for ($i = 1; $i <= $pages; $i++) {
    $active = $i === $page ? ' class="active"' : '';
    $html .= '<li' . $active . '><a href="#" data-page="' . $i . '">' . $i . '</a></li>';
  }
  $html .= '<li' . ($nextDisabled ? ' class="disabled"' : '') . '><a href="#" data-page="' . min($pages, $page + 1) . '">&raquo;</a></li>';
  $html .= '</ul>';
  return $html;
}
