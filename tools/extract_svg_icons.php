<?php
$srcFile = __DIR__ . '/../catalog/view/theme/plum_store/image/icons.svg';
$dir = __DIR__ . '/../catalog/view/theme/plum_store/image/svg';
if (!is_dir($dir)) {
	mkdir($dir, 0777, true);
}

$map = [
	'i-phone' => 'phone.svg',
	'i-heart' => 'heart.svg',
	'i-compare' => 'compare.svg',
	'i-bag' => 'bag.svg',
	'i-user' => 'user.svg',
	'i-wallet' => 'pay.svg',
	'i-truck' => 'truck.svg',
	'i-shield' => 'shield.svg',
	'i-cart' => 'cart.svg',
	'i-search' => 'search.svg',
	'i-chevron' => 'chevron.svg',
	'i-chevron-left' => 'chevron-left.svg',
	'i-chevron-right' => 'chevron-right.svg',
	'i-trash' => 'trash.svg',
	'i-store' => 'store.svg',
	'i-more' => 'more.svg',
	'i-close' => 'close.svg',
	'i-bolt' => 'bolt.svg',
	'i-laptop' => 'laptop.svg',
	'i-cpu' => 'cpu.svg',
	'i-smartphone' => 'smartphone.svg',
	'i-tv' => 'tv.svg',
	'i-brand' => 'brand.svg',
	'i-gamepad' => 'gamepad.svg',
	'i-printer' => 'printer.svg',
	'i-router' => 'router.svg',
	'i-headphones' => 'headphones.svg',
	'i-watch' => 'watch.svg',
	'i-appliance' => 'appliance.svg',
	'i-home' => 'home.svg',
	'i-wrench' => 'wrench.svg',
	'i-grid' => 'grid.svg',
];

$src = file_get_contents($srcFile);
if (!preg_match_all('/<symbol\s+id="([^"]+)"([^>]*)>(.*?)<\/symbol>/s', $src, $m, PREG_SET_ORDER)) {
	fwrite(STDERR, "no symbols\n");
	exit(1);
}

foreach ($m as $row) {
	$id = $row[1];
	$attrs = trim($row[2]);
	$inner = trim($row[3]);
	$name = isset($map[$id]) ? $map[$id] : (preg_replace('/^i-/', '', $id) . '.svg');

	$svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$svg .= '<svg xmlns="http://www.w3.org/2000/svg"' . ($attrs !== '' ? ' ' . $attrs : '') . ">\n";
	$svg .= $inner . "\n";
	$svg .= "</svg>\n";

	file_put_contents($dir . '/' . $name, $svg);
	echo $name . "\n";
}

echo 'TOTAL=' . count($m) . "\n";
