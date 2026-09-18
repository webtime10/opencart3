<?php
/**
 * Add gallery images + Color/Memory/Warranty options to seeded products.
 * Run: php tools/seed_product_extras.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

$root = dirname(__DIR__);
require $root . '/config.php';

$m = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)DB_PORT);
if ($m->connect_error) {
	fwrite(STDERR, $m->connect_error . PHP_EOL);
	exit(1);
}
$m->set_charset('utf8mb4');
$p = DB_PREFIX;

function q(mysqli $m, string $sql): void {
	if (!$m->query($sql)) {
		throw new RuntimeException($m->error . "\nSQL: " . $sql);
	}
}

function esc(mysqli $m, string $s): string {
	return $m->real_escape_string($s);
}

function collectImages(string $dir): array {
	$out = [];
	if (!is_dir($dir)) {
		return $out;
	}
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f) {
		if (!$f->isFile()) {
			continue;
		}
		if (!preg_match('/\.(jpe?g|png|webp|gif)$/i', $f->getFilename())) {
			continue;
		}
		$out[] = $f->getPathname();
	}
	sort($out);
	return $out;
}

$ruId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='ru-ru'")->fetch_assoc()['language_id'];
$enId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='en-gb'")->fetch_assoc()['language_id'];
$ukId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='uk-ua'")->fetch_assoc()['language_id'];
$langs = [$ruId, $enId, $ukId];

echo "== 1) Ensure Size option has EN/RU/UK names ==\n";
$sizeId = 11;
$r = $m->query("SELECT option_id FROM {$p}option WHERE option_id={$sizeId}");
if ($r && $r->num_rows) {
	foreach ([
		$ruId => 'Размер',
		$enId => 'Size',
		$ukId => 'Розмір',
	] as $lid => $name) {
		$ex = $m->query("SELECT 1 FROM {$p}option_description WHERE option_id={$sizeId} AND language_id={$lid}")->num_rows;
		if ($ex) {
			q($m, "UPDATE {$p}option_description SET name='" . esc($m, $name) . "' WHERE option_id={$sizeId} AND language_id={$lid}");
		} else {
			q($m, "INSERT INTO {$p}option_description SET option_id={$sizeId}, language_id={$lid}, name='" . esc($m, $name) . "'");
		}
	}
	$sizeValues = [
		46 => [$ruId => 'S', $enId => 'S', $ukId => 'S'],
		47 => [$ruId => 'M', $enId => 'M', $ukId => 'M'],
		48 => [$ruId => 'L', $enId => 'L', $ukId => 'L'],
	];
	foreach ($sizeValues as $ovid => $names) {
		$exists = $m->query("SELECT 1 FROM {$p}option_value WHERE option_value_id={$ovid} AND option_id={$sizeId}")->num_rows;
		if (!$exists) {
			continue;
		}
		foreach ($names as $lid => $name) {
			$ex = $m->query("SELECT 1 FROM {$p}option_value_description WHERE option_value_id={$ovid} AND language_id={$lid}")->num_rows;
			if ($ex) {
				q($m, "UPDATE {$p}option_value_description SET name='" . esc($m, $name) . "', option_id={$sizeId} WHERE option_value_id={$ovid} AND language_id={$lid}");
			} else {
				q($m, "INSERT INTO {$p}option_value_description SET option_value_id={$ovid}, language_id={$lid}, option_id={$sizeId}, name='" . esc($m, $name) . "'");
			}
		}
	}
	echo "Size option #{$sizeId} localized\n";
}

echo "== 2) Resolve Color / Memory / Warranty ==\n";
$optColor = 13;
$optMemory = 14;
$optWarranty = 15;

function optionValues(mysqli $m, string $p, int $optionId): array {
	$ids = [];
	$r = $m->query("SELECT option_value_id FROM {$p}option_value WHERE option_id={$optionId} ORDER BY sort_order, option_value_id");
	while ($row = $r->fetch_assoc()) {
		$ids[] = (int)$row['option_value_id'];
	}
	return $ids;
}

$colorValues = optionValues($m, $p, $optColor);
$memoryValues = optionValues($m, $p, $optMemory);
$warrantyValues = optionValues($m, $p, $optWarranty);
$sizeValuesIds = optionValues($m, $p, $sizeId);

if (!$colorValues || !$memoryValues || !$warrantyValues) {
	throw new RuntimeException('Missing option values for Color/Memory/Warranty');
}
echo 'color=' . implode(',', $colorValues) . ' memory=' . implode(',', $memoryValues) . ' warranty=' . implode(',', $warrantyValues) . "\n";

echo "== 3) Build image pools ==\n";
$fotoImgs = collectImages($root . '/фото');
$demoImgs = collectImages($root . '/image/catalog/demo');
$seedImgs = collectImages($root . '/image/catalog/seed');
echo 'foto=' . count($fotoImgs) . ' demo=' . count($demoImgs) . ' seed=' . count($seedImgs) . "\n";

$galleryDir = $root . '/image/catalog/seed/_gallery';
if (!is_dir($galleryDir)) {
	mkdir($galleryDir, 0777, true);
}

// Prefer foto phones, then demo — copy into shared gallery for stable relative paths
$absPool = array_values(array_unique(array_merge($fotoImgs, $demoImgs)));
if (!$absPool) {
	$absPool = $seedImgs;
}
if (!$absPool) {
	throw new RuntimeException('No images for gallery');
}

$relGallery = [];
foreach ($absPool as $i => $src) {
	$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
	$name = 'g' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT) . '.' . $ext;
	$dest = $galleryDir . '/' . $name;
	if (!is_file($dest)) {
		if (!@copy($src, $dest)) {
			throw new RuntimeException('copy failed: ' . $src);
		}
	}
	$relGallery[] = 'catalog/seed/_gallery/' . $name;
}
echo 'gallery_files=' . count($relGallery) . "\n";

echo "== 4) Attach options + gallery to seed products ==\n";
$products = [];
$r = $m->query("SELECT product_id, image FROM {$p}product WHERE image LIKE 'catalog/seed/%' AND image NOT LIKE 'catalog/seed/_gallery/%' ORDER BY product_id");
while ($row = $r->fetch_assoc()) {
	$products[] = $row;
}
echo 'products=' . count($products) . "\n";

$galleryPerProduct = 4;
$optAttached = 0;
$imgAttached = 0;

foreach ($products as $idx => $prod) {
	$pid = (int)$prod['product_id'];
	$main = (string)$prod['image'];

	// --- gallery ---
	q($m, "DELETE FROM {$p}product_image WHERE product_id={$pid}");
	$added = 0;
	$start = $idx * 3; // shift per product so galleries differ
	for ($g = 0; $g < count($relGallery) && $added < $galleryPerProduct; $g++) {
		$rel = $relGallery[($start + $g) % count($relGallery)];
		if ($rel === $main) {
			continue;
		}
		$sort = $added + 1;
		q($m, "INSERT INTO {$p}product_image SET product_id={$pid}, image='" . esc($m, $rel) . "', sort_order={$sort}");
		$added++;
		$imgAttached++;
	}

	// --- options: wipe previous seed options for these ids, re-add ---
	$optIds = [$optColor, $optMemory, $optWarranty, $sizeId];
	$in = implode(',', $optIds);
	$poIds = [];
	$rPo = $m->query("SELECT product_option_id FROM {$p}product_option WHERE product_id={$pid} AND option_id IN ({$in})");
	while ($row = $rPo->fetch_assoc()) {
		$poIds[] = (int)$row['product_option_id'];
	}
	if ($poIds) {
		$poIn = implode(',', $poIds);
		q($m, "DELETE FROM {$p}product_option_value WHERE product_option_id IN ({$poIn})");
		q($m, "DELETE FROM {$p}product_option WHERE product_option_id IN ({$poIn})");
	}

	$attach = [
		[
			'option_id' => $optColor,
			'required' => 1,
			'values' => $colorValues,
			'prices' => [], // flat
		],
		[
			'option_id' => $optMemory,
			'required' => 1,
			'values' => $memoryValues,
			// progressive surcharge
			'prices' => [0, 40, 90, 180],
		],
		[
			'option_id' => $optWarranty,
			'required' => 0,
			'values' => $warrantyValues,
			'prices' => [0, 25],
		],
		[
			'option_id' => $sizeId,
			'required' => 0,
			'values' => $sizeValuesIds,
			'prices' => [],
		],
	];

	foreach ($attach as $spec) {
		$oid = (int)$spec['option_id'];
		$req = (int)$spec['required'];
		q($m, "INSERT INTO {$p}product_option SET product_id={$pid}, option_id={$oid}, value='', required={$req}");
		$poId = (int)$m->insert_id;
		foreach ($spec['values'] as $vi => $ovid) {
			$price = isset($spec['prices'][$vi]) ? (float)$spec['prices'][$vi] : 0.0;
			$prefix = $price > 0 ? '+' : '+';
			$qty = 50;
			q($m, "INSERT INTO {$p}product_option_value SET
				product_option_id={$poId},
				product_id={$pid},
				option_id={$oid},
				option_value_id=" . (int)$ovid . ",
				quantity={$qty},
				subtract=0,
				price='{$price}',
				price_prefix='{$prefix}',
				points=0,
				points_prefix='+',
				weight='0.00000000',
				weight_prefix='+'
			");
		}
		$optAttached++;
	}

	if (($idx + 1) % 80 === 0) {
		echo "  ... " . ($idx + 1) . "/" . count($products) . "\n";
	}
}

echo "\nDONE products=" . count($products) . " option_links={$optAttached} gallery_rows={$imgAttached}\n";

// clear cache
$cacheDir = DIR_CACHE;
if (is_dir($cacheDir)) {
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($it as $f) {
		if ($f->getFilename() === 'index.html') {
			continue;
		}
		$f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
	}
}
echo "cache cleared\n";
