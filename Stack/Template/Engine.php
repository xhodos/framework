<?php

namespace Hodos\Stack\Template;

use Error;

class Engine
{
	protected string $cachePath;
	
	protected static ?Engine $instance = NULL;
	
	public function __construct(public string $view, public ?array $data = [], ?string $cachePath = NULL)
	{
		if (!self::$instance)
			self::$instance = $this;
		$this->cachePath = $cachePath ? trim($cachePath) : getRootPath() . '/system/framework/cache/views';
	}
	
	public static function renderStatic(string $template, array $data = []):string
	{
		return new static($template, $data)->render();
	}
	
	public function render():string
	{
		ob_start();
		$this->make();
		return ob_get_clean();
	}
	
	protected function make():void
	{
		$templateFile = $this->getViewFile();
		$filename = str_replace(['/', '\\', '.'], '.', $this->view);
		$cacheFile = "$this->cachePath/$filename.php";
		
		if (!is_readable($templateFile))
			throw new Error("View $this->view not found");
		
		if (!file_exists($cacheFile) || filemtime($cacheFile) < filemtime($templateFile)) {
			if (!is_dir(dirname($cacheFile)))
				mkdir(dirname($cacheFile), 0777, true);
			
			// Get file contents
			$templateContent = file_get_contents($templateFile);
			$layout = NULL;
			$sections = [];
			
			// Handle @extends
			if (preg_match('/@extends\s?\(["\'](.*?)["\']\)/', $templateContent, $extendMatch)) {
				$layout = $extendMatch[1];
				$templateContent = str_replace($extendMatch[0], '', $templateContent);
			}
			
			// Capture @section blocks
			preg_match_all('/@section\s?\(["\'](.*?)["\']\)(.*?)@endsection/s', $templateContent, $sectionMatches, PREG_SET_ORDER);
			foreach ($sectionMatches as $match) {
				$sections[$match[1]] = trim($match[2]);
				$templateContent = str_replace($match[0], '', $templateContent);
			}
			$templateContent = $this->processTemplate($templateContent);
			
			// If layout is used, process it
			if ($layout) {
				$layoutFile = $this->getViewFile($layout);
				
				if (!is_readable($layoutFile))
					throw new Error("Layout view $layout not found");
				
				// Continue parsing the layout like a regular template
				$templateContent = $this->processTemplate($this->yieldContent($layoutFile, $sections));
			}
			file_put_contents($cacheFile, $templateContent);
		}
		if (!empty($this->data))
			extract($this->data, EXTR_SKIP);
		include $cacheFile;
	}
	
	private function yieldContent($layoutFile, $sections):string
	{
		// Replace @yield with section content
		return preg_replace_callback('/@yield\s?\(["\'](.*?)["\']\)/', function ($match) use ($sections) {
			return $sections[$match[1]] ?? '';
		}, file_get_contents($layoutFile));
	}
	
	private function processTemplate(string $templateContent):string
	{
		// Replace variables
		$templateContent = preg_replace_callback('/({!!\s?(.*?)\s?!!})/', function ($matches) {
			return '<?= '. $matches[2] . ' ?>';
		}, $templateContent);
		$templateContent = preg_replace_callback('/({{\s?(.*?)\s?}})/', function ($matches) {
			return '<?= htmlspecialchars('. $matches[2] . '); ?>';
		}, $templateContent);
		
		// Replace foreach
		$templateContent = preg_replace('/@foreach\s?\((.*?)\)/', '<?php foreach ($1): ?>', $templateContent);
		$templateContent = str_replace('@endforeach', '<?php endforeach; ?>', $templateContent);
		
		// Replace if/else/endif
		$templateContent = preg_replace('/@if\s?\((.*?)\)/', '<?php if ($1): ?>', $templateContent);
		$templateContent = preg_replace('/@elseif\s?\((.*?)\)/', '<?php elseif ($1): ?>', $templateContent);
		$templateContent = str_replace('@else', '<?php else: ?>', $templateContent);
		$templateContent = str_replace('@endif', '<?php endif; ?>', $templateContent);
		
		// Replace include
		return preg_replace_callback('/@include\s?\(([^,]+)((,\s?)?(.*))?\)/', function ($matches) {
			return '<?= (' . __CLASS__ . '::renderStatic(' . $matches[1] . ', get_defined_vars())); ?>';
		}, $templateContent);
	}
	
	private function getViewFile(?string $view = NULL):string
	{
		$constructViewFilePath = constructViewFilePath($view ?? $this->view);
		$viewFilePath = (env('APP_VIEWS_DIR') ?? 'views') . '/' . $constructViewFilePath;
		return str_replace('.xs.php', '', getRootPath() . DIRECTORY_SEPARATOR . useDirectorySeparator($viewFilePath)) . '.xs.php';
	}
}
