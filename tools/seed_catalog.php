<?php
/**
 * Seed catalog: uk-ua language, 16 menu categories, 20 products each, photos, attributes.
 * Run: php tools/seed_catalog.php
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

function q($m, $sql) {
	if (!$m->query($sql)) {
		throw new RuntimeException($m->error . "\nSQL: " . $sql);
	}
	return $m;
}

function esc($m, $s) {
	return $m->real_escape_string((string)$s);
}

echo "== 1) Ukrainian language ==\n";
$r = $m->query("SELECT language_id FROM {$p}language WHERE code='uk-ua'");
if ($row = $r->fetch_assoc()) {
	$ukId = (int)$row['language_id'];
	q($m, "UPDATE {$p}language SET status=1, directory='uk-ua', name='Ukrainian' WHERE language_id={$ukId}");
	echo "uk-ua exists id={$ukId}\n";
} else {
	q($m, "INSERT INTO {$p}language SET name='Ukrainian', code='uk-ua', locale='uk_UA.UTF-8,uk_UA,uk-ua,ukrainian', image='uk-ua.png', directory='uk-ua', sort_order=3, status=1");
	$ukId = (int)$m->insert_id;
	echo "uk-ua inserted id={$ukId}\n";
}

$ruId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='ru-ru'")->fetch_assoc()['language_id'];
$enId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='en-gb'")->fetch_assoc()['language_id'];
$langs = [$ruId, $enId, $ukId];

// stock status for en/uk
$stockMap = [
	7 => ['en' => 'In Stock', 'uk' => 'В наявності', 'ru' => 'В наличии'],
	8 => ['en' => 'Pre-Order', 'uk' => 'Передзамовлення', 'ru' => 'Предзаказ'],
	5 => ['en' => 'Out Of Stock', 'uk' => 'Немає в наявності', 'ru' => 'Нет в наличии'],
	6 => ['en' => '2-3 Days', 'uk' => 'Очікування 2-3 дні', 'ru' => 'Ожидание 2-3 дня'],
];
foreach ($stockMap as $sid => $names) {
	foreach ([$enId => $names['en'], $ukId => $names['uk'], $ruId => $names['ru']] as $lid => $name) {
		$exists = $m->query("SELECT 1 FROM {$p}stock_status WHERE stock_status_id={$sid} AND language_id={$lid}")->num_rows;
		if (!$exists) {
			q($m, "INSERT INTO {$p}stock_status SET stock_status_id={$sid}, language_id={$lid}, name='" . esc($m, $name) . "'");
		}
	}
}

echo "== 2) wt_lang ==\n";
$wt = [
	'en-gb' => ['status' => 1, 'prefix' => '', 'hreflang' => 'en', 'default' => 1],
	'ru-ru' => ['status' => 1, 'prefix' => 'ru', 'hreflang' => 'ru', 'default' => 0],
	'uk-ua' => ['status' => 1, 'prefix' => 'ua', 'hreflang' => 'uk', 'default' => 0],
];
$json = esc($m, json_encode($wt, JSON_UNESCAPED_UNICODE));
$chk = $m->query("SELECT setting_id FROM {$p}setting WHERE store_id=0 AND `key`='module_wt_lang_language'");
if ($chk->num_rows) {
	q($m, "UPDATE {$p}setting SET value='{$json}' WHERE store_id=0 AND `key`='module_wt_lang_language'");
} else {
	q($m, "INSERT INTO {$p}setting SET store_id=0, code='module_wt_lang', `key`='module_wt_lang_language', value='{$json}', serialized=1");
}
q($m, "UPDATE {$p}setting SET value='1' WHERE store_id=0 AND `key`='module_wt_lang_status'");
echo "wt_lang: en default, ru=/ru, ua=/ua\n";

echo "== 3) Attributes ==\n";
$agId = 0;
$r = $m->query("SELECT attribute_group_id FROM {$p}attribute_group_description WHERE name='Specs' AND language_id={$enId} LIMIT 1");
if ($row = $r->fetch_assoc()) {
	$agId = (int)$row['attribute_group_id'];
} else {
	q($m, "INSERT INTO {$p}attribute_group SET sort_order=10");
	$agId = (int)$m->insert_id;
	$agNames = [$ruId => 'Характеристики', $enId => 'Specs', $ukId => 'Характеристики'];
	foreach ($agNames as $lid => $name) {
		q($m, "INSERT INTO {$p}attribute_group_description SET attribute_group_id={$agId}, language_id={$lid}, name='" . esc($m, $name) . "'");
	}
}

$attrDefs = [
	'brand' => [$ruId => 'Бренд', $enId => 'Brand', $ukId => 'Бренд'],
	'color' => [$ruId => 'Цвет', $enId => 'Color', $ukId => 'Колір'],
	'warranty' => [$ruId => 'Гарантия', $enId => 'Warranty', $ukId => 'Гарантія'],
	'material' => [$ruId => 'Материал', $enId => 'Material', $ukId => 'Матеріал'],
];
$attrIds = [];
$sort = 1;
foreach ($attrDefs as $key => $names) {
	$enName = $names[$enId];
	$r = $m->query("SELECT a.attribute_id FROM {$p}attribute a JOIN {$p}attribute_description ad ON a.attribute_id=ad.attribute_id WHERE a.attribute_group_id={$agId} AND ad.language_id={$enId} AND ad.name='" . esc($m, $enName) . "' LIMIT 1");
	if ($row = $r->fetch_assoc()) {
		$attrIds[$key] = (int)$row['attribute_id'];
	} else {
		q($m, "INSERT INTO {$p}attribute SET attribute_group_id={$agId}, sort_order={$sort}");
		$aid = (int)$m->insert_id;
		foreach ($names as $lid => $name) {
			q($m, "INSERT INTO {$p}attribute_description SET attribute_id={$aid}, language_id={$lid}, name='" . esc($m, $name) . "'");
		}
		$attrIds[$key] = $aid;
	}
	$sort++;
}
echo "attrs: " . implode(',', array_keys($attrIds)) . "\n";

echo "== 4) Collect images ==\n";
$seedDir = $root . '/image/catalog/seed';
if (!is_dir($seedDir)) {
	mkdir($seedDir, 0777, true);
}

function collectImages($dir) {
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

$fotoImgs = collectImages($root . '/фото');
$demoImgs = collectImages($root . '/image/catalog/demo');
echo "foto=" . count($fotoImgs) . " demo=" . count($demoImgs) . "\n";

function copySeedImage($src, $seedDir, $slug, $index) {
	$ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
	$relDir = 'catalog/seed/' . $slug;
	$absDir = $seedDir . '/' . $slug;
	if (!is_dir($absDir)) {
		mkdir($absDir, 0777, true);
	}
	$name = 'p' . str_pad((string)$index, 2, '0', STR_PAD_LEFT) . '.' . $ext;
	$dest = $absDir . '/' . $name;
	if (!@copy($src, $dest)) {
		throw new RuntimeException('copy failed: ' . $src . ' -> ' . $dest);
	}
	return $relDir . '/' . $name;
}

echo "== 5) Categories ==\n";
$categories = [
	['slug' => 'power', 'ru' => 'Энергопитание', 'en' => 'Power & Charging', 'uk' => 'Енергоживлення', 'pool' => 'demo'],
	['slug' => 'kids', 'ru' => 'Товары для детей', 'en' => 'Kids Products', 'uk' => 'Товари для дітей', 'pool' => 'demo'],
	['slug' => 'laptops', 'ru' => 'Ноутбуки и компьютеры', 'en' => 'Laptops & Computers', 'uk' => 'Ноутбуки і комп\'ютери', 'pool' => 'demo'],
	['slug' => 'parts', 'ru' => 'Компьютерные комплектующие', 'en' => 'PC Components', 'uk' => 'Комп\'ютерні комплектуючі', 'pool' => 'demo'],
	['slug' => 'phones', 'ru' => 'Смартфоны и планшеты', 'en' => 'Smartphones & Tablets', 'uk' => 'Смартфони та планшети', 'pool' => 'foto'],
	['slug' => 'tv', 'ru' => 'Телевизоры и мультимедиа', 'en' => 'TVs & Multimedia', 'uk' => 'Телевізори і мультимедіа', 'pool' => 'demo'],
	['slug' => 'gaming', 'ru' => 'Товары для геймеров', 'en' => 'Gaming', 'uk' => 'Товари для геймерів', 'pool' => 'demo'],
	['slug' => 'office', 'ru' => 'Всё для печати и офиса', 'en' => 'Office & Print', 'uk' => 'Все для друку та офісу', 'pool' => 'demo'],
	['slug' => 'network', 'ru' => 'Сетевое оборудование', 'en' => 'Networking', 'uk' => 'Мережеве обладнання', 'pool' => 'demo'],
	['slug' => 'audio', 'ru' => 'Аудио', 'en' => 'Audio', 'uk' => 'Аудіо', 'pool' => 'demo'],
	['slug' => 'gadgets', 'ru' => 'Гаджеты', 'en' => 'Gadgets', 'uk' => 'Гаджети', 'pool' => 'demo'],
	['slug' => 'appliances', 'ru' => 'Бытовая техника', 'en' => 'Home Appliances', 'uk' => 'Побутова техніка', 'pool' => 'demo'],
	['slug' => 'smarthome', 'ru' => 'Умный дом и охрана', 'en' => 'Smart Home & Security', 'uk' => 'Розумний дім та охорона', 'pool' => 'demo'],
	['slug' => 'tools', 'ru' => 'Инструменты', 'en' => 'Tools', 'uk' => 'Інструменти', 'pool' => 'demo'],
	['slug' => 'photo', 'ru' => 'Фото и видео', 'en' => 'Photo & Video', 'uk' => 'Фото та відео', 'pool' => 'demo'],
	['slug' => 'auto', 'ru' => 'Автотовары', 'en' => 'Auto Accessories', 'uk' => 'Автотовари', 'pool' => 'demo'],
];

// Disable old empty-ish category 59 from menu (keep data)
q($m, "UPDATE {$p}category SET status=0, top=0 WHERE category_id=59");

$productNamePools = [
	'power' => [
		'en' => ['GaN Charger 65W', 'Power Bank 20000mAh', 'USB-C Cable 2m', 'Wireless Pad 15W', 'Car Charger Dual', 'MagSafe Stand', 'PD Hub 100W', 'Solar Power Bank', 'Wall Adapter 30W', 'Cable Organizer Kit', 'Laptop Brick 90W', 'Multi Port Dock', 'Travel Adapter Kit', 'Qi2 Charging Stand', 'USB-C to Lightning', 'Battery Pack Slim', 'Desk Charging Station', 'Fast Charge Brick', 'Extension USB Hub', 'Eco Power Strip'],
		'ru' => ['Зарядка GaN 65Вт', 'Пауэрбанк 20000мАч', 'Кабель USB-C 2м', 'Беспроводная панель 15Вт', 'Автозарядка Dual', 'Стенд MagSafe', 'Хаб PD 100Вт', 'Солнечный пауэрбанк', 'Блок 30Вт', 'Органайзер кабелей', 'Блок для ноутбука 90Вт', 'Док Multi Port', 'Тревел-адаптер', 'Стенд Qi2', 'USB-C — Lightning', 'Тонкий аккумулятор', 'Зарядная станция', 'Быстрый блок', 'USB-хаб', 'Сетевой фильтр Eco'],
		'uk' => ['Зарядка GaN 65Вт', 'Павербанк 20000мАш', 'Кабель USB-C 2м', 'Бездротова панель 15Вт', 'Автозарядка Dual', 'Стенд MagSafe', 'Хаб PD 100Вт', 'Сонячний павербанк', 'Блок 30Вт', 'Органайзер кабелів', 'Блок для ноутбука 90Вт', 'Док Multi Port', 'Тревел-адаптер', 'Стенд Qi2', 'USB-C — Lightning', 'Тонкий акумулятор', 'Зарядна станція', 'Швидкий блок', 'USB-хаб', 'Мережевий фільтр Eco'],
	],
	'kids' => [
		'en' => ['Kids Tablet 10"', 'STEM Robot Kit', 'Learning Headphones', 'Interactive Globe', 'Coding Mouse', 'Puzzle Pad', 'Kids Smartwatch', 'Music Keyboard Mini', 'AR Flashcards', 'Soft Controller', 'Junior Camera', 'Math Trainer Pro', 'Story Projector', 'Build Blocks Smart', 'Drawing Tablet Kids', 'RC Mini Car', 'Science Lab Set', 'Language Pen', 'Night Lamp Buddy', 'Educational Console'],
		'ru' => ['Детский планшет 10"', 'STEM робот-набор', 'Наушники для учёбы', 'Интерактивный глобус', 'Кодинг-мышь', 'Пазл-пад', 'Детские смарт-часы', 'Мини-синтезатор', 'AR карточки', 'Мягкий геймпад', 'Детская камера', 'Тренажёр математики', 'Проектор сказок', 'Умный конструктор', 'Графический планшет', 'Мини RC машинка', 'Набор юного учёного', 'Ручка-переводчик', 'Ночник Buddy', 'Обучающая консоль'],
		'uk' => ['Дитячий планшет 10"', 'STEM робот-набір', 'Навушники для навчання', 'Інтерактивний глобус', 'Кодинг-миша', 'Пазл-пад', 'Дитячий смарт-годинник', 'Міні-синтезатор', 'AR картки', 'М\'який геймпад', 'Дитяча камера', 'Тренажер математики', 'Проектор казок', 'Розумний конструктор', 'Графічний планшет', 'Міні RC машинка', 'Набір юного науковця', 'Ручка-перекладач', 'Нічник Buddy', 'Навчальна консоль'],
	],
	'laptops' => [
		'en' => ['Ultrabook 14 Air', 'Gaming Laptop 15', 'Business Notebook Pro', '2-in-1 Convertible', 'Workstation 16', 'Student Laptop Lite', 'Creator Laptop OLED', 'Mini PC Cube', 'All-in-One 24', 'Chromebook Go', 'Laptop Sleeve Kit', 'Cooling Pad X', 'USB-C Dock Pro', 'Mechanical KB Travel', 'Laptop Stand Alu', 'Wireless Mouse Silent', 'Webcam 1080p', 'Portable SSD 1TB', 'Laptop Backpack Tech', 'Monitor Portable 15'],
		'ru' => ['Ультрабук 14 Air', 'Игровой ноутбук 15', 'Бизнес-ноутбук Pro', 'Трансформер 2-в-1', 'Рабочая станция 16', 'Студенческий Lite', 'Ноутбук Creator OLED', 'Мини-ПК Cube', 'Моноблок 24', 'Chromebook Go', 'Чехол для ноутбука', 'Охлаждающая подставка', 'Док USB-C Pro', 'Компактная мехклава', 'Алюм. подставка', 'Тихая мышь', 'Веб-камера 1080p', 'Портативный SSD 1ТБ', 'Рюкзак Tech', 'Портативный монитор 15'],
		'uk' => ['Ультрабук 14 Air', 'Ігровий ноутбук 15', 'Бізнес-ноутбук Pro', 'Трансформер 2-в-1', 'Робоча станція 16', 'Студентський Lite', 'Ноутбук Creator OLED', 'Міні-ПК Cube', 'Моноблок 24', 'Chromebook Go', 'Чохол для ноутбука', 'Охолоджувальна підставка', 'Док USB-C Pro', 'Компактна мехклава', 'Алюм. підставка', 'Тиха миша', 'Веб-камера 1080p', 'Портативний SSD 1ТБ', 'Рюкзак Tech', 'Портативний монітор 15'],
	],
	'parts' => [
		'en' => ['Ryzen CPU Box', 'GeForce GPU 12GB', 'B650 Motherboard', 'DDR5 32GB Kit', 'NVMe 2TB Gen4', 'PSU 750W Gold', 'Tower Case Airflow', 'AIO Cooler 240', 'Wi-Fi 6E Card', 'Sound Card DAC', 'SATA SSD 1TB', 'ARGB Fan Pack', 'CPU Thermal Paste', 'PCIe Capture Card', 'RAM RGB 16GB', 'HDD 4TB NAS', 'ITX Motherboard', 'Modular PSU 850W', 'Air Cooler Dual', 'M.2 Heatsink Kit'],
		'ru' => ['Процессор Ryzen BOX', 'Видеокарта 12ГБ', 'Материнка B650', 'DDR5 32ГБ комплект', 'NVMe 2ТБ Gen4', 'БП 750Вт Gold', 'Корпус Airflow', 'СЖО 240', 'Адаптер Wi-Fi 6E', 'Звуковая карта DAC', 'SATA SSD 1ТБ', 'Набор вентиляторов ARGB', 'Термопаста', 'Карта захвата PCIe', 'ОЗУ RGB 16ГБ', 'HDD 4ТБ NAS', 'Материнка ITX', 'Модульный БП 850Вт', 'Кулер Dual Tower', 'Радиатор M.2'],
		'uk' => ['Процесор Ryzen BOX', 'Відеокарта 12ГБ', 'Материнка B650', 'DDR5 32ГБ комплект', 'NVMe 2ТБ Gen4', 'ЖИВЛЕННЯ 750Вт Gold', 'Корпус Airflow', 'СВО 240', 'Адаптер Wi-Fi 6E', 'Звукова карта DAC', 'SATA SSD 1ТБ', 'Набір вентиляторів ARGB', 'Термопаста', 'Карта захоплення PCIe', 'ОЗП RGB 16ГБ', 'HDD 4ТБ NAS', 'Материнка ITX', 'Модульний БЖ 850Вт', 'Кулер Dual Tower', 'Радіатор M.2'],
	],
	'phones' => [
		'en' => ['iPhone 17 Pro 1TB Silver', 'iPhone 17 Pro Natural', 'iPhone 17 256GB Sage', 'iPhone 17 256GB Lavender', 'iPhone 17 Mist Blue', 'iPhone 16 Pro Desert', 'Galaxy S25 Ultra', 'Galaxy A56 5G', 'Pixel 9 Pro', 'Pixel 9a', 'Xiaomi 15 Pro', 'Redmi Note 14', 'Nothing Phone 3', 'OnePlus 13', 'Motorola Edge 50', 'Honor Magic 7', 'Oppo Find X8', 'Vivo X200', 'Realme GT 7', 'Tablet Air 11'],
		'ru' => ['iPhone 17 Pro 1ТБ Silver', 'iPhone 17 Pro Natural', 'iPhone 17 256ГБ Sage', 'iPhone 17 256ГБ Lavender', 'iPhone 17 Mist Blue', 'iPhone 16 Pro Desert', 'Galaxy S25 Ultra', 'Galaxy A56 5G', 'Pixel 9 Pro', 'Pixel 9a', 'Xiaomi 15 Pro', 'Redmi Note 14', 'Nothing Phone 3', 'OnePlus 13', 'Motorola Edge 50', 'Honor Magic 7', 'Oppo Find X8', 'Vivo X200', 'Realme GT 7', 'Планшет Air 11'],
		'uk' => ['iPhone 17 Pro 1ТБ Silver', 'iPhone 17 Pro Natural', 'iPhone 17 256ГБ Sage', 'iPhone 17 256ГБ Lavender', 'iPhone 17 Mist Blue', 'iPhone 16 Pro Desert', 'Galaxy S25 Ultra', 'Galaxy A56 5G', 'Pixel 9 Pro', 'Pixel 9a', 'Xiaomi 15 Pro', 'Redmi Note 14', 'Nothing Phone 3', 'OnePlus 13', 'Motorola Edge 50', 'Honor Magic 7', 'Oppo Find X8', 'Vivo X200', 'Realme GT 7', 'Планшет Air 11'],
	],
	'tv' => [
		'en' => ['OLED TV 55"', 'QLED TV 65"', 'Smart TV 43" HD', 'Soundbar 3.1', 'Streaming Stick 4K', 'TV Wall Mount', 'HDMI 2.1 Cable', 'Media Player Box', 'Projector Full HD', 'Projector Screen 100"', 'TV Antenna Indoor', 'AV Receiver 7.2', 'Blu-ray Player', 'Remote Universal', 'TV Cleaning Kit', 'Fire Stick Lite', 'Apple TV 4K', 'Gaming Monitor 32"', 'TV LED Backlight', 'Set-Top Box DVB-T2'],
		'ru' => ['OLED ТВ 55"', 'QLED ТВ 65"', 'Smart TV 43" HD', 'Саундбар 3.1', 'Стример 4K', 'Кронштейн для ТВ', 'Кабель HDMI 2.1', 'Медиаплеер', 'Проектор Full HD', 'Экран 100"', 'Комнатная антенна', 'AV-ресивер 7.2', 'Blu-ray плеер', 'Универсальный пульт', 'Набор для чистки ТВ', 'Fire Stick Lite', 'Apple TV 4K', 'Игровой монитор 32"', 'LED-подсветка ТВ', 'Тюнер DVB-T2'],
		'uk' => ['OLED ТВ 55"', 'QLED ТВ 65"', 'Smart TV 43" HD', 'Саундбар 3.1', 'Стрімер 4K', 'Кронштейн для ТВ', 'Кабель HDMI 2.1', 'Медіаплеєр', 'Проєктор Full HD', 'Екран 100"', 'Кімнатна антена', 'AV-ресивер 7.2', 'Blu-ray плеєр', 'Універсальний пульт', 'Набір для чищення ТВ', 'Fire Stick Lite', 'Apple TV 4K', 'Ігровий монітор 32"', 'LED-підсвітка ТВ', 'Тюнер DVB-T2'],
	],
	'gaming' => [
		'en' => ['Wireless Gamepad Pro', 'Racing Wheel Kit', 'Gaming Headset 7.1', 'RGB Keyboard TKL', 'Gaming Mouse 8K', 'Mousepad XL', 'Capture Card 4K', 'Console Stand Dual', 'Gaming Chair Lite', 'VR Headset Bundle', 'Streaming Mic Arm', 'Ring Light Stream', 'Gamepad Charger Dock', 'FPS Trigger Grips', 'Console Skin Pack', 'Gaming Glasses Blue', 'Headset Stand RGB', 'Thumb Grip Set', 'Portable Console Case', 'LED Strip Game Desk'],
		'ru' => ['Беспроводной геймпад Pro', 'Руль с педалями', 'Гарнитура 7.1', 'Клавиатура TKL RGB', 'Мышь 8K', 'Коврик XL', 'Карта захвата 4K', 'Подставка под консоль', 'Игровое кресло Lite', 'VR набор', 'Пантограф для микрофона', 'Кольцевая лампа', 'Док зарядки геймпадов', 'Триггеры FPS', 'Скины для консоли', 'Очки от синего света', 'Подставка под гарнитуру', 'Накладки на стики', 'Чехол для консоли', 'LED лента для стола'],
		'uk' => ['Бездротовий геймпад Pro', 'Кермо з педалями', 'Гарнітура 7.1', 'Клавіатура TKL RGB', 'Миша 8K', 'Килимок XL', 'Карта захоплення 4K', 'Підставка під консоль', 'Ігрове крісло Lite', 'VR набір', 'Пантограф для мікрофона', 'Кільцева лампа', 'Док зарядки геймпадів', 'Тригери FPS', 'Скіни для консолі', 'Окуляри від синього світла', 'Підставка під гарнітуру', 'Накладки на стіки', 'Чохол для консолі', 'LED стрічка для столу'],
	],
	'office' => [
		'en' => ['Laser Printer Mono', 'Ink Tank Printer', 'Document Scanner', 'Label Printer', 'Toner Cartridge BK', 'Paper A4 500', 'Stapler Electric', 'Desk Organizer Set', 'Webcam Conference', 'USB Headset Office', 'Whiteboard Magnetic', 'Laminator A4', 'Shredder Cross-Cut', 'Binding Machine', 'Presentation Remote', 'Ergo Keyboard', 'Vertical Mouse', 'Desk Lamp LED', 'Cable Tray Underdesk', 'Notebook Pack 5'],
		'ru' => ['Лазерный принтер Ч/Б', 'Струйный с СНПЧ', 'Сканер документов', 'Принтер этикеток', 'Картридж BK', 'Бумага A4 500', 'Электростеплер', 'Органайзер на стол', 'Веб-камера для созвонов', 'Офисная гарнитура', 'Магнитная доска', 'Ламинатор A4', 'Шредер', 'Брошюратор', 'Пульт для презентаций', 'Эргоклавиатура', 'Вертикальная мышь', 'Настольная лампа LED', 'Кабель-канал', 'Блокноты 5 шт'],
		'uk' => ['Лазерний принтер Ч/Б', 'Струменевий із СНПЧ', 'Сканер документів', 'Принтер етикеток', 'Картридж BK', 'Папір A4 500', 'Електростеплер', 'Органайзер на стіл', 'Веб-камера для дзвінків', 'Офісна гарнітура', 'Магнітна дошка', 'Ламінатор A4', 'Шредер', 'Брошурувальник', 'Пульт для презентацій', 'Ергоклавіатура', 'Вертикальна миша', 'Настільна лампа LED', 'Кабель-канал', 'Блокноти 5 шт'],
	],
	'network' => [
		'en' => ['Wi-Fi 6 Router', 'Mesh Wi-Fi 3-Pack', 'Gigabit Switch 8p', 'PoE Injector', 'Access Point AC', 'Range Extender', 'NAS 2-Bay', 'Cat6 Cable 10m', 'Fiber Media Converter', 'VPN Router Pro', 'LTE Modem USB', 'Network Rack 9U', 'Patch Panel 24', 'RJ45 Crimp Kit', 'Wi-Fi USB Adapter', 'Managed Switch 16p', 'Powerline Kit AV2', 'Outdoor AP', 'SFP Module Pair', 'Cable Tester Pro'],
		'ru' => ['Роутер Wi-Fi 6', 'Mesh система 3 шт', 'Коммутатор 8 порт', 'PoE инжектор', 'Точка доступа AC', 'Усилитель сигнала', 'NAS 2 диска', 'Кабель Cat6 10м', 'Медиаконвертер', 'VPN-роутер Pro', 'LTE модем USB', 'Стойка 9U', 'Патч-панель 24', 'Кримпер набор', 'USB Wi-Fi адаптер', 'Управляемый свитч 16п', 'Powerline AV2', 'Уличная AP', 'Пара SFP модулей', 'Тестер кабеля'],
		'uk' => ['Роутер Wi-Fi 6', 'Mesh система 3 шт', 'Комутатор 8 портів', 'PoE інжектор', 'Точка доступу AC', 'Підсилювач сигналу', 'NAS 2 диски', 'Кабель Cat6 10м', 'Медіаконвертер', 'VPN-роутер Pro', 'LTE модем USB', 'Стійка 9U', 'Патч-панель 24', 'Кримпер набір', 'USB Wi-Fi адаптер', 'Керований світч 16п', 'Powerline AV2', 'Вулична AP', 'Пара SFP модулів', 'Тестер кабелю'],
	],
	'audio' => [
		'en' => ['ANC Headphones', 'True Wireless Earbuds', 'Studio Monitor Pair', 'USB Microphone', 'Portable Speaker', 'DAC Amp Portable', 'IEMs Stage', 'Audio Interface 2i2', 'Soundbar Mini', 'Bluetooth Receiver', 'Vinyl Turntable', 'Headphone Amp Desk', 'XLR Cable Pair', 'Speaker Stands', 'Karaoke Mic Wireless', 'Car Speaker Set', 'Subwoofer 10"', 'Audio Mixer 4ch', 'Cassette Adapter BT', 'Ear Tips Foam Pack'],
		'ru' => ['Наушники ANC', 'TWS наушники', 'Студийные мониторы', 'USB микрофон', 'Портативная колонка', 'Портативный DAC', 'Сценические IEM', 'Аудиоинтерфейс 2i2', 'Мини-саундбар', 'BT приёмник', 'Виниловый проигрыватель', 'Усилитель для наушников', 'Пара XLR кабелей', 'Стойки для колонок', 'Караоке микрофон', 'Автоакустика', 'Сабвуфер 10"', 'Микшер 4 канала', 'BT адаптер кассеты', 'Пенные амбушюры'],
		'uk' => ['Навушники ANC', 'TWS навушники', 'Студійні монітори', 'USB мікрофон', 'Портативна колонка', 'Портативний DAC', 'Сценічні IEM', 'Аудіоінтерфейс 2i2', 'Міні-саундбар', 'BT приймач', 'Вініловий програвач', 'Підсилювач для навушників', 'Пара XLR кабелів', 'Стійки для колонок', 'Караоке мікрофон', 'Автоакустика', 'Сабвуфер 10"', 'Мікшер 4 канали', 'BT адаптер касети', 'Пінні амбушюри'],
	],
	'gadgets' => [
		'en' => ['Smart Ring Fitness', 'Action Camera 4K', 'E-Reader 7"', 'Translator Device', 'Laser Pointer Presenter', 'Pocket Projector', 'Digital Voice Recorder', 'GPS Tracker Mini', 'Smart Scale Wi-Fi', 'UV Sanitizer Box', 'Portable Fan USB', 'Selfie Stick Tripod', 'Card Reader 4-in-1', 'Magnetic Phone Mount', 'Cable MagSafe Pack', 'Smart Plug Duo', 'LED Desk Clock', 'Wireless Presenter', 'Mini Drone Cam', 'Power Bank Keychain'],
		'ru' => ['Смарт-кольцо', 'Экшн-камера 4K', 'Электронная книга 7"', 'Переводчик', 'Презентер лазерный', 'Карманный проектор', 'Диктофон', 'GPS трекер мини', 'Умные весы Wi-Fi', 'УФ стерилизатор', 'USB вентилятор', 'Селфи-штатив', 'Картридер 4-в-1', 'Магнитный держатель', 'Набор MagSafe кабелей', 'Умная розетка Duo', 'LED часы', 'Беспроводной презентер', 'Мини-дрон с камерой', 'Пауэрбанк-брелок'],
		'uk' => ['Смарт-кільце', 'Екшн-камера 4K', 'Електронна книга 7"', 'Перекладач', 'Презентер лазерний', 'Кишеньковий проєктор', 'Диктофон', 'GPS трекер міні', 'Розумна вага Wi-Fi', 'УФ стерилізатор', 'USB вентилятор', 'Селфі-штатив', 'Картридер 4-в-1', 'Магнітний тримач', 'Набір MagSafe кабелів', 'Розумна розетка Duo', 'LED годинник', 'Бездротовий презентер', 'Міні-дрон з камерою', 'Павербанк-брелок'],
	],
	'appliances' => [
		'en' => ['Robot Vacuum', 'Air Purifier HEPA', 'Humidifier Ultrasonic', 'Espresso Machine', 'Blender Pro 1200', 'Toaster 2-Slice', 'Electric Kettle Steel', 'Microwave 20L', 'Induction Cooktop', 'Hair Dryer Ionic', 'Steam Iron', 'Garment Steamer', 'Dishwasher Compact', 'Fridge Mini Bar', 'Heater Oil Radiator', 'Fan Tower Oscillating', 'Dehumidifier 12L', 'Coffee Grinder Burr', 'Food Processor', 'Hand Mixer Set'],
		'ru' => ['Робот-пылесос', 'Очиститель воздуха HEPA', 'Увлажнитель', 'Кофемашина', 'Блендер Pro 1200', 'Тостер на 2 ломтика', 'Чайник стальной', 'СВЧ 20л', 'Индукционная плита', 'Фен ионный', 'Паровой утюг', 'Отпариватель', 'Посудомойка компакт', 'Мини-холодильник', 'Масляный обогреватель', 'Башенный вентилятор', 'Осушитель 12л', 'Кофемолка жерновая', 'Кухонный комбайн', 'Миксер ручной'],
		'uk' => ['Робот-пилосос', 'Очищувач повітря HEPA', 'Зволожувач', 'Кавомашина', 'Блендер Pro 1200', 'Тостер на 2 скибки', 'Чайник сталевий', 'Мікрохвильовка 20л', 'Індукційна плита', 'Фен іонний', 'Парова праска', 'Відпарювач', 'Посудомийка компакт', 'Міні-холодильник', 'Масляний обігрівач', 'Баштовий вентилятор', 'Осушувач 12л', 'Кавомолка жорнова', 'Кухонний комбайн', 'Міксер ручний'],
	],
	'smarthome' => [
		'en' => ['Smart Hub Zigbee', 'Door Sensor Pack', 'IP Camera Indoor', 'IP Camera Outdoor', 'Smart Bulb Color', 'Smart Switch Wall', 'Thermostat Wi-Fi', 'Smoke Detector Smart', 'Water Leak Sensor', 'Smart Lock Fingerprint', 'Siren Battery', 'Motion Sensor PIR', 'Smart Relay DIN', 'Video Doorbell', 'Curtain Motor Kit', 'IR Blaster Hub', 'Smart Plug Energy', 'Alarm Keypad', 'Floodlight Cam', 'Window Sensor Duo'],
		'ru' => ['Хаб Zigbee', 'Датчики двери', 'IP камера indoor', 'IP камера outdoor', 'Умная лампа RGB', 'Умный выключатель', 'Термостат Wi-Fi', 'Датчик дыма', 'Датчик протечки', 'Замок с отпечатком', 'Сирена', 'Датчик движения PIR', 'Реле DIN', 'Видеодомофон', 'Мотор для штор', 'IR хаб', 'Розетка с учётом', 'Клавиатура сигнализации', 'Камера-прожектор', 'Датчик окна Duo'],
		'uk' => ['Хаб Zigbee', 'Датчики дверей', 'IP камера indoor', 'IP камера outdoor', 'Розумна лампа RGB', 'Розумний вимикач', 'Термостат Wi-Fi', 'Датчик диму', 'Датчик протікання', 'Замок з відбитком', 'Сирена', 'Датчик руху PIR', 'Реле DIN', 'Відеодомофон', 'Мотор для штор', 'IR хаб', 'Розетка з обліком', 'Клавіатура сигналізації', 'Камера-прожектор', 'Датчик вікна Duo'],
	],
	'tools' => [
		'en' => ['Cordless Drill 18V', 'Impact Driver Kit', 'Angle Grinder 125', 'Multitool Oscillating', 'Laser Level 360', 'Tape Measure 8m', 'Tool Bag Pro', 'Screwdriver Bit Set', 'Socket Set 108pcs', 'Heat Gun Digital', 'Soldering Station', 'Clamp Pack 4', 'Utility Knife Set', 'Work Light LED', 'Rotary Tool Kit', 'Jigsaw Corded', 'Circular Saw Mini', 'Torque Wrench', 'Pliers Combo Pack', 'Safety Glasses Kit'],
		'ru' => ['Шуруповёрт 18В', 'Импульсный винтовёрт', 'Болгарка 125', 'Мультитул', 'Лазерный уровень 360', 'Рулетка 8м', 'Сумка для инструмента', 'Биты набор', 'Головки 108 шт', 'Фен технический', 'Паяльная станция', 'Струбцины 4 шт', 'Нож строительный', 'Фонарь LED', 'Гравер набор', 'Лобзик', 'Циркулярка мини', 'Динамометрический ключ', 'Плоскогубцы набор', 'Очки защитные'],
		'uk' => ['Шурупокрут 18В', 'Імпульсний гвинтокрут', 'Болгарка 125', 'Мультитул', 'Лазерний рівень 360', 'Рулетка 8м', 'Сумка для інструменту', 'Біти набір', 'Головки 108 шт', 'Фен технічний', 'Паяльна станція', 'Струбцини 4 шт', 'Ніж будівельний', 'Ліхтар LED', 'Гравер набір', 'Лобзик', 'Циркулярка міні', 'Динамометричний ключ', 'Плоскогубці набір', 'Окуляри захисні'],
	],
	'photo' => [
		'en' => ['Mirrorless Camera Kit', 'DSLR Body Only', 'Prime Lens 50mm', 'Zoom Lens 24-70', 'Tripod Carbon', 'Gimbal 3-Axis', 'LED Softbox Kit', 'Memory Card 256GB', 'Camera Bag Sling', 'ND Filter Set', 'External Flash TTL', 'Mic Shotgun', 'Monitor Field 5"', 'Battery Grip', 'Cleaning Kit Pro', 'Remote Intervalometer', 'Drone Photo Combo', 'Action Mount Pack', 'Reflector 5-in-1', 'Lens Cap Bundle'],
		'ru' => ['Беззеркалка набор', 'Зеркалка body', 'Объектив 50мм', 'Зум 24-70', 'Штатив карбоновый', 'Стабилизатор 3 оси', 'Софтбокс LED', 'Карта памяти 256ГБ', 'Сумка-слинг', 'ND фильтры', 'Вспышка TTL', 'Микрофон shotgun', 'Монитор 5"', 'Батарейный блок', 'Набор для чистки', 'Пульт интервалометр', 'Дрон фото-набор', 'Крепления action', 'Отражатель 5-в-1', 'Крышки объективов'],
		'uk' => ['Бездзеркалка набір', 'Дзеркалка body', 'Об\'єктив 50мм', 'Зум 24-70', 'Штатив карбоновий', 'Стабілізатор 3 осі', 'Софтбокс LED', 'Карта пам\'яті 256ГБ', 'Сумка-слінг', 'ND фільтри', 'Спалах TTL', 'Мікрофон shotgun', 'Монітор 5"', 'Батарейний блок', 'Набір для чищення', 'Пульт інтервалометр', 'Дрон фото-набір', 'Кріплення action', 'Відбивач 5-в-1', 'Кришки об\'єктивів'],
	],
	'auto' => [
		'en' => ['Car Dash Cam Dual', 'OBD2 Scanner BT', 'Phone Mount Mag', 'Tire Inflator Portable', 'Jump Starter 2000A', 'Car Vacuum Mini', 'Seat Organizer', 'USB Hub Car', 'LED Interior Kit', 'Parking Sensor Set', 'Car Air Freshener Ion', 'Wireless CarPlay', 'FM Transmitter', 'Trunk Organizer', 'Steering Cover', 'Floor Mats Set', 'Wiper Blades Pair', 'Oil Funnel Kit', 'Emergency Tool Kit', 'Car Fridge 12V'],
		'ru' => ['Видеорегистратор Dual', 'OBD2 сканер BT', 'Держатель Mag', 'Компрессор портативный', 'Пусковое устройство', 'Автопылесос', 'Органайзер на сиденье', 'USB хаб в авто', 'LED салонный набор', 'Парктроники', 'Ионизатор воздуха', 'Wireless CarPlay', 'FM модулятор', 'Органайзер в багажник', 'Оплётка руля', 'Коврики комплект', 'Дворники пара', 'Воронка для масла', 'Аварийный набор', 'Автохолодильник 12В'],
		'uk' => ['Відеореєстратор Dual', 'OBD2 сканер BT', 'Тримач Mag', 'Компресор портативний', 'Пусковий пристрій', 'Автопилосос', 'Органайзер на сидіння', 'USB хаб в авто', 'LED салонний набір', 'Парктроніки', 'Іонізатор повітря', 'Wireless CarPlay', 'FM модулятор', 'Органайзер у багажник', 'Оплітка керма', 'Килимки комплект', 'Двірники пара', 'Лійка для оливи', 'Аварійний набір', 'Автохолодильник 12В'],
	],
];

$brands = ['Apex', 'Nova', 'Pulse', 'Orbit', 'Vertex', 'Nimbus', 'Quark', 'Lumen', 'Helix', 'Prism'];
$colors = [
	'en' => ['Black', 'Silver', 'Blue', 'White', 'Graphite'],
	'ru' => ['Чёрный', 'Серебристый', 'Синий', 'Белый', 'Графит'],
	'uk' => ['Чорний', 'Сріблястий', 'Синій', 'Білий', 'Графіт'],
];
$materials = [
	'en' => ['Plastic', 'Aluminum', 'Steel', 'Silicone', 'Glass'],
	'ru' => ['Пластик', 'Алюминий', 'Сталь', 'Силикон', 'Стекло'],
	'uk' => ['Пластик', 'Алюміній', 'Сталь', 'Силікон', 'Скло'],
];

$sortCat = 1;
$catIds = [];
foreach ($categories as $cat) {
	// reuse if already created
	$r = $m->query("SELECT c.category_id FROM {$p}category c JOIN {$p}category_description cd ON c.category_id=cd.category_id WHERE cd.language_id={$enId} AND cd.name='" . esc($m, $cat['en']) . "' AND c.status=1 LIMIT 1");
	if ($row = $r->fetch_assoc()) {
		$cid = (int)$row['category_id'];
		$catIds[$cat['slug']] = $cid;
		echo "cat {$cid} {$cat['slug']} exists\n";
		$sortCat++;
		continue;
	}
	q($m, "INSERT INTO {$p}category SET parent_id=0, top=1, `column`=1, sort_order={$sortCat}, status=1, page_group_links='', noindex=0, date_added=NOW(), date_modified=NOW()");
	$cid = (int)$m->insert_id;
	$catIds[$cat['slug']] = $cid;
	q($m, "INSERT INTO {$p}category_path SET category_id={$cid}, path_id={$cid}, level=0");
	q($m, "INSERT INTO {$p}category_to_store SET category_id={$cid}, store_id=0");
	$names = [$ruId => $cat['ru'], $enId => $cat['en'], $ukId => $cat['uk']];
	foreach ($names as $lid => $name) {
		$meta = esc($m, $name);
		q($m, "INSERT INTO {$p}category_description SET category_id={$cid}, language_id={$lid}, name='{$meta}', description='', meta_title='{$meta}', meta_description='', meta_keyword=''");
	}
	echo "cat {$cid} {$cat['slug']} {$cat['en']}\n";
	$sortCat++;
}

echo "== 6) Products (20 each) ==\n";
$created = 0;
foreach ($categories as $cat) {
	$cid = $catIds[$cat['slug']];
	// skip if already seeded ~20
	$have = (int)$m->query("SELECT COUNT(*) c FROM {$p}product_to_category WHERE category_id={$cid}")->fetch_assoc()['c'];
	if ($have >= 20) {
		echo "  {$cat['slug']}: already {$have}, skip\n";
		continue;
	}
	$pool = ($cat['pool'] === 'foto' && $fotoImgs) ? $fotoImgs : array_values(array_merge($fotoImgs, $demoImgs));
	if (!$pool) {
		$pool = $demoImgs ?: $fotoImgs;
	}
	if (!$pool) {
		throw new RuntimeException('No images available');
	}

	$namesEn = $productNamePools[$cat['slug']]['en'];
	$namesRu = $productNamePools[$cat['slug']]['ru'];
	$namesUk = $productNamePools[$cat['slug']]['uk'];

	for ($i = 0; $i < 20; $i++) {
		$src = $pool[$i % count($pool)];
		$image = copySeedImage($src, $seedDir, $cat['slug'], $i + 1);
		$model = strtoupper($cat['slug']) . '-' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
		$price = round(199 + ($i * 37) + (crc32($cat['slug'] . $i) % 500), 2);
		$qty = 15 + ($i % 40);
		$brand = $brands[$i % count($brands)];
		$colorIdx = $i % count($colors['en']);
		$matIdx = $i % count($materials['en']);

		q($m, "INSERT INTO {$p}product SET
			model='" . esc($m, $model) . "',
			sku='" . esc($m, $model) . "',
			upc='', ean='', jan='', isbn='', mpn='', location='',
			quantity={$qty},
			stock_status_id=7,
			image='" . esc($m, $image) . "',
			manufacturer_id=0,
			shipping=1,
			price='{$price}',
			points=0,
			tax_class_id=0,
			date_available=CURDATE(),
			weight='0.50000000',
			weight_class_id=1,
			length='0.00000000', width='0.00000000', height='0.00000000',
			length_class_id=1,
			subtract=1,
			minimum=1,
			sort_order=" . ($i + 1) . ",
			status=1,
			viewed=0,
			date_added=NOW(),
			date_modified=NOW(),
			oct_stickers='',
			noindex=0
		");
		$pid = (int)$m->insert_id;

		$descTriplet = [
			$ruId => ['name' => $namesRu[$i], 'desc' => '<p>' . $namesRu[$i] . ' — качественный товар для категории «' . $cat['ru'] . '». Подходит для демо витрины Plum Store.</p>'],
			$enId => ['name' => $namesEn[$i], 'desc' => '<p>' . $namesEn[$i] . ' — quality demo product for «' . $cat['en'] . '». Built for Plum Store theme preview.</p>'],
			$ukId => ['name' => $namesUk[$i], 'desc' => '<p>' . $namesUk[$i] . ' — якісний демо-товар для категорії «' . $cat['uk'] . '». Для вітрини теми Plum Store.</p>'],
		];
		foreach ($descTriplet as $lid => $d) {
			$n = esc($m, $d['name']);
			$ds = esc($m, $d['desc']);
			q($m, "INSERT INTO {$p}product_description SET product_id={$pid}, language_id={$lid}, name='{$n}', description='{$ds}', tag='', meta_title='{$n}', meta_description='', meta_keyword=''");
		}

		q($m, "INSERT INTO {$p}product_to_store SET product_id={$pid}, store_id=0");
		q($m, "INSERT INTO {$p}product_to_category SET product_id={$pid}, category_id={$cid}, main_category=1");

		$attrValues = [
			'brand' => [$ruId => $brand, $enId => $brand, $ukId => $brand],
			'color' => [$ruId => $colors['ru'][$colorIdx], $enId => $colors['en'][$colorIdx], $ukId => $colors['uk'][$colorIdx]],
			'warranty' => [$ruId => '12 мес.', $enId => '12 months', $ukId => '12 міс.'],
			'material' => [$ruId => $materials['ru'][$matIdx], $enId => $materials['en'][$matIdx], $ukId => $materials['uk'][$matIdx]],
		];
		foreach ($attrValues as $key => $vals) {
			$aid = $attrIds[$key];
			foreach ($vals as $lid => $val) {
				q($m, "INSERT INTO {$p}product_attribute SET product_id={$pid}, attribute_id={$aid}, language_id={$lid}, text='" . esc($m, $val) . "'");
			}
		}

		$created++;
	}
	echo "  {$cat['slug']}: 20 products\n";
}

echo "\nDONE products={$created} categories=" . count($catIds) . " uk_id={$ukId}\n";

// clear caches
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
