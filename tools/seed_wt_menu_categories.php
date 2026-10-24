<?php
/**
 * 1) Add arbitrary nested categories under existing top-level ones
 * 2) Rebuild WT Menu module tree from category hierarchy
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
if (!$langIds) {
	fwrite(STDERR, "No languages\n");
	exit(1);
}

$storeIds = [0];
$r = $db->query("SELECT store_id FROM `{$p}store`");
while ($row = $r->fetch_assoc()) {
	$storeIds[] = (int)$row['store_id'];
}

/** @return int new category_id */
function addCategory(mysqli $db, $p, $parentId, $nameRu, $nameUk, $nameEn, array $langIds, array $storeIds, $sort = 0) {
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

	// path table
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

// Marker: skip if already seeded
$marker = $db->query("SELECT category_id FROM `{$p}category_description` WHERE name = 'ДБЖ / инверторы' LIMIT 1");
if ($marker && $marker->num_rows) {
	echo "Nested categories already exist, skip INSERT\n";
} else {
	$treeSeed = [
		76 => [ // Энергопитание
			['ДБЖ / инверторы', 'ДБЖ / інвертори', 'UPS / inverters', [
				['ИБП', 'ДБЖ', 'UPS'],
				['Инверторы', 'Інвертори', 'Inverters'],
				['Стабилизаторы', 'Стабілізатори', 'Stabilizers'],
			]],
			['Зарядные станции', 'Зарядні станції', 'Power stations', [
				['Портативные станции', 'Портативні станції', 'Portable stations'],
				['Солнечные панели', 'Сонячні панелі', 'Solar panels'],
			]],
			['Батареи', 'Батареї', 'Batteries', [
				['AGM', 'AGM', 'AGM'],
				['GEL', 'GEL', 'GEL'],
				['LiFePO4', 'LiFePO4', 'LiFePO4'],
			]],
		],
		78 => [ // Ноутбуки
			['Ноутбуки', 'Ноутбуки', 'Laptops', [
				['Apple MacBook', 'Apple MacBook', 'Apple MacBook'],
				['ASUS', 'ASUS', 'ASUS'],
				['Lenovo', 'Lenovo', 'Lenovo'],
				['Игровые', 'Ігрові', 'Gaming'],
			]],
			['Компьютеры', 'Комп\'ютери', 'PCs', [
				['Системные блоки', 'Системні блоки', 'Desktops'],
				['Моноблоки', 'Моноблоки', 'All-in-one'],
			]],
			['Мониторы', 'Монітори', 'Monitors', [
				['24"', '24"', '24"'],
				['27"', '27"', '27"'],
				['Игровые', 'Ігрові', 'Gaming'],
			]],
		],
		80 => [ // Смартфоны
			['Смартфоны', 'Смартфони', 'Smartphones', [
				['Apple', 'Apple', 'Apple'],
				['Samsung', 'Samsung', 'Samsung'],
				['Xiaomi', 'Xiaomi', 'Xiaomi'],
			]],
			['Планшеты', 'Планшети', 'Tablets', [
				['Apple', 'Apple', 'Apple'],
				['Samsung', 'Samsung', 'Samsung'],
			]],
			['Аксессуары', 'Аксесуари', 'Accessories', [
				['Чехлы', 'Чохли', 'Cases'],
				['Зарядные', 'Зарядні', 'Chargers'],
				['Павербанки', 'Павербанки', 'Power banks'],
			]],
		],
		81 => [ // ТВ
			['Телевизоры', 'Телевізори', 'TVs', [
				['4K', '4K', '4K'],
				['Smart TV', 'Smart TV', 'Smart TV'],
				['OLED', 'OLED', 'OLED'],
			]],
			['Медиаплееры', 'Медіаплеєри', 'Media players', []],
			['Аудиосистемы', 'Аудіосистеми', 'Audio systems', []],
		],
		91 => [ // Авто
			['Видеорегистраторы', 'Відеореєстратори', 'Dashcams', []],
			['Автозвук', 'Автозвук', 'Car audio', []],
			['Зарядные', 'Зарядні', 'Chargers', []],
			['Держатели', 'Тримачі', 'Holders', []],
		],
		79 => [ // Комплектующие
			['Процессоры', 'Процесори', 'CPUs', []],
			['Видеокарты', 'Відеокарти', 'GPUs', []],
			['Материнские платы', 'Материнські плати', 'Motherboards', []],
			['ОЗУ', 'ОЗП', 'RAM', []],
			['SSD / HDD', 'SSD / HDD', 'SSD / HDD', []],
		],
	];

	$sort = 1;
	foreach ($treeSeed as $parentId => $children) {
		$check = $db->query("SELECT category_id FROM `{$p}category` WHERE category_id = '" . (int)$parentId . "'");
		if (!$check->num_rows) {
			echo "Skip missing parent {$parentId}\n";
			continue;
		}
		foreach ($children as $child) {
			list($ru, $uk, $en, $grand) = $child;
			$cid = addCategory($db, $p, $parentId, $ru, $uk, $en, $langIds, $storeIds, $sort++);
			echo "+ {$parentId} → {$cid} {$ru}\n";
			$gs = 1;
			foreach ($grand as $g) {
				$gid = addCategory($db, $p, $cid, $g[0], $g[1], $g[2], $langIds, $storeIds, $gs++);
				echo "  + {$cid} → {$gid} {$g[0]}\n";
			}
		}
	}
	echo "Categories seeded\n";
}

// Build menu tree from categories (exclude demo root 92)
$lang = 1;
foreach ($langIds as $lid => $code) {
	if ($code === 'ru-ru' || $code === 'ru') {
		$lang = $lid;
		break;
	}
	if ($code === 'uk-ua') {
		$lang = $lid;
	}
}

$r = $db->query("SELECT c.category_id, c.parent_id, cd.name
	FROM `{$p}category` c
	INNER JOIN `{$p}category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = {$lang})
	WHERE c.status = 1 AND c.category_id NOT IN (92,93,94,95,96)
	  AND c.parent_id NOT IN (92,93,94,95,96)
	ORDER BY c.parent_id, c.sort_order, LCASE(cd.name)");

$byParent = [];
$names = [];
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

// Top-level: parent_id = 0, skip demo
$top = isset($byParent[0]) ? $byParent[0] : [];
$tree = buildMenuNodes($top, $byParent, $names, $idCounter);

// Prepend Home
array_unshift($tree, [
	'id'       => $idCounter++,
	'name'     => 'Главная',
	'href'     => 'index.php?route=common/home',
	'status'   => 1,
	'image'    => '',
	'children' => [],
]);

$setting = [
	'name'   => 'WT Menu',
	'status' => '1',
	'tree'   => $tree,
];

$json = $db->real_escape_string(json_encode($setting, JSON_UNESCAPED_UNICODE));
$db->query("UPDATE `{$p}module` SET `name` = 'WT Menu', `setting` = '{$json}' WHERE `code` = 'wt_menu'");

echo "WT Menu updated, top items=" . count($tree) . ", nodes=" . ($idCounter - 1) . "\n";
echo "DONE\n";
