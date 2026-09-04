<?php
/**
 * Fix duplicate addHreflang in modification cache + update wt-lang OCMOD XML in DB.
 * docker exec -u www-data opencart3_web php /var/www/html/tools/fix_wt_lang_modification.php
 */
require_once __DIR__ . '/../config.php';

$dbHost = DB_HOSTNAME;
$dbPort = (int)DB_PORT;
$inDocker = is_file('/var/www/html/index.php') && DB_HOSTNAME === 'db_oc3';
if (!$inDocker && $dbHost === 'db_oc3' && php_sapi_name() === 'cli') {
	$dbHost = '127.0.0.1';
	$dbPort = 3308;
}

$mysqli = new mysqli($dbHost, DB_USERNAME, DB_PASSWORD, DB_DATABASE, $dbPort);
if ($mysqli->connect_error) {
	fwrite(STDERR, 'DB: ' . $mysqli->connect_error . PHP_EOL);
	exit(1);
}
$mysqli->set_charset('utf8mb4');

$modPath = dirname(__DIR__) . '/../wt_lang/install.xml';
if (!is_file($modPath)) {
	$modPath = dirname(__DIR__) . '/../wt_lang/install.xml';
}
if (!is_file($modPath) && is_file('/home/carbon/site/wt_lang/install.xml')) {
	$modPath = '/home/carbon/site/wt_lang/install.xml';
}
if (!is_file($modPath)) {
	$modPath = '/var/www/html/../wt_lang/install.xml';
}
$modXml = file_get_contents($modPath);
if ($modXml === false) {
	fwrite(STDERR, "install.xml not found\n");
	exit(1);
}

$code = 'wt-lang-oc3';
$stmt = $mysqli->prepare('UPDATE `' . DB_PREFIX . 'modification` SET `xml` = ? WHERE `code` = ?');
$stmt->bind_param('ss', $modXml, $code);
$stmt->execute();
echo 'Updated modification XML in DB (' . $stmt->affected_rows . " rows)\n";
$stmt->close();

$docMod = DIR_MODIFICATION . 'system/library/document.php';
if (is_file($docMod)) {
	$s = file_get_contents($docMod);
	$method = "\tpublic function addHreflang(\$href, \$hreflang) {
\t\t\$key = \$href . '|' . \$hreflang;
\t\t\$this->links[\$key] = array(
\t\t\t'href'     => \$href,
\t\t\t'rel'      => 'alternate',
\t\t\t'hreflang' => \$hreflang
\t\t);
\t}

";
	$count = 0;
	$fixed = str_replace($method, $method, $s, $count);
	// str_replace count doesn't work that way in PHP - use substr_count
	$occurrences = substr_count($s, 'public function addHreflang');
	if ($occurrences > 1) {
		$pos = strpos($s, 'public function addHreflang');
		$pos2 = strpos($s, 'public function addHreflang', $pos + 1);
		if ($pos2 !== false) {
			$end = strpos($s, "\n\tpublic function addLink", $pos2);
			if ($end === false) {
				$end = strpos($s, "\n\tpublic function addLink", $pos2);
			}
			if ($end !== false) {
				$s = substr($s, 0, $pos2) . substr($s, $end + 1);
				file_put_contents($docMod, $s);
				echo "Removed duplicate addHreflang from modification cache\n";
			}
		}
	} else {
		echo "Modification document.php OK ($occurrences addHreflang)\n";
	}
} else {
	echo "No modification document.php yet\n";
}

echo "Done. Refresh modifications in admin if needed.\n";
