<?php
/**
 * Delete Мониторы + Периферия under Ноутбуки и компьютеры (and all descendants),
 * then rebuild WT Menu.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)(DB_PORT ?: 3306));
$db->set_charset('utf8mb4');
$p = DB_PREFIX;

function collectDescendants(mysqli $db, $p, $rootId) {
	$ids = [];
	$r = $db->query("SELECT category_id FROM `{$p}category_path` WHERE path_id = '" . (int)$rootId . "'");
	while ($row = $r->fetch_assoc()) {
		$ids[] = (int)$row['category_id'];
	}
	return array_values(array_unique($ids));
}

function deleteCategories(mysqli $db, $p, array $ids) {
	if (!$ids) {
		return;
	}
	$list = implode(',', array_map('intval', $ids));
	$tables = [
		'category_description',
		'category_path',
		'category_to_store',
		'category_to_layout',
		'category_filter',
		'product_to_category',
		'coupon_category',
	];
	foreach ($tables as $t) {
		$check = $db->query("SHOW TABLES LIKE '{$p}{$t}'");
		if ($check && $check->num_rows) {
			$db->query("DELETE FROM `{$p}{$t}` WHERE category_id IN ({$list})");
		}
	}
	$db->query("DELETE FROM `{$p}category` WHERE category_id IN ({$list})");
}

// Find under parent 78 (Ноутбуки и компьютеры)
$parentId = 78;
$targets = ['Мониторы', 'Периферия'];
$toDelete = [];

foreach ($targets as $name) {
	$r = $db->query("SELECT c.category_id FROM `{$p}category` c
		INNER JOIN `{$p}category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = 1)
		WHERE c.parent_id = '{$parentId}' AND cd.name = '" . $db->real_escape_string($name) . "'");
	while ($row = $r->fetch_assoc()) {
		$cid = (int)$row['category_id'];
		$desc = collectDescendants($db, $p, $cid);
		echo "Delete {$name} #{$cid} (+" . (count($desc) - 1) . " descendants)\n";
		$toDelete = array_merge($toDelete, $desc);
	}
}

$toDelete = array_values(array_unique($toDelete));
deleteCategories($db, $p, $toDelete);
echo "Deleted " . count($toDelete) . " categories\n";

// Rebuild WT Menu
$exclude = '92,93,94,95,96';
$lang = 1;
$byParent = [];
$names = [];
$r = $db->query("SELECT c.category_id, c.parent_id, cd.name
	FROM `{$p}category` c
	INNER JOIN `{$p}category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = {$lang})
	WHERE c.status = 1 AND c.category_id NOT IN ($exclude) AND c.parent_id NOT IN ($exclude)
	ORDER BY c.parent_id, c.sort_order, LCASE(cd.name)");
while ($row = $r->fetch_assoc()) {
	$cid = (int)$row['category_id'];
	$names[$cid] = $row['name'];
	$byParent[(int)$row['parent_id']][] = $cid;
}

$idCounter = 1;
function buildMenuNodes(array $catIds, array $byParent, array $names, &$idCounter) {
	$nodes = [];
	foreach ($catIds as $cid) {
		$children = !empty($byParent[$cid]) ? buildMenuNodes($byParent[$cid], $byParent, $names, $idCounter) : [];
		$nodes[] = [
			'id' => $idCounter++, 'name' => $names[$cid],
			'href' => 'index.php?route=product/category&path=' . $cid,
			'status' => 1, 'image' => '', 'children' => $children,
		];
	}
	return $nodes;
}

$tree = buildMenuNodes(isset($byParent[0]) ? $byParent[0] : [], $byParent, $names, $idCounter);
array_unshift($tree, [
	'id' => $idCounter++, 'name' => 'Главная', 'href' => 'index.php?route=common/home',
	'status' => 1, 'image' => '', 'children' => [],
]);

$db->query("ALTER TABLE `{$p}module` MODIFY `setting` MEDIUMTEXT NOT NULL");
$json = json_encode(['name' => 'WT Menu', 'status' => '1', 'tree' => $tree], JSON_UNESCAPED_UNICODE);
$stmt = $db->prepare("UPDATE `{$p}module` SET `name` = 'WT Menu', `setting` = ? WHERE `code` = 'wt_menu'");
$stmt->bind_param('s', $json);
$stmt->execute();
echo "WT Menu rebuilt, nodes=" . ($idCounter - 1) . "\nDONE\n";
