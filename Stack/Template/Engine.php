<?php

namespace Hodos\Stack\Template;

use Error;

class Engine
{
	protected string $cachePath;
	protected static ?array $directives = [];
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
	
	public static function directive(string $name, callable $handler):void
	{
		self::$directives[$name] = $handler;
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
			
			// Collect all layouts and sections
			[$finalLayout, $allSections] = $this->resolveExtendsAndSections($templateContent);
			
			// Load and compile the final layout content recursively
			$compiled = $this->compileLayoutChain($finalLayout, $allSections);
			
			file_put_contents($cacheFile, $compiled);
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
			return '<?= ' . $matches[2] . ' ?>';
		}, $templateContent);
		$templateContent = preg_replace_callback('/({{\s?(.*?)\s?}})/', function ($matches) {
			return '<?= htmlspecialchars(' . $matches[2] . '); ?>';
		}, $templateContent);
			
		// Replace foreach
		// Advanced foreach
		$templateContent = preg_replace_callback('/@foreach\s*\((.+?)\s+as\s+(.+?)\)/', function ($matches) {
			$iterable = trim($matches[1]);
			$variables = trim($matches[2]);
			return "<?php foreach ($iterable as $variables): ?>";
		}, $templateContent);
		$templateContent = str_replace('@endforeach', '<?php endforeach; ?>', $templateContent);
		
		// Replace if/else/endif
		$templateContent = preg_replace('/@if\s?\((.*?)\)/', '<?php if ($1): ?>', $templateContent);
		$templateContent = preg_replace('/@elseif\s?\((.*?)\)/', '<?php elseif ($1): ?>', $templateContent);
		$templateContent = str_replace('@else', '<?php else: ?>', $templateContent);
		$templateContent = str_replace('@endif', '<?php endif; ?>', $templateContent);
		
		// Replace @csrf with actual csrf_token
		$templateContent = preg_replace_callback('/@csrf/', function ($matches) {
			return '<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">';
		}, $templateContent);
		
		// Replace include
		$templateContent = preg_replace_callback('/@include\s?\(["\'](.*?)["\'](.*?)\)/', function ($matches) {
			return "<?= (" . __CLASS__ . "::renderStatic('$matches[1]', get_defined_vars())); ?>";
		}, $templateContent);
		
		// Process custom directives
		foreach (self::$directives as $name => $handler) {
			$templateContent = preg_replace_callback("/@$name\\s*(\\((.*?)\\))?", function ($matches) use ($handler) {
				$args = isset($matches[2]) ? $matches[2] : '';
				return $handler($args);
			}, $templateContent);
		}
		return $templateContent;
	}
	
	private function resolveExtendsAndSections(string $templateContent):array
	{
		$layout = NULL;
		$sections = [];
		
		// Check for @extends
		if (preg_match('/@extends\s?\(["\'](.*?)["\']\)/', $templateContent, $extendMatch)) {
			$layout = $extendMatch[1];
			$templateContent = str_replace($extendMatch[0], '', $templateContent);
		}
		// Collect sections
		preg_match_all('/@section\s?\(["\'](.*?)["\']\)(.*?)@endsection/s', $templateContent, $sectionMatches, PREG_SET_ORDER);
		foreach ($sectionMatches as $match) {
			$sections[$layout . $match[1]] = trim($match[2]);
			$templateContent = str_replace($match[0], '', $templateContent);
		}
		
		// Add remaining content as a default section if needed
		if (!empty(trim($templateContent)) && !isset($sections['content'])) {
			$sections['content'] = trim($templateContent);
		}
		return [$layout, $sections];
	}
	
	private function compileLayoutChain(?string $layout, array $sections):string
	{
		if (!$layout)
			// No layout? Just process the current sections
			return $this->processTemplate($sections['content'] ?? '');
		$layoutFile = $this->getViewFile($layout);
		
		if (!is_readable($layoutFile))
			throw new Error("Layout view $layout not found");
		
		$layoutContent = file_get_contents($layoutFile);
		
		// Check if the layout extends another
		[$parentLayout, $parentSections] = $this->resolveExtendsAndSections($layoutContent);
		
		// Merge child sections into parent
		$mergedSections = array_merge($parentSections, $sections);
		
		// Recursively build layout chain
		$finalContent = $this->compileLayoutChain($parentLayout, $mergedSections);
		
		// Replace yields with final section content
		return $this->processTemplate(preg_replace_callback('/@yield\s?\(["\'](.*?)["\']\)/', function ($match) use ($mergedSections, $layout) {
			return $mergedSections[$layout . $match[1]] ?? '';
		}, $finalContent));
	}
	
	
	private function getViewFile(?string $view = NULL):string
	{
		$constructViewFilePath = constructViewFilePath($view ?? $this->view);
		$viewFilePath = env('APP_VIEWS_DIR', 'views') . '/' . $constructViewFilePath;
		return str_replace('.xs.php', '', getRootPath() . DIRECTORY_SEPARATOR . useDirectorySeparator($viewFilePath)) . '.xs.php';
	}
}
