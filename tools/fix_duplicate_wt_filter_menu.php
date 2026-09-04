<?php
$file = dirname(__DIR__) . '/system/storage/modification/admin/controller/common/column_left.php';

if (!is_file($file)) {
	fwrite(STDERR, "File not found: $file\n");
	exit(1);
}

$text = file_get_contents($file);

$old = <<<'PHP'
			if ($this->user->hasPermission('access', 'catalog/wt_filter')) {
				$catalog[] = array(
					'name'	   => $this->language->get('text_wt_filter'),
					'href'     => $this->url->link('catalog/wt_filter', 'user_token=' . $this->session->data['user_token'], true),
					'children' => array()
				);
			}


			// WT Filter start
PHP;

$new = <<<'PHP'
			// WT Filter start
PHP;

if (strpos($text, $old) === false) {
	fwrite(STDERR, "Duplicate block not found (maybe already fixed).\n");
	exit(0);
}

file_put_contents($file, str_replace($old, $new, $text));
echo "Removed duplicate WT Filter menu entry.\n";
