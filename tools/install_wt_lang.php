<?php
/**
 * Register WT Lang in DB (extension + settings + OCMOD record).
 * Run: docker exec opencart3_web php /var/www/html/tools/install_wt_lang.php
 */
require_once __DIR__ . '/../config.php';

$dbHost = DB_HOSTNAME;
$dbPort = (int)DB_PORT;

// Host CLI: Docker service name db_oc3 is unavailable outside the container.
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

$modPath = dirname(__DIR__) . '/../wt_lang/install.xml';
if (!is_file($modPath)) {
	$modPath = '/var/www/html/../wt_lang/install.xml';
}
$modXml = file_get_contents($modPath);
if ($modXml === false) {
	fwrite(STDERR, "install.xml not found at {$modPath}\n");
	exit(1);
}

$code = 'wt-lang-oc3';
$stmt = $mysqli->prepare('DELETE FROM `' . DB_PREFIX . 'modification` WHERE `code` = ?');
$stmt->bind_param('s', $code);
$stmt->execute();
$stmt->close();

$name = 'WT Lang Modification (OpenCart 3)';
$author = 'WT';
$version = '1.0.0';
$link = '';
$status = 1;
$stmt = $mysqli->prepare('INSERT INTO `' . DB_PREFIX . 'modification` (`extension_install_id`, `name`, `code`, `author`, `version`, `link`, `xml`, `status`, `date_added`) VALUES (0, ?, ?, ?, ?, ?, ?, ?, NOW())');
$stmt->bind_param('ssssssi', $name, $code, $author, $version, $link, $modXml, $status);
$stmt->execute();
$stmt->close();

$res = $mysqli->query("SELECT extension_id FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` = 'wt_lang' LIMIT 1");
if (!$res || !$res->num_rows) {
	$mysqli->query("INSERT INTO `" . DB_PREFIX . "extension` SET `type` = 'module', `code` = 'wt_lang'");
}

$res = $mysqli->query("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_wt_lang_status' AND store_id = 0 LIMIT 1");
if (!$res || !$res->num_rows) {
	$langRes = $mysqli->query("SELECT * FROM `" . DB_PREFIX . "language` WHERE status = 1 ORDER BY sort_order, name");
	$configRes = $mysqli->query("SELECT value FROM `" . DB_PREFIX . "setting` WHERE `key` = 'config_language' AND store_id = 0 LIMIT 1");
	$default = 'uk-ua';
	if ($configRes && $configRes->num_rows) {
		$configRow = $configRes->fetch_assoc();
		$default = $configRow['value'];
	}

	$language_map = array();
	while ($row = $langRes->fetch_assoc()) {
		$lc = $row['code'];
		$is_default = ($lc === $default);
		$prefix = '';
		if (!$is_default) {
			if ($lc === 'en-gb') {
				$prefix = 'en';
			} elseif ($lc === 'uk-ua') {
				$prefix = 'uk';
			} elseif ($lc === 'ru-ru') {
				$prefix = 'ru';
			} else {
				$prefix = substr($lc, 0, 2);
			}
		}
		$hreflang = ($lc === 'uk-ua') ? 'uk' : (($lc === 'en-gb') ? 'en' : ($prefix ?: substr($lc, 0, 2)));
		$language_map[$lc] = array(
			'status'   => 1,
			'prefix'   => $prefix,
			'hreflang' => $hreflang,
			'default'  => $is_default ? 1 : 0,
		);
	}

	$settings = array(
		'module_wt_lang_status'    => '1',
		'module_wt_lang_hreflang'  => '1',
		'module_wt_lang_xdefault'  => '1',
	);

	foreach ($settings as $key => $value) {
		$stmt = $mysqli->prepare('INSERT INTO `' . DB_PREFIX . 'setting` SET store_id = 0, `code` = \'module_wt_lang\', `key` = ?, `value` = ?, serialized = 0');
		$stmt->bind_param('ss', $key, $value);
		$stmt->execute();
		$stmt->close();
	}

	$json = json_encode($language_map);
	$key = 'module_wt_lang_language';
	$stmt = $mysqli->prepare('INSERT INTO `' . DB_PREFIX . 'setting` SET store_id = 0, `code` = \'module_wt_lang\', `key` = ?, `value` = ?, serialized = 1');
	$stmt->bind_param('ss', $key, $json);
	$stmt->execute();
	$stmt->close();
}

echo "WT Lang DB install OK.\n";
echo "Refresh modifications in admin if needed (Extensions → Modifications → Refresh).\n";
echo "Enable: Extensions → Modules → WT Lang.\n";
