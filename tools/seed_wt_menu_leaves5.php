<?php
/**
 * Add 5 arbitrary leaf categories under every mid-level (column) category,
 * then rebuild WT Menu.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)(DB_PORT ?: 3306));
if ($db->connect_error) {
	fwrite(STDERR, $db->connect_error . "\n");
	exit(1);
}
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
		parent_id = '" . (int)$parentId . "',
		`top` = '0',
		`column` = '1',
		sort_order = '" . (int)$sort . "',
		status = '1',
		page_group_links = '',
		noindex = '1',
		date_added = NOW(),
		date_modified = NOW()");
	$id = (int)$db->insert_id;

	foreach ($langIds as $lid => $code) {
		if ($code === 'uk-ua') {
			$name = $nameUk;
		} elseif (strpos($code, 'en') === 0) {
			$name = $nameEn;
		} else {
			$name = $nameRu;
		}
		$name = $db->real_escape_string($name);
		$db->query("INSERT INTO `{$p}category_description` SET
			category_id = '{$id}',
			language_id = '" . (int)$lid . "',
			name = '{$name}',
			description = '',
			meta_title = '{$name}',
			meta_description = '',
			meta_keyword = '',
			meta_h1 = '{$name}'");
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

$pool = [
	['Новинки', 'Новинки', 'New'],
	['Хиты продаж', 'Хіти продажів', 'Bestsellers'],
	['Акции', 'Акції', 'Sale'],
	['Премиум', 'Преміум', 'Premium'],
	['Бюджетные', 'Бюджетні', 'Budget'],
	['Профессиональные', 'Професійні', 'Pro'],
	['Для дома', 'Для дому', 'Home'],
	['Для офиса', 'Для офісу', 'Office'],
	['Компактные', 'Компактні', 'Compact'],
	['Универсальные', 'Універсальні', 'Universal'],
	['С доставкой', 'З доставкою', 'With delivery'],
	['Рекомендуем', 'Рекомендуємо', 'Recommended'],
];

$exclude = '92,93,94,95,96';

// Mid-level = direct children of top-level (parent_id = 0 roots), excluding demo
$r = $db->query("SELECT c.category_id, cd.name
	FROM `{$p}category` c
	INNER JOIN `{$p}category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = 1)
	INNER JOIN `{$p}category` pcat ON (pcat.category_id = c.parent_id AND pcat.parent_id = 0)
	WHERE c.status = 1
	  AND c.category_id NOT IN ($exclude)
	  AND c.parent_id NOT IN ($exclude)
	  AND pcat.category_id NOT IN ($exclude)
	ORDER BY c.parent_id, c.sort_order, cd.name");

$mids = [];
while ($row = $r->fetch_assoc()) {
	$mids[] = $row;
}

$added = 0;
foreach ($mids as $mid) {
	$parentId = (int)$mid['category_id'];
	$sort = 100;
	for ($i = 0; $i < 5; $i++) {
		$item = $pool[($parentId + $i) % count($pool)];
		// make unique per parent
		$nameRu = $item[0] . ' ' . ($i + 1);
		$nameUk = $item[1] . ' ' . ($i + 1);
		$nameEn = $item[2] . ' ' . ($i + 1);
		$id = addCategory($db, $p, $parentId, $nameRu, $nameUk, $nameEn, $langIds, $storeIds, $sort + $i);
		echo "+ {$mid['name']} → {$id} {$nameRu}\n";
		$added++;
	}
}

echo "Added {$added} leaf categories under " . count($mids) . " mid-level groups\n";

// Rebuild WT Menu
$lang = 1;
$byParent = [];
$names = [];
$r = $db->query("SELECT c.category_id, c.parent_id, cd.name
	FROM `{$p}category` c
	INNER JOIN `{$p}category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = {$lang})
	WHERE c.status = 1 AND c.category_id NOT IN ($exclude)
	  AND c.parent_id NOT IN ($exclude)
	ORDER BY c.parent_id, c.sort_order, LCASE(cd.name)");
while ($row = $r->fetch_assoc()) {
	$cid = (int)$row['category_id'];
	$pid = (int)$row['parent_id'];
	$names[$cid] = $row['name'];
	$byParent[$pid][] = $cid;
}

$idCounter = 1;
function buildMenuNodes(array $catIds, array $byParent, array $names, &$idCounter) {
	$nodes = [];
	foreach ($catIds as $cid) {
		$children = [];
		if (!empty($byParent[$cid])) {
			$children = buildMenuNodes($byParent[$cid], $byParent, $names, $idCounter);
		}
		$nodes[] = [
			'id'       => $idCounter++,
			'name'     => $names[$cid],
			'href'     => 'index.php?route=product/category&path=' . $cid,
			'status'   => 1,
			'image'    => '',
			'children' => $children,
		];
	}
	return $nodes;
}

$top = isset($byParent[0]) ? $byParent[0] : [];
$tree = buildMenuNodes($top, $byParent, $names, $idCounter);
array_unshift($tree, [
	'id'       => $idCounter++,
	'name'     => 'Главная',
	'href'     => 'index.php?route=common/home',
	'status'   => 1,
	'image'    => '',
	'children' => [],
]);

$setting = ['name' => 'WT Menu', 'status' => '1', 'tree' => $tree];
$json = json_encode($setting, JSON_UNESCAPED_UNICODE);
$db->query("ALTER TABLE `{$p}module` MODIFY `setting` MEDIUMTEXT NOT NULL");
$stmt = $db->prepare("UPDATE `{$p}module` SET `name` = 'WT Menu', `setting` = ? WHERE `code` = 'wt_menu'");
$stmt->bind_param('s', $json);
$stmt->execute();

echo "WT Menu rebuilt, nodes=" . ($idCounter - 1) . "\nDONE\n";
