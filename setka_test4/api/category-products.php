<?php
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__) . '/partials/catalog-lib.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(4, min(32, (int)($_GET['limit'] ?? 8)));
$append = !empty($_GET['append']);
$data = setka_catalog_page($page, $limit);

echo json_encode([
  'ok' => true,
  'append' => $append,
  'html' => setka_render_product_cards($data['items']),
  'page' => $data['page'],
  'pages' => $data['pages'],
  'limit' => $data['limit'],
  'total' => $data['total'],
  'from' => $data['from'],
  'to' => $data['to'],
  'has_more' => $data['has_more'],
  'meta' => 'Показано ' . $data['from'] . '–' . $data['to'] . ' з ' . $data['total'],
  'found' => 'Знайдено ' . $data['total'] . ' товарів',
  'pager' => setka_render_pagination($data['page'], $data['pages']),
], JSON_UNESCAPED_UNICODE);
