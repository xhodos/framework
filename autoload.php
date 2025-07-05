<?php

/* Require the App Helper file */
require_once ROOT . '/vendor/xhodos/framework/Helpers/app.php';

$LOADED_CLASSES = new stdClass();
$composerFile = getRootPath() . '/composer.json';

if (config('app.composer.autoload'))
	// Autoload classes Using Composer's Auto-Loader.
	require_once ROOT . '/vendor/autoload.php';
else
	// Autoload classes Using Built-in Auto-Loader.
	spl_autoload_register(function ($class) use (&$LOADED_CLASSES, $composerFile) {
		$getFilePath = function () use ($composerFile, $class) {
			if (file_exists($composerFile)) {
				$path_str = '';
				$composerJson = file_get_contents($composerFile);
				$data = json_decode($composerJson);
				
				if (json_last_error() !== JSON_ERROR_NONE)
					throw new RuntimeException("Invalid JSON: " . json_last_error_msg());
				
				$autoload = $data->autoload;
				$psr0 = $autoload->{'psr-0'} ?? NULL;
				$psr4 = $autoload->{'psr-4'} ?? NULL;
				
				if (!empty($psr4))
					foreach ($psr4 as $key => $value)
						if (str_starts_with($class, $key))
							$path_str = useDirectorySeparator(str_replace($key, $value, $class));
				
				if (empty($path_str) && !empty($psr0))
					foreach ($psr0 as $key => $value)
						if (str_starts_with($class, $key))
							$path_str = useDirectorySeparator(str_replace($key, $value, $class));
				if (empty($path_str))
					$path_str = useDirectorySeparator($class);
			} else
				$path_str = useDirectorySeparator($class);
			return $path_str;
		};
		$class_path = loadFile($getFilePath());
		
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
