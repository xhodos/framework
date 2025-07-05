<?php

/* Require the App Helper file */
require_once ROOT . '/vendor/xhodos/framework/Helpers/app.php';

$LOADED_CLASSES = new stdClass();

if (config('app.composer.autoload'))
	// Autoload classes Using Composer's Auto-Loader.
	require_once ROOT . '/vendor/autoload.php';
else
	// Autoload classes Using Built-in Auto-Loader.
	spl_autoload_register(function ($class) use (&$LOADED_CLASSES) {
		$class_path = loadFile($class) ? loadFile($class) : loadFile('vendor\\' . $class);
		
		if ($class_path) {
			$class_file = array_reverse(explode('\\', $class_path))[0];
			$class_file_exploded = explode('.', $class_file);
			
			if (strtolower(array_reverse($class_file_exploded)[0]) === 'php')
				$LOADED_CLASSES->{$class_file_exploded[0]} = $class_path;
		} else
			die("Class $class not found");
	});

foreach (['cache', 'logs'] as $dir)
	if (!is_dir(ROOT . "/system/framework/$dir"))
		mkdir(ROOT . "/system/framework/$dir", 0777, true);
