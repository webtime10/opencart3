<?php
/**
 * Add sample 4th-level categories under existing L3 leaves (depth=2 path),
 * then rebuild WT Menu (4 levels: top → col → item → sub).
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)(DB_PORT ?: 3306));
$db->set_charset('utf8mb4');
$p = DB_PREFIX;

$langIds = [];
$r = $db->query("SELECT language_id, code FROM `{$p}language`");
while ($row = $r->fetch_assoc()) {
	$langIds[(int)$row['language_id']] = $row['code'];
}
$storeIds = [0];
$r = $db->query("SELECT store_id FROM `{$p}store`");
while ($row = $r->fetch_assoc()) {
	$storeIds[] = (int)$row['store_id'];
}

function addCategory(mysqli $db, $p, $parentId, $nameRu, $nameUk, $nameEn, array $langIds, array $storeIds, $sort = 0) {
	$check = $db->query("SELECT c.category_id FROM `{$p}category` c
		INNER JOIN `{$p}category_description` cd ON (c.category_id = cd.category_id)
		WHERE c.parent_id = '" . (int)$parentId . "' AND cd.name = '" . $db->real_escape_string($nameRu) . "' LIMIT 1");
	if ($check && $check->num_rows) {
		return (int)$check->fetch_assoc()['category_id'];
	}

	$db->query("INSERT INTO `{$p}category` SET
		parent_id = '" . (int)$parentId . "', `top` = '0', `column` = '1',
		sort_order = '" . (int)$sort . "', status = '1', page_group_links = '', noindex = '1',
		date_added = NOW(), date_modified = NOW()");
	$id = (int)$db->insert_id;

	foreach ($langIds as $lid => $code) {
		$name = ($code === 'uk-ua') ? $nameUk : ((strpos($code, 'en') === 0) ? $nameEn : $nameRu);
		$name = $db->real_escape_string($name);
		$db->query("INSERT INTO `{$p}category_description` SET
			category_id = '{$id}', language_id = '" . (int)$lid . "',
			name = '{$name}', description = '', meta_title = '{$name}',
			meta_description = '', meta_keyword = '', meta_h1 = '{$name}'");
	}
	foreach ($storeIds as $sid) {
		$db->query("INSERT IGNORE INTO `{$p}category_to_store` SET category_id = '{$id}', store_id = '" . (int)$sid . "'");
	}
	$level = 0;
	$path = [];
	if ($parentId) {
		$pr = $db->query("SELECT path_id, level FROM `{$p}category_path` WHERE category_id = '" . (int)$parentId . "' ORDER BY level ASC");
		while ($row = $pr->fetch_assoc()) {
			$path[] = [(int)$row['path_id'], (int)$row['level']];
			$level = (int)$row['level'] + 1;
		}
	}
	foreach ($path as $seg) {
		$db->query("INSERT INTO `{$p}category_path` SET category_id = '{$id}', path_id = '{$seg[0]}', level = '{$seg[1]}'");
	}
	$db->query("INSERT INTO `{$p}category_path` SET category_id = '{$id}', path_id = '{$id}', level = '{$level}'");
	return $id;
}

$exclude = '92,93,94,95,96';
$l4names = [
	['Линейка A', 'Лінійка A', 'Line A'],
	['Линейка B', 'Лінійка B', 'Line B'],
	['Линейка C', 'Лінійка C', 'Line C'],
];

// L3 = categories whose parent is mid (mid.parent is top/0), and that currently have no children
// Path: top(0) → mid → l3  => l3 has level 2 in category_path (0-based levels: top=0? actually path level for self)
// In OC: root cat path level 0, child level 1, grandchild level 2
$r = $db->query("SELECT c.category_id, cd.name, MAX(cp.level) AS depth
	FROM `{$p}category` c
	INNER JOIN `{$p}category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = 1)
	INNER JOIN `{$p}category_path` cp ON (cp.category_id = c.category_id)
	WHERE c.status = 1 AND c.category_id NOT IN ($exclude) AND c.parent_id NOT IN ($exclude)
	GROUP BY c.category_id, cd.name
	HAVING depth = 2
	ORDER BY c.category_id");

$added = 0;
while ($row = $r->fetch_assoc()) {
	$parentId = (int)$row['category_id'];
	// skip if already has children
	$ch = $db->query("SELECT category_id FROM `{$p}category` WHERE parent_id = '{$parentId}' AND status = 1 LIMIT 1");
	if ($ch->num_rows) {
		continue;
	}
	// skip generic filler names like "Новинки 1"
	if (preg_match('/\s\d+$/u', $row['name'])) {
		continue;
	}
	for ($i = 0; $i < 3; $i++) {
		$n = $l4names[$i];
		$id = addCategory($db, $p, $parentId, $n[0], $n[1], $n[2], $langIds, $storeIds, $i + 1);
		echo "+ {$row['name']} → {$id} {$n[0]}\n";
		$added++;
	}
}
echo "Added {$added} L4 categories\n";

// Rebuild menu
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
