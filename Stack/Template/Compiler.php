<?php

namespace Hodos\Stack\Template;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class Compiler
{
	public static function compileAll(?string $dir = NULL, string $extension = '.xs.php'):void
	{
		$viewsDir = $dir ?? getRootPath() . '/views';
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir));
		
		foreach ($iterator as $file) {
			if ($file->isFile() && str_ends_with($file->getFilename(), $extension)) {
				$viewName = self::getViewNameFromPath($file->getPathname(), $viewsDir, $extension);
				try {
					Engine::renderStatic($viewName);
					echo "Compiled: $viewName\n";
				} catch (Throwable $e) {
					echo "Error compiling $viewName: " . $e->getMessage() . "\n";
				}
			}
		}
	}
	
	public static function clearCache(?string $dir = NULL):void
	{
		$cacheDir = $dir ?? getRootPath() . '/system/framework/cache/views';
		$files = glob(rtrim($cacheDir, '/') . '/*.php');
		
		foreach ($files as $file) {
			unlink($file);
			echo "Deleted cache: $file\n";
		}
	}
	
	private static function getViewNameFromPath(string $path, string $baseDir, string $extension):string
	{
		$relativePath = str_replace([$baseDir, DIRECTORY_SEPARATOR], ['', '.'], $path);
		return ltrim(str_replace($extension, '', $relativePath), '.');
	}
}
