<?php
/**
 * Clean WT Menu item names: keep only last segment after ">".
 */
require __DIR__ . '/../config.php';
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)(DB_PORT ?: 3306));
$db->set_charset('utf8mb4');
$p = DB_PREFIX;

$r = $db->query("SELECT module_id, setting FROM `{$p}module` WHERE code = 'wt_menu'");
if (!$r || !$r->num_rows) {
	echo "no module\n";
	exit;
}

function cleanNames(array $nodes) {
	foreach ($nodes as &$node) {
		if (!empty($node['name'])) {
			$name = html_entity_decode($node['name'], ENT_QUOTES, 'UTF-8');
			$name = preg_replace('/\s+/u', ' ', str_replace("\xc2\xa0", ' ', $name));
			if (strpos($name, '>') !== false) {
				$parts = preg_split('/\s*>\s*/u', $name);
				$node['name'] = trim((string)end($parts));
			} else {
				$node['name'] = trim($name);
			}
		}
		if (!empty($node['children']) && is_array($node['children'])) {
			$node['children'] = cleanNames($node['children']);
		}
	}
	unset($node);
	return $nodes;
}

while ($row = $r->fetch_assoc()) {
	$setting = json_decode($row['setting'], true);
	if (!is_array($setting) || empty($setting['tree'])) {
		continue;
	}
	$setting['tree'] = cleanNames($setting['tree']);
	$json = json_encode($setting, JSON_UNESCAPED_UNICODE);
	$stmt = $db->prepare("UPDATE `{$p}module` SET setting = ? WHERE module_id = ?");
	$mid = (int)$row['module_id'];
	$stmt->bind_param('si', $json, $mid);
	$stmt->execute();
	echo "cleaned module_id={$mid}\n";
}
echo "DONE\n";
