<?php
/**
 * Seed demo catalog for plum_store theme testing.
 * - Demo categories (RU/EN/UA)
 * - 18 products per category
 * - Photos from /фото/1..6 (gallery)
 * - Options: Color, Memory, Warranty
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$root = 'C:/OSPanel64/home/opencart3.loc';
$imageRoot = $root . '/image';
$demoRel = 'catalog/demo_seed';

$db = new mysqli('127.0.1.29', 'root', '123', 'opencart3');
$db->set_charset('utf8mb4');
$prefix = 'oc_';

function findFotoRoot(string $root): string {
	foreach (scandir($root) ?: [] as $name) {
		if ($name === '.' || $name === '..') {
			continue;
		}
		$path = $root . DIRECTORY_SEPARATOR . $name;
		if (!is_dir($path)) {
			continue;
		}
		$has = true;
		for ($i = 1; $i <= 6; $i++) {
			if (!is_dir($path . DIRECTORY_SEPARATOR . (string) $i)) {
				$has = false;
				break;
			}
		}
		if ($has) {
			return $path;
		}
	}
	throw new RuntimeException('Foto folder 1..6 not found under ' . $root);
}

function esc(mysqli $db, $v): string {
	return "'" . $db->real_escape_string((string) $v) . "'";
}

function slugify(string $text): string {
	$map = [
		'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
		'і'=>'i','ї'=>'yi','є'=>'ye','ґ'=>'g',
	];
	$text = mb_strtolower($text, 'UTF-8');
	$text = strtr($text, $map);
	$text = preg_replace('/[^a-z0-9]+/i', '-', $text);
	return trim($text, '-') ?: 'item';
}

function copySet(string $srcDir, string $dstDir): array {
	if (!is_dir($dstDir)) {
		mkdir($dstDir, 0775, true);
	}
	$files = [];
	foreach (scandir($srcDir) ?: [] as $f) {
		if ($f === '.' || $f === '..') {
			continue;
		}
		$src = $srcDir . DIRECTORY_SEPARATOR . $f;
		if (!is_file($src)) {
			continue;
		}
		$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
		if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
			continue;
		}
		$dstName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $f);
		$dst = $dstDir . DIRECTORY_SEPARATOR . $dstName;
		if (!is_file($dst)) {
			copy($src, $dst);
		}
		$files[] = $dstName;
	}
	sort($files);
	return $files;
}

$fotoRoot = findFotoRoot($root);
echo "Foto root: $fotoRoot\n";

$sets = [];
for ($i = 1; $i <= 6; $i++) {
	$src = $fotoRoot . DIRECTORY_SEPARATOR . $i;
	$dst = $imageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $demoRel) . DIRECTORY_SEPARATOR . $i;
	$files = copySet($src, $dst);
	if (!$files) {
		throw new RuntimeException("No images in set $i");
	}
	$sets[$i] = array_map(static fn ($f) => $demoRel . '/' . $i . '/' . $f, $files);
	echo "Set $i: " . count($files) . " files\n";
}

$langs = [];
$r = $db->query("SELECT language_id, code FROM {$prefix}language WHERE status=1 ORDER BY language_id");
while ($row = $r->fetch_assoc()) {
	$langs[(int) $row['language_id']] = $row['code'];
}
if (!$langs) {
	throw new RuntimeException('No languages');
}
echo 'Languages: ' . implode(',', array_keys($langs)) . "\n";

$stock = (int) ($db->query("SELECT stock_status_id FROM {$prefix}stock_status ORDER BY stock_status_id LIMIT 1")->fetch_assoc()['stock_status_id'] ?? 7);
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// --- Options ---
function ensureOption(mysqli $db, string $prefix, array $langs, string $type, array $namesByCode, array $valuesByCode): array {
	// Find by RU/EN name
	$searchName = $namesByCode['ru-ru'] ?? reset($namesByCode);
	$existing = $db->query("SELECT o.option_id FROM {$prefix}option o INNER JOIN {$prefix}option_description od ON o.option_id=od.option_id WHERE od.name=" . esc($db, $searchName) . " LIMIT 1");
	if ($existing && $existing->num_rows) {
		$optionId = (int) $existing->fetch_assoc()['option_id'];
	} else {
		$db->query("INSERT INTO {$prefix}option SET type=" . esc($db, $type) . ", sort_order=0");
		$optionId = (int) $db->insert_id;
		foreach ($langs as $lid => $code) {
			$name = $namesByCode[$code] ?? $namesByCode['en-gb'] ?? $searchName;
			$db->query("INSERT INTO {$prefix}option_description SET option_id={$optionId}, language_id={$lid}, name=" . esc($db, $name));
		}
	}

	$valueIds = [];
	$sort = 0;
	foreach ($valuesByCode as $key => $labels) {
		$labelRu = $labels['ru-ru'] ?? reset($labels);
		$found = $db->query("SELECT ov.option_value_id FROM {$prefix}option_value ov INNER JOIN {$prefix}option_value_description ovd ON ov.option_value_id=ovd.option_value_id WHERE ov.option_id={$optionId} AND ovd.name=" . esc($db, $labelRu) . " LIMIT 1");
		if ($found && $found->num_rows) {
			$valueIds[$key] = (int) $found->fetch_assoc()['option_value_id'];
			continue;
		}
		$db->query("INSERT INTO {$prefix}option_value SET option_id={$optionId}, image='', sort_order={$sort}");
		$ovid = (int) $db->insert_id;
		foreach ($langs as $lid => $code) {
			$name = $labels[$code] ?? $labels['en-gb'] ?? $labelRu;
			$db->query("INSERT INTO {$prefix}option_value_description SET option_value_id={$ovid}, language_id={$lid}, option_id={$optionId}, name=" . esc($db, $name));
		}
		$valueIds[$key] = $ovid;
		$sort++;
	}

	return ['option_id' => $optionId, 'values' => $valueIds];
}

$optColor = ensureOption($db, $prefix, $langs, 'select', [
	'ru-ru' => 'Цвет',
	'en-gb' => 'Color',
	'uk-ua' => 'Колір',
], [
	'red' => ['ru-ru' => 'Красный', 'en-gb' => 'Red', 'uk-ua' => 'Червоний'],
	'blue' => ['ru-ru' => 'Синий', 'en-gb' => 'Blue', 'uk-ua' => 'Синій'],
	'yellow' => ['ru-ru' => 'Жёлтый', 'en-gb' => 'Yellow', 'uk-ua' => 'Жовтий'],
	'black' => ['ru-ru' => 'Чёрный', 'en-gb' => 'Black', 'uk-ua' => 'Чорний'],
	'white' => ['ru-ru' => 'Белый', 'en-gb' => 'White', 'uk-ua' => 'Білий'],
]);

$optMemory = ensureOption($db, $prefix, $langs, 'select', [
	'ru-ru' => 'Память',
	'en-gb' => 'Memory',
	'uk-ua' => "Пам'ять",
], [
	'128' => ['ru-ru' => '128 ГБ', 'en-gb' => '128 GB', 'uk-ua' => '128 ГБ'],
	'256' => ['ru-ru' => '256 ГБ', 'en-gb' => '256 GB', 'uk-ua' => '256 ГБ'],
	'512' => ['ru-ru' => '512 ГБ', 'en-gb' => '512 GB', 'uk-ua' => '512 ГБ'],
	'1tb' => ['ru-ru' => '1 ТБ', 'en-gb' => '1 TB', 'uk-ua' => '1 ТБ'],
]);

$optWarranty = ensureOption($db, $prefix, $langs, 'radio', [
	'ru-ru' => 'Гарантия',
	'en-gb' => 'Warranty',
	'uk-ua' => 'Гарантія',
], [
	'12' => ['ru-ru' => '12 месяцев', 'en-gb' => '12 months', 'uk-ua' => '12 місяців'],
	'24' => ['ru-ru' => '24 месяца', 'en-gb' => '24 months', 'uk-ua' => '24 місяці'],
]);

echo "Options ready: color={$optColor['option_id']} memory={$optMemory['option_id']} warranty={$optWarranty['option_id']}\n";

// --- Categories ---
function addCategory(mysqli $db, string $prefix, array $langs, int $parentId, array $names, int $sort, string $now): int {
	$db->query("INSERT INTO {$prefix}category SET image='', parent_id={$parentId}, top=" . ($parentId ? 0 : 1) . ", `column`=1, sort_order={$sort}, status=1, page_group_links='', date_added=" . esc($db, $now) . ", date_modified=" . esc($db, $now) . ", noindex=0");
	$cid = (int) $db->insert_id;
	foreach ($langs as $lid => $code) {
		$name = $names[$code] ?? $names['ru-ru'] ?? reset($names);
		$db->query(
			"INSERT INTO {$prefix}category_description SET category_id={$cid}, language_id={$lid}, name=" . esc($db, $name) .
			", description=" . esc($db, '<p>' . $name . '</p>') .
			", meta_title=" . esc($db, $name) .
			", meta_description='', meta_keyword='', meta_h1=" . esc($db, $name)
		);
	}
	$db->query("INSERT INTO {$prefix}category_to_store SET category_id={$cid}, store_id=0");

	// path
	if ($parentId) {
		$pr = $db->query("SELECT path_id, level FROM {$prefix}category_path WHERE category_id={$parentId} ORDER BY level");
		while ($p = $pr->fetch_assoc()) {
			$db->query("INSERT INTO {$prefix}category_path SET category_id={$cid}, path_id=" . (int) $p['path_id'] . ", level=" . (int) $p['level']);
		}
		$maxLevel = (int) ($db->query("SELECT MAX(level) m FROM {$prefix}category_path WHERE category_id={$parentId}")->fetch_assoc()['m'] ?? -1);
		$db->query("INSERT INTO {$prefix}category_path SET category_id={$cid}, path_id={$cid}, level=" . ($maxLevel + 1));
	} else {
		$db->query("INSERT INTO {$prefix}category_path SET category_id={$cid}, path_id={$cid}, level=0");
	}

	// seo urls if table exists
	$seoExists = $db->query("SHOW TABLES LIKE '{$prefix}seo_url'")->num_rows > 0;
	if ($seoExists) {
		foreach ($langs as $lid => $code) {
			$name = $names[$code] ?? $names['ru-ru'];
			$keyword = slugify($name) . '-' . $cid;
			$db->query("INSERT INTO {$prefix}seo_url SET store_id=0, language_id={$lid}, query=" . esc($db, 'category_id=' . $cid) . ", keyword=" . esc($db, $keyword));
		}
	}

	return $cid;
}

// Avoid duplicate demo parent
$demoName = 'Демо шаблона';
$existingDemo = $db->query("SELECT category_id FROM {$prefix}category_description WHERE name=" . esc($db, $demoName) . " LIMIT 1");
if ($existingDemo && $existingDemo->num_rows) {
	$parentId = (int) $existingDemo->fetch_assoc()['category_id'];
	echo "Using existing demo parent #$parentId\n";
	// delete previous demo products under this tree (optional cleanup of prior seed)
	$childIds = [];
	$cr = $db->query("SELECT category_id FROM {$prefix}category WHERE parent_id={$parentId}");
	while ($c = $cr->fetch_assoc()) {
		$childIds[] = (int) $c['category_id'];
	}
	$allCats = array_merge([$parentId], $childIds);
	if ($childIds) {
		$in = implode(',', $allCats);
		$pr = $db->query("SELECT DISTINCT product_id FROM {$prefix}product_to_category WHERE category_id IN ($in)");
		$pids = [];
		while ($p = $pr->fetch_assoc()) {
			$pids[] = (int) $p['product_id'];
		}
		foreach ($pids as $pid) {
			$db->query("DELETE FROM {$prefix}product_option_value WHERE product_id={$pid}");
			$db->query("DELETE FROM {$prefix}product_option WHERE product_id={$pid}");
			$db->query("DELETE FROM {$prefix}product_image WHERE product_id={$pid}");
			$db->query("DELETE FROM {$prefix}product_description WHERE product_id={$pid}");
			$db->query("DELETE FROM {$prefix}product_to_category WHERE product_id={$pid}");
			$db->query("DELETE FROM {$prefix}product_to_store WHERE product_id={$pid}");
			if ($db->query("SHOW TABLES LIKE '{$prefix}seo_url'")->num_rows) {
				$db->query("DELETE FROM {$prefix}seo_url WHERE query=" . esc($db, 'product_id=' . $pid));
			}
			$db->query("DELETE FROM {$prefix}product WHERE product_id={$pid}");
		}
		foreach ($childIds as $cid) {
			$db->query("DELETE FROM {$prefix}category_description WHERE category_id={$cid}");
			$db->query("DELETE FROM {$prefix}category_to_store WHERE category_id={$cid}");
			$db->query("DELETE FROM {$prefix}category_path WHERE category_id={$cid}");
			if ($db->query("SHOW TABLES LIKE '{$prefix}seo_url'")->num_rows) {
				$db->query("DELETE FROM {$prefix}seo_url WHERE query=" . esc($db, 'category_id=' . $cid));
			}
			$db->query("DELETE FROM {$prefix}category WHERE category_id={$cid}");
		}
		echo "Cleaned previous demo children/products\n";
	}
} else {
	$parentId = addCategory($db, $prefix, $langs, 0, [
		'ru-ru' => 'Демо шаблона',
		'en-gb' => 'Theme Demo',
		'uk-ua' => 'Демо шаблону',
	], 0, $now);
	echo "Created demo parent #$parentId\n";
}

$categoryDefs = [
	[
		'ru-ru' => 'Смартфоны DEMO',
		'en-gb' => 'Smartphones DEMO',
		'uk-ua' => 'Смартфони DEMO',
		'prefix' => 'Phone',
	],
	[
		'ru-ru' => 'Аксессуары DEMO',
		'en-gb' => 'Accessories DEMO',
		'uk-ua' => 'Аксесуари DEMO',
		'prefix' => 'Case',
	],
	[
		'ru-ru' => 'Аудио DEMO',
		'en-gb' => 'Audio DEMO',
		'uk-ua' => 'Аудіо DEMO',
		'prefix' => 'Audio',
	],
	[
		'ru-ru' => 'Гаджеты DEMO',
		'en-gb' => 'Gadgets DEMO',
		'uk-ua' => 'Гаджети DEMO',
		'prefix' => 'Gadget',
	],
];

$categoryIds = [];
$sort = 1;
foreach ($categoryDefs as $def) {
	$names = $def;
	unset($names['prefix']);
	$cid = addCategory($db, $prefix, $langs, $parentId, $names, $sort++, $now);
	$categoryIds[] = ['id' => $cid, 'prefix' => $def['prefix'], 'names' => $names];
	echo "Category #$cid {$def['ru-ru']}\n";
}

function addProduct(
	mysqli $db,
	string $prefix,
	array $langs,
	int $categoryId,
	string $model,
	array $names,
	float $price,
	array $images,
	array $optionPacks,
	int $stock,
	string $now,
	string $today
): int {
	$main = $images[0];
	$db->query(
		"INSERT INTO {$prefix}product SET model=" . esc($db, $model) .
		", sku='', upc='', ean='', jan='', isbn='', mpn='', location='', quantity=100, stock_status_id={$stock}, image=" . esc($db, $main) .
		", manufacturer_id=0, shipping=1, price=" . esc($db, number_format($price, 4, '.', '')) .
		", points=0, tax_class_id=0, date_available=" . esc($db, $today) .
		", weight=0, weight_class_id=1, length=0, width=0, height=0, length_class_id=1, subtract=1, minimum=1, sort_order=0, status=1, viewed=0, date_added=" . esc($db, $now) . ", date_modified=" . esc($db, $now) . ", oct_stickers='', noindex=0"
	);
	$pid = (int) $db->insert_id;

	foreach ($langs as $lid => $code) {
		$name = $names[$code] ?? $names['ru-ru'] ?? $model;
		$db->query(
			"INSERT INTO {$prefix}product_description SET product_id={$pid}, language_id={$lid}, name=" . esc($db, $name) .
			", description=" . esc($db, '<p>' . $name . ' — demo product for theme testing.</p>') .
			", tag='', meta_title=" . esc($db, $name) . ", meta_description='', meta_keyword='', meta_h1=" . esc($db, $name)
		);
	}

	$db->query("INSERT INTO {$prefix}product_to_store SET product_id={$pid}, store_id=0");
	$db->query("INSERT INTO {$prefix}product_to_category SET product_id={$pid}, category_id={$categoryId}, main_category=1");

	// additional images (skip first = main)
	$sort = 1;
	foreach (array_slice($images, 1) as $img) {
		$db->query("INSERT INTO {$prefix}product_image SET product_id={$pid}, image=" . esc($db, $img) . ", sort_order={$sort}");
		$sort++;
	}

	foreach ($optionPacks as $pack) {
		$optionId = (int) $pack['option_id'];
		$required = !empty($pack['required']) ? 1 : 0;
		$db->query("INSERT INTO {$prefix}product_option SET product_id={$pid}, option_id={$optionId}, value='', required={$required}");
		$poid = (int) $db->insert_id;
		foreach ($pack['values'] as $ovid => $meta) {
			$priceDelta = isset($meta['price']) ? (float) $meta['price'] : 0;
			$prefixChar = $priceDelta >= 0 ? '+' : '-';
			$db->query(
				"INSERT INTO {$prefix}product_option_value SET product_option_id={$poid}, product_id={$pid}, option_id={$optionId}, option_value_id=" . (int) $ovid .
				", quantity=50, subtract=0, price=" . esc($db, number_format(abs($priceDelta), 4, '.', '')) .
				", price_prefix=" . esc($db, $prefixChar) . ", points=0, points_prefix='+', weight=0, weight_prefix='+'"
			);
		}
	}

	if ($db->query("SHOW TABLES LIKE '{$prefix}seo_url'")->num_rows) {
		foreach ($langs as $lid => $code) {
			$name = $names[$code] ?? $names['ru-ru'];
			$keyword = slugify($name) . '-' . $pid;
			$db->query("INSERT INTO {$prefix}seo_url SET store_id=0, language_id={$lid}, query=" . esc($db, 'product_id=' . $pid) . ", keyword=" . esc($db, $keyword));
		}
	}

	return $pid;
}

$created = 0;
foreach ($categoryIds as $catIndex => $cat) {
	for ($n = 1; $n <= 18; $n++) {
		$setIndex = (($n - 1) % 6) + 1;
		$images = $sets[$setIndex];
		// use up to 6 images
		$images = array_slice($images, 0, 6);
		if (count($images) < 2 && isset($sets[$setIndex])) {
			// duplicate if set has few images so gallery still has several
			while (count($images) < 4) {
				$images[] = $images[array_rand($images)];
			}
		}

		$model = sprintf('DEMO-%s-%02d', strtoupper($cat['prefix']), $n);
		$names = [
			'ru-ru' => $cat['names']['ru-ru'] . " #{$n}",
			'en-gb' => $cat['names']['en-gb'] . " #{$n}",
			'uk-ua' => $cat['names']['uk-ua'] . " #{$n}",
		];
		// nicer phone-like names for first category
		if ($cat['prefix'] === 'Phone') {
			$names = [
				'ru-ru' => "Смартфон Demo Pro {$n}",
				'en-gb' => "Smartphone Demo Pro {$n}",
				'uk-ua' => "Смартфон Demo Pro {$n}",
			];
		}

		$price = 4999 + ($catIndex * 1000) + ($n * 137);

		// vary options by product index
		$colorValues = $optColor['values'];
		$memoryValues = $optMemory['values'];
		$warrantyValues = $optWarranty['values'];

		$colorPick = array_slice($colorValues, 0, 3 + ($n % 3), true); // 3-5 colors
		$memoryPick = array_slice($memoryValues, 0, 2 + ($n % 3), true);
		$warrantyPick = $warrantyValues;

		$optionPacks = [];

		$colorMeta = [];
		$i = 0;
		foreach ($colorPick as $key => $ovid) {
			$colorMeta[$ovid] = ['price' => $i === 0 ? 0 : 100 * $i];
			$i++;
		}
		$optionPacks[] = [
			'option_id' => $optColor['option_id'],
			'required' => 1,
			'values' => $colorMeta,
		];

		if ($cat['prefix'] === 'Phone' || $cat['prefix'] === 'Gadget') {
			$memMeta = [];
			$i = 0;
			foreach ($memoryPick as $key => $ovid) {
				$memMeta[$ovid] = ['price' => $i * 500];
				$i++;
			}
			$optionPacks[] = [
				'option_id' => $optMemory['option_id'],
				'required' => 1,
				'values' => $memMeta,
			];
		}

		if ($n % 2 === 0) {
			$wMeta = [];
			foreach ($warrantyPick as $key => $ovid) {
				$wMeta[$ovid] = ['price' => $key === '24' ? 299 : 0];
			}
			$optionPacks[] = [
				'option_id' => $optWarranty['option_id'],
				'required' => 0,
				'values' => $wMeta,
			];
		}

		$pid = addProduct($db, $prefix, $langs, $cat['id'], $model, $names, $price, $images, $optionPacks, $stock, $now, $today);
		$created++;
		echo "Product #$pid {$names['ru-ru']} set={$setIndex} imgs=" . count($images) . "\n";
	}
}

echo "\nDONE. Created products: {$created}\n";
echo "Parent category ID: {$parentId}\n";
echo "Open admin Categories / Catalog and storefront category 'Демо шаблона'\n";
