<?php
/**
 * Register WT Menu module + permissions.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('VERSION', '3.0.3.8');

require_once(__DIR__ . '/../admin/config.php');
require_once(DIR_SYSTEM . 'startup.php');

$registry = new Registry();

$config = new Config();
$config->load('default');
$config->load('admin');
$registry->set('config', $config);

$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE store_id = '0'");
foreach ($query->rows as $setting) {
	$config->set($setting['key'], $setting['serialized'] ? json_decode($setting['value'], true) : $setting['value']);
}

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'opencart3.loc';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PORT'] = '80';

$registry->set('request', new Request());

$session = new Session($config->get('session_engine') ?: 'file', $registry);
$session->start();
$session->data['user_id'] = 1;
$session->data['user_token'] = 'cli_wt_menu';
$registry->set('session', $session);

$language = new Language('ru-ru');
$language->load('ru-ru');
$registry->set('language', $language);

$registry->set('load', new Loader($registry));
$registry->set('url', new Url(HTTP_SERVER, HTTPS_SERVER));
$registry->set('response', new Response());
$registry->set('log', new Log('wt_menu_install.log'));
$registry->set('event', new Event($registry));
$registry->set('cache', new Cache($config->get('cache_engine') ?: 'file', $config->get('cache_expire') ?: 3600));
$registry->set('document', new Document());

$user = new Cart\User($registry);
$registry->set('user', $user);

if (!$user->isLogged()) {
	fwrite(STDERR, "login fail\n");
	exit(1);
}

$check = $db->query("SELECT * FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` = 'wt_menu'");
if (!$check->num_rows) {
	$db->query("INSERT INTO `" . DB_PREFIX . "extension` SET `type` = 'module', `code` = 'wt_menu'");
	echo "Extension registered\n";
} else {
	echo "Extension already registered\n";
}

$route = 'extension/module/wt_menu';
$groups = $db->query("SELECT user_group_id, permission FROM `" . DB_PREFIX . "user_group`");
foreach ($groups->rows as $row) {
	$perm = json_decode($row['permission'], true);
	if (!is_array($perm)) {
		$perm = array('access' => array(), 'modify' => array());
	}
	$changed = false;
	foreach (array('access', 'modify') as $type) {
		if (!isset($perm[$type]) || !is_array($perm[$type])) {
			$perm[$type] = array();
		}
		if (!in_array($route, $perm[$type], true)) {
			$perm[$type][] = $route;
			$changed = true;
		}
	}
	if ($changed) {
		$db->query("UPDATE `" . DB_PREFIX . "user_group` SET permission = '" . $db->escape(json_encode($perm)) . "' WHERE user_group_id = '" . (int)$row['user_group_id'] . "'");
		echo "Permissions OK group #{$row['user_group_id']}\n";
	}
}

echo "DONE. Admin → Extensions → Modules → WT Menu → +\n";
