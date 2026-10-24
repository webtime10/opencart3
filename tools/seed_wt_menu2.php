<?php
/**
 * Seed WT Menu2 (module_id=34) with 6 top categories, 4 levels deep.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../config.php';

$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, (int)(DB_PORT ?: 3306));
$db->set_charset('utf8mb4');
$p = DB_PREFIX;
$moduleId = 34;
$lang = 1;

// 6 top categories for horizontal Menu 2
$topIds = [78, 79, 80, 81, 82, 84]; // notebooks, parts, phones, TV, gaming, network

$byParent = [];
$names = [];
$r = $db->query("SELECT c.category_id, c.parent_id, cd.name
	FROM `{$p}category` c
	INNER JOIN `{$p}category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = {$lang})
	WHERE c.status = 1
	ORDER BY c.parent_id, c.sort_order, LCASE(cd.name)");
while ($row = $r->fetch_assoc()) {
	$cid = (int)$row['category_id'];
	$names[$cid] = $row['name'];
	$byParent[(int)$row['parent_id']][] = $cid;
}

$idCounter = 1;

/**
 * Build up to $maxDepth levels (1=L1). Cap L2 columns at 4.
 */
function buildLimited(array $catIds, array $byParent, array $names, &$idCounter, $depth, $maxDepth, $maxSiblings = 0) {
	$nodes = [];
	$take = $catIds;
	if ($maxSiblings > 0) {
		$take = array_slice($catIds, 0, $maxSiblings);
	}
	foreach ($take as $cid) {
		$children = [];
		if ($depth < $maxDepth && !empty($byParent[$cid])) {
			// L2 (depth+1==2): max 4 columns; deeper: take up to 6
			$cap = ($depth + 1 === 2) ? 4 : 6;
			$children = buildLimited($byParent[$cid], $byParent, $names, $idCounter, $depth + 1, $maxDepth, $cap);
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

$tree = buildLimited($topIds, $byParent, $names, $idCounter, 1, 4, 6);

// Ensure every L1 has at least some structure — report
foreach ($tree as $n) {
	$l2 = count($n['children']);
	$l3 = 0; $l4 = 0;
	foreach ($n['children'] as $c) {
		$l3 += count($c['children']);
		foreach ($c['children'] as $g) {
			$l4 += count($g['children']);
		}
	}
	echo $n['name'] . " L2={$l2} L3={$l3} L4={$l4}\n";
}

$r = $db->query("SELECT setting FROM `{$p}module` WHERE module_id = {$moduleId} AND code = 'wt_menu' LIMIT 1");
if (!$r || !$r->num_rows) {
	fwrite(STDERR, "Module {$moduleId} not found\n");
	exit(1);
}
$old = json_decode($r->fetch_assoc()['setting'], true);
if (!is_array($old)) {
	$old = [];
}

$setting = [
	'name'      => isset($old['name']) ? $old['name'] : 'WT Menu2',
	'status'    => '1',
	'menu_slot' => 2,
	'tree'      => $tree,
];

$db->query("ALTER TABLE `{$p}module` MODIFY `setting` MEDIUMTEXT NOT NULL");
$json = json_encode($setting, JSON_UNESCAPED_UNICODE);
$stmt = $db->prepare("UPDATE `{$p}module` SET `setting` = ? WHERE `module_id` = ? AND `code` = 'wt_menu'");
$stmt->bind_param('si', $json, $moduleId);
$stmt->execute();

echo "Updated module_id={$moduleId}, nodes=" . ($idCounter - 1) . ", L1=" . count($tree) . "\nDONE\n";
