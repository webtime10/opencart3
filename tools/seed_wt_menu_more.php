<?php
/**
 * Add more mid-level categories under every top-level parent,
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
	// skip if same name already under parent
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

// Extra mid-level groups per top category (parent_id => list of [ru, uk, en, grandchildren[]])
$extra = [
	76 => [ // Энергопитание — already has some; add more columns
		['Аксессуары', 'Аксесуари', 'Accessories', [['Кабели', 'Кабелі', 'Cables'], ['Переходники', 'Перехідники', 'Adapters'], ['Розетки', 'Розетки', 'Sockets']]],
		['Генераторы', 'Генератори', 'Generators', [['Бензиновые', 'Бензинові', 'Petrol'], ['Дизельные', 'Дизельні', 'Diesel']]],
	],
	77 => [ // Товары для детей
		['Игрушки', 'Іграшки', 'Toys', [['Конструкторы', 'Конструктори', 'Building sets'], ['Мягкие', 'М\'які', 'Soft toys']]],
		['Детские гаджеты', 'Дитячі гаджети', 'Kids gadgets', [['Планшеты', 'Планшети', 'Tablets'], ['Часы', 'Годинники', 'Watches']]],
		['Обучение', 'Навчання', 'Learning', [['Робототехника', 'Робототехніка', 'Robotics'], ['Книги', 'Книги', 'Books']]],
		['Творчество', 'Творчість', 'Creative', [['Рисование', 'Малювання', 'Drawing'], ['Лепка', 'Ліплення', 'Modeling']]],
	],
	78 => [
		['Периферия', 'Периферія', 'Peripherals', [['Клавиатуры', 'Клавіатури', 'Keyboards'], ['Мыши', 'Миші', 'Mice'], ['Наушники', 'Навушники', 'Headsets']]],
		['Сумки и рюкзаки', 'Сумки і рюкзаки', 'Bags', [['Для ноутбука', 'Для ноутбука', 'Laptop bags'], ['Рюкзаки', 'Рюкзаки', 'Backpacks']]],
	],
	79 => [
		['Блоки питания', 'Блоки живлення', 'PSU', [['500–650W', '500–650W', '500–650W'], ['750W+', '750W+', '750W+']]],
		['Корпуса', 'Корпуси', 'Cases', [['Midi-Tower', 'Midi-Tower', 'Midi-Tower'], ['Mini-ITX', 'Mini-ITX', 'Mini-ITX']]],
		['Охлаждение', 'Охолодження', 'Cooling', [['Кулеры', 'Кулери', 'Coolers'], ['СЖО', 'СВО', 'AIO']]],
	],
	80 => [
		['Смарт-часы', 'Смарт-годинники', 'Smartwatches', [['Apple Watch', 'Apple Watch', 'Apple Watch'], ['Samsung', 'Samsung', 'Samsung'], ['Xiaomi', 'Xiaomi', 'Xiaomi']]],
		['Наушники', 'Навушники', 'Earbuds', [['TWS', 'TWS', 'TWS'], ['Накладные', 'Накладні', 'Over-ear']]],
	],
	81 => [
		['Кронштейны', 'Кронштейни', 'Mounts', [['Настенные', 'Настінні', 'Wall'], ['Потолочные', 'Стельові', 'Ceiling']]],
		['Кабели TV', 'Кабелі TV', 'TV cables', [['HDMI', 'HDMI', 'HDMI'], ['Optical', 'Optical', 'Optical']]],
	],
	82 => [ // Геймеры
		['Консоли', 'Консолі', 'Consoles', [['PlayStation', 'PlayStation', 'PlayStation'], ['Xbox', 'Xbox', 'Xbox'], ['Nintendo', 'Nintendo', 'Nintendo']]],
		['Игры', 'Ігри', 'Games', [['PS5', 'PS5', 'PS5'], ['Xbox', 'Xbox', 'Xbox'], ['PC', 'PC', 'PC']]],
		['Геймерская периферия', 'Геймерська периферія', 'Gaming gear', [['Геймпады', 'Геймпади', 'Controllers'], ['Кресла', 'Крісла', 'Chairs']]],
		['Стриминг', 'Стрімінг', 'Streaming', [['Микрофоны', 'Мікрофони', 'Mics'], ['Камеры', 'Камери', 'Cams']]],
	],
	83 => [ // Офис
		['Принтеры', 'Принтери', 'Printers', [['Лазерные', 'Лазерні', 'Laser'], ['Струйные', 'Струменеві', 'Inkjet']]],
		['МФУ', 'БФП', 'MFPs', [['Ч/Б', 'Ч/Б', 'B&W'], ['Цветные', 'Кольорові', 'Color']]],
		['Расходники', 'Витратники', 'Consumables', [['Картриджи', 'Картриджі', 'Cartridges'], ['Бумага', 'Папір', 'Paper']]],
		['Оргтехника', 'Оргтехніка', 'Office gear', [['Шредеры', 'Шредери', 'Shredders'], ['Ламинаторы', 'Ламінатори', 'Laminators']]],
	],
	84 => [ // Сеть
		['Роутеры', 'Роутери', 'Routers', [['Wi-Fi 6', 'Wi-Fi 6', 'Wi-Fi 6'], ['Mesh', 'Mesh', 'Mesh']]],
		['Коммутаторы', 'Комутатори', 'Switches', [['Неуправляемые', 'Некеровані', 'Unmanaged'], ['Управляемые', 'Керовані', 'Managed']]],
		['Кабели сети', 'Кабелі мережі', 'Network cables', [['Cat5e', 'Cat5e', 'Cat5e'], ['Cat6', 'Cat6', 'Cat6']]],
		['Точки доступа', 'Точки доступу', 'Access points', [['Потолочные', 'Стельові', 'Ceiling'], ['Уличные', 'Вуличні', 'Outdoor']]],
	],
	85 => [ // Аудио
		['Наушники', 'Навушники', 'Headphones', [['TWS', 'TWS', 'TWS'], ['Накладные', 'Накладні', 'Over-ear']]],
		['Колонки', 'Колонки', 'Speakers', [['Портативные', 'Портативні', 'Portable'], ['Саундбары', 'Саундбари', 'Soundbars']]],
		['Микрофоны', 'Мікрофони', 'Microphones', [['USB', 'USB', 'USB'], ['Студийные', 'Студійні', 'Studio']]],
		['Hi-Fi', 'Hi-Fi', 'Hi-Fi', [['Усилители', 'Підсилювачі', 'Amps'], ['Проигрыватели', 'Програвачі', 'Players']]],
	],
	86 => [ // Гаджеты
		['Смарт-часы', 'Смарт-годинники', 'Smartwatches', [['Фитнес', 'Фітнес', 'Fitness'], ['Премиум', 'Преміум', 'Premium']]],
		['Электротранспорт', 'Електротранспорт', 'E-transport', [['Самокаты', 'Самокати', 'Scooters'], ['Гироборды', 'Гіроборди', 'Hoverboards']]],
		['VR / AR', 'VR / AR', 'VR / AR', [['Очки', 'Окуляри', 'Headsets'], ['Аксессуары', 'Аксесуари', 'Accessories']]],
		['Умные аксессуары', 'Розумні аксесуари', 'Smart accessories', [['Трекеры', 'Трекери', 'Trackers'], ['Кольца', 'Кільця', 'Rings']]],
	],
	87 => [ // Бытовая техника
		['Кухня', 'Кухня', 'Kitchen', [['Холодильники', 'Холодильники', 'Fridges'], ['Плиты', 'Плити', 'Stoves'], ['Микроволновки', 'Мікрохвильовки', 'Microwaves']]],
		['Климат', 'Клімат', 'Climate', [['Кондиционеры', 'Кондиціонери', 'ACs'], ['Обогреватели', 'Обігрівачі', 'Heaters']]],
		['Уборка', 'Прибирання', 'Cleaning', [['Пылесосы', 'Пилососи', 'Vacuums'], ['Роботы', 'Роботи', 'Robot vacuums']]],
		['Стирка', 'Прання', 'Laundry', [['Стиральные', 'Пральні', 'Washers'], ['Сушильные', 'Сушильні', 'Dryers']]],
	],
	88 => [ // Умный дом
		['Камеры', 'Камери', 'Cameras', [['IP камеры', 'IP камери', 'IP cams'], ['Видеодомофоны', 'Відеодомофони', 'Door phones']]],
		['Датчики', 'Датчики', 'Sensors', [['Движения', 'Руху', 'Motion'], ['Дыма', 'Диму', 'Smoke']]],
		['Освещение', 'Освітлення', 'Lighting', [['Лампы', 'Лампи', 'Bulbs'], ['Ленты', 'Стрічки', 'Strips']]],
		['Замки и реле', 'Замки і реле', 'Locks & relays', [['Умные замки', 'Розумні замки', 'Smart locks'], ['Реле', 'Реле', 'Relays']]],
	],
	89 => [ // Инструменты
		['Электроинструмент', 'Електроінструмент', 'Power tools', [['Дрели', 'Дрилі', 'Drills'], ['Шуруповерты', 'Шурупокрути', 'Drivers']]],
		['Измерительные', 'Вимірювальні', 'Measuring', [['Мультиметры', 'Мультиметри', 'Multimeters'], ['Лазеры', 'Лазери', 'Lasers']]],
		['Ручной инструмент', 'Ручний інструмент', 'Hand tools', [['Наборы', 'Набори', 'Sets'], ['Ключи', 'Ключі', 'Wrenches']]],
		['Садовый', 'Садовий', 'Garden', [['Газонокосилки', 'Газонокосарки', 'Mowers'], ['Триммеры', 'Тримери', 'Trimmers']]],
	],
	90 => [ // Фото
		['Камеры', 'Камери', 'Cameras', [['Фотоаппараты', 'Фотоапарати', 'Cameras'], ['Экшн-камеры', 'Екшн-камери', 'Action cams']]],
		['Оптика', 'Оптика', 'Optics', [['Объективы', 'Об\'єктиви', 'Lenses'], ['Фильтры', 'Фільтри', 'Filters']]],
		['Свет', 'Світло', 'Lighting', [['Вспышки', 'Спалахи', 'Flashes'], ['Софтбоксы', 'Софтбокси', 'Softboxes']]],
		['Штативы', 'Штативи', 'Tripods', [['Напольные', 'Підлогові', 'Floor'], ['Настольные', 'Настільні', 'Desktop']]],
	],
	91 => [
		['Автоаккумуляторы', 'Автоакумулятори', 'Car batteries', [['AGM', 'AGM', 'AGM'], ['GEL', 'GEL', 'GEL']]],
		['Автогаджеты', 'Автогаджети', 'Car gadgets', [['GPS', 'GPS', 'GPS'], ['FM-модуляторы', 'FM-модулятори', 'FM transmitters']]],
	],
];

$sortBase = 50;
foreach ($extra as $parentId => $children) {
	$check = $db->query("SELECT category_id FROM `{$p}category` WHERE category_id = '" . (int)$parentId . "' AND status = 1");
	if (!$check->num_rows) {
		echo "skip parent {$parentId}\n";
		continue;
	}
	$sort = $sortBase;
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

// Rebuild WT Menu tree
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

$setting = [
	'name'   => 'WT Menu',
	'status' => '1',
	'tree'   => $tree,
];
$json = $db->real_escape_string(json_encode($setting, JSON_UNESCAPED_UNICODE));
$db->query("UPDATE `{$p}module` SET `name` = 'WT Menu', `setting` = '{$json}' WHERE `code` = 'wt_menu'");

echo "WT Menu rebuilt, top=" . count($tree) . ", nodes=" . ($idCounter - 1) . "\nDONE\n";
