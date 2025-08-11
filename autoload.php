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
		static $autoloadConfig = null;
		
		$getFilePath = function (string $class) use (&$autoloadConfig, $composerFile) {
			// Cache composer.json data
			if ($autoloadConfig === null) {
				$autoloadConfig = (object)[
					'psr4' => [],
					'psr0' => [],
					'classMapLookup' => []
				];
				
				if (is_readable($composerFile)) {
					$data = json_decode(file_get_contents($composerFile));
					
					if (json_last_error() !== JSON_ERROR_NONE)
						throw new RuntimeException("Invalid composer.json: " . json_last_error_msg());
					
					$autoloadConfig->psr4 = (array) ($data->autoload->{'psr-4'} ?? []);
					$autoloadConfig->psr0 = (array) ($data->autoload->{'psr-0'} ?? []);
					
					foreach ((array) ($data->autoload->classmap ?? []) as $dir) {
						$fullDir = ROOT . '/' . trim($dir, '/');
						if (!is_dir($fullDir)) continue;
						
						$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS));
						
						foreach ($iter as $file)
							if (strtolower($file->getExtension()) === 'php')
								$autoloadConfig->classMapLookup[strtolower(pathinfo($file, PATHINFO_FILENAME))] = $file->getPathname();
					}
				}
			}
			$filePath = null;
			$lowerName = strtolower($class);
			
			// 1️⃣ Classmap
			if (isset($autoloadConfig->classMapLookup[$lowerName]))
				$filePath = $autoloadConfig->classMapLookup[$lowerName];
			
			// 2️⃣ PSR-4
			if (!$filePath)
				foreach ($autoloadConfig->psr4 as $prefix => $baseDir)
					if (str_starts_with($class, $prefix)) {
						$relative = substr($class, strlen($prefix));
						$filePath = ROOT . '/' . trim($baseDir, '/') . '/' . str_replace('\\', '/', $relative) . '.php';
						break;
					}
			
			// 3️⃣ PSR-0
			if (!$filePath)
				foreach ($autoloadConfig->psr0 as $prefix => $baseDir)
					if (str_starts_with($class, $prefix)) {
						$relative = str_replace('_', '/', $class);
						$filePath = ROOT . '/' . trim($baseDir, '/') . '/' . str_replace('\\', '/', $relative) . '.php';
						break;
					}
			
			// 4️⃣ Fallback
			if (!$filePath)
				$filePath = ROOT . '/' . str_replace(['\\', '_'], '/', $class) . '.php';
			return $filePath;
		};
		$classFile = $getFilePath($class);
		
		if (is_readable($classFile)) {
			require_once $classFile;
			$LOADED_CLASSES->{pathinfo($classFile, PATHINFO_FILENAME)} = $classFile;
		} else
			dd("Autoloader: Class '$class' not found. Looked in: $classFile");
	});
	
foreach (['cache', 'logs'] as $dir)
	if (!is_dir(ROOT . "/system/framework/$dir"))
		mkdir(ROOT . "/system/framework/$dir", 0777, true);
