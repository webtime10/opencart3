<?php
/**
 * Включить WT Filter на витрине + добавить в макеты column_left.
 * Run: docker exec opencart3_web php /var/www/html/tools/ensure_wt_filter_front.php
 * Or from host: php tools/ensure_wt_filter_front.php
 */
require_once __DIR__ . '/../config.php';

$dbHost = DB_HOSTNAME;
$dbPort = (int)DB_PORT;

if ($dbHost === 'db_oc3' && php_sapi_name() === 'cli') {
	$dbHost = '127.0.0.1';
	$dbPort = 3308;
}

$mysqli = new mysqli($dbHost, DB_USERNAME, DB_PASSWORD, DB_DATABASE, $dbPort);

if ($mysqli->connect_error) {
	fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
	exit(1);
}

$mysqli->set_charset('utf8mb4');
$prefix = DB_PREFIX;

$settings = array(
	'module_wt_filter_status'              => '1',
	'module_wt_filter_attribute_separator' => ',',
	'module_wt_filter_price_special'       => '0',
	'module_wt_filter_discount'            => '1',
	'module_wt_filter_seo_text_position'   => 'below',
	'module_wt_filter_show_counts'         => '1',
	'module_wt_filter_accent_color'        => '#229ac8',
);

foreach ($settings as $key => $value) {
	$stmt = $mysqli->prepare("DELETE FROM `{$prefix}setting` WHERE `key` = ? AND store_id = '0'");
	$stmt->bind_param('s', $key);
	$stmt->execute();
	$stmt->close();

	$code = 'module_wt_filter';
	$stmt = $mysqli->prepare("INSERT INTO `{$prefix}setting` SET store_id = '0', `code` = ?, `key` = ?, `value` = ?, serialized = '0'");
	$stmt->bind_param('sss', $code, $key, $value);
	$stmt->execute();
	$stmt->close();
	echo "Setting: $key = $value\n";
}

$routes = array('product/category', 'product/manufacturer/info', 'product/special');

foreach ($routes as $route) {
	$stmt = $mysqli->prepare("SELECT layout_id FROM `{$prefix}layout_route` WHERE route = ? AND store_id = '0' LIMIT 1");
	$stmt->bind_param('s', $route);
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res->fetch_assoc();
	$stmt->close();

	if (!$row) {
		echo "No layout for route: $route\n";
		continue;
	}

	$layout_id = (int)$row['layout_id'];
	$check = $mysqli->query("SELECT layout_module_id FROM `{$prefix}layout_module`
		WHERE layout_id = '{$layout_id}' AND code = 'wt_filter' AND position = 'column_left' LIMIT 1");

	if ($check && $check->num_rows) {
		echo "wt_filter already in layout {$layout_id} ({$route})\n";
		continue;
	}

	$mysqli->query("INSERT INTO `{$prefix}layout_module` SET layout_id = '{$layout_id}', code = 'wt_filter', position = 'column_left', sort_order = '1'");
	echo "Added wt_filter to layout {$layout_id} ({$route})\n";
}

echo "Done. Open category page and hard-refresh (Ctrl+F5).\n";
