<?php
$paths = array(
	'/var/www/html/system/library/seopro.php',
	'/var/www/html/system/storage/modification/system/library/seopro.php',
);

$needle = "        switch (\$data['route']) {";
$insert = "        if (!isset(\$data['route'])) {\n            return [\$url, \$data, \$postfix];\n        }\n\n        switch (\$data['route']) {";

foreach ($paths as $path) {
	if (!is_file($path)) {
		echo "skip: $path\n";
		continue;
	}

	$s = file_get_contents($path);

	if (strpos($s, "if (!isset(\$data['route']))") !== false) {
		echo "already: $path\n";
		continue;
	}

	if (strpos($s, $needle) === false) {
		echo "no match: $path\n";
		continue;
	}

	file_put_contents($path, str_replace($needle, $insert, $s));
	echo "patched: $path\n";
}
