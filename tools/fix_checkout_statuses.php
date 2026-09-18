<?php
/**
 * Fix checkout order statuses: add EN/UK names + ensure config keys.
 * Run: php tools/fix_checkout_statuses.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

$ruId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='ru-ru'")->fetch_assoc()['language_id'];
$enId = (int)$m->query("SELECT language_id FROM {$p}language WHERE code='en-gb'")->fetch_assoc()['language_id'];
$ukRow = $m->query("SELECT language_id FROM {$p}language WHERE code='uk-ua'")->fetch_assoc();
$ukId = $ukRow ? (int)$ukRow['language_id'] : 0;

echo "langs ru={$ruId} en={$enId} uk={$ukId}\n";

// Standard OpenCart order statuses (ids used by default install + this shop)
$statuses = [
	1  => ['ru' => 'Ожидание', 'en' => 'Pending', 'uk' => 'Очікування'],
	2  => ['ru' => 'В обработке', 'en' => 'Processing', 'uk' => 'В обробці'],
	3  => ['ru' => 'Доставлено', 'en' => 'Shipped', 'uk' => 'Доставлено'],
	5  => ['ru' => 'Сделка завершена', 'en' => 'Complete', 'uk' => 'Завершено'],
	7  => ['ru' => 'Отменено', 'en' => 'Canceled', 'uk' => 'Скасовано'],
	8  => ['ru' => 'Возврат', 'en' => 'Denied', 'uk' => 'Відхилено'],
	9  => ['ru' => 'Отмена и аннулирование', 'en' => 'Canceled Reversal', 'uk' => 'Скасування платежу'],
	10 => ['ru' => 'Неудавшийся', 'en' => 'Failed', 'uk' => 'Невдалий'],
	11 => ['ru' => 'Возмещенный', 'en' => 'Refunded', 'uk' => 'Повернено'],
	12 => ['ru' => 'Полностью измененный', 'en' => 'Reversed', 'uk' => 'Сторновано'],
	13 => ['ru' => 'Полный возврат', 'en' => 'Chargeback', 'uk' => 'Повний чарджбек'],
	14 => ['ru' => 'Истекший', 'en' => 'Expired', 'uk' => 'Прострочено'],
	15 => ['ru' => 'Обработан', 'en' => 'Processed', 'uk' => 'Оброблено'],
	16 => ['ru' => 'Аннулирован', 'en' => 'Voided', 'uk' => 'Анульовано'],
];

foreach ($statuses as $sid => $names) {
	$map = [
		$ruId => $names['ru'],
		$enId => $names['en'],
	];
	if ($ukId) {
		$map[$ukId] = $names['uk'];
	}
	foreach ($map as $lid => $name) {
		$ex = $m->query("SELECT 1 FROM {$p}order_status WHERE order_status_id={$sid} AND language_id={$lid}")->num_rows;
		if ($ex) {
			q($m, "UPDATE {$p}order_status SET name='" . esc($m, $name) . "' WHERE order_status_id={$sid} AND language_id={$lid}");
		} else {
			q($m, "INSERT INTO {$p}order_status SET order_status_id={$sid}, language_id={$lid}, name='" . esc($m, $name) . "'");
		}
	}
	echo "status {$sid} ok\n";
}

function upsertSetting(mysqli $m, string $p, string $key, string $value, int $serialized = 0): void {
	$code = 'config';
	$row = $m->query("SELECT setting_id FROM {$p}setting WHERE store_id=0 AND `key`='" . esc($m, $key) . "'")->fetch_assoc();
	if ($row) {
		q($m, "UPDATE {$p}setting SET value='" . esc($m, $value) . "', serialized={$serialized} WHERE setting_id=" . (int)$row['setting_id']);
	} else {
		q($m, "INSERT INTO {$p}setting SET store_id=0, code='{$code}', `key`='" . esc($m, $key) . "', value='" . esc($m, $value) . "', serialized={$serialized}");
	}
}

echo "== checkout config ==\n";
// Default new order = Pending
upsertSetting($m, $p, 'config_order_status_id', '1', 0);
// Processing: Pending, Processing, Shipped, Complete, Processed(15) if exists — classic set
upsertSetting($m, $p, 'config_processing_status', json_encode(['1', '2', '15'], JSON_UNESCAPED_UNICODE), 1);
// Complete: Complete + Shipped (common OC defaults use Complete)
upsertSetting($m, $p, 'config_complete_status', json_encode(['5', '3'], JSON_UNESCAPED_UNICODE), 1);
// Fraud: Denied
upsertSetting($m, $p, 'config_fraud_status_id', '8', 0);
upsertSetting($m, $p, 'config_invoice_prefix', 'INV-2026-00', 0);
upsertSetting($m, $p, 'config_cart_weight', '1', 0);
upsertSetting($m, $p, 'config_checkout_guest', '1', 0);

echo "DONE\n";

$r = $m->query("SELECT order_status_id, language_id, name FROM {$p}order_status WHERE language_id={$enId} ORDER BY order_status_id");
echo "-- EN statuses --\n";
while ($row = $r->fetch_assoc()) {
	echo $row['order_status_id'] . "\t" . $row['name'] . PHP_EOL;
}
