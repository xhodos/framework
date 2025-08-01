<?php
namespace Hodos\Stack\Template;

use Closure;
use Error;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

class Engine
{
	protected string $cachePath;
	protected string $componentPath;
	
	protected static int $maxDepth = 30;
	protected static int $renderDepth = 0;
	
	protected static ?array $directives = [];
	protected static ?array $componentMap = [];
	protected static ?Engine $instance = NULL;
	
	public function __construct(public string $view, public ?array $data = [], ?string $componentPath = NULL, ?string $cachePath = NULL)
	{
		if (!self::$instance)
			self::$instance = $this;
		
		$this->cachePath = correctDirPath($cachePath ?: getRootPath() . '/system/framework/cache/views');
		$this->componentPath = correctDirPath($componentPath ?: getRootPath() . '/app/Components/Views');
		$this->discoverComponents();
	}
	
	public static function renderStatic(string $template, ?array $data = []):View
	{
		if (!self::$instance)
			self::$instance = new static($template, $data);
		// return new View(self::$instance->make(), $data)->render();
		$view = new View(self::$instance->make(), $data);
		self::$instance = NULL; // Free memory
		return $view;
	}
	
	public static function directive(string $name, callable $handler):void
	{
		self::$directives[$name] = $handler;
	}
	
	protected function make():string
	{
		$templateFile = $this->getViewFile();
		$filename = str_replace(['/', '\\', '.'], '.', $this->view);
		return $this->buildCacheFile($filename, $templateFile);
	}
	
	private function buildCacheFile($filename, $templateFile, string $templateType = 'view'):string
	{
		$cacheFile = "$this->cachePath/$filename.php";
		
		if (!is_readable($templateFile))
			throw new Error(mb_convert_case($templateType, MB_CASE_TITLE) . " $filename not found");
		
		if (!file_exists($cacheFile) || filemtime($cacheFile) < filemtime($templateFile)) {
			if (!is_dir(dirname($cacheFile)))
				mkdir(dirname($cacheFile), 0777, true);
			
			$templateContent = file_get_contents($templateFile);
			[$layout, $sections] = $this->resolveExtendsAndSections($templateContent);
			$compiled = $this->compileLayoutChain($layout, $sections);
			file_put_contents($cacheFile, $compiled);
		}
		return $cacheFile;
	}
	
	private function resolveExtendsAndSections(string $templateContent):array
	{
		$layout = NULL;
		$sections = [];
		$floatingContent = '';
		
		if (preg_match('/@extends\s?\(["\'](.*?)["\']\)/', $templateContent, $extendMatch)) {
			$layout = $extendMatch[1];
			$templateContent = str_replace($extendMatch[0], '', $templateContent);
		}
		preg_match_all('/@section\s?\(["\'](.*?)["\']\)(.*?)@endsection/s', $templateContent, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
		
		$lastOffset = 0;
		foreach ($matches as $match) {
			$name = $match[1][0];
			$content = trim($match[2][0]);
			
			$start = (int) $match[0][1];
			if ($start > $lastOffset) {
				$intermediate = substr($templateContent, $lastOffset, $start - $lastOffset);
				$floatingContent .= trim($intermediate);
			}
			
			$templateContent = '';
			$floatingContent = trim($floatingContent . $content);
			
			$sections[$layout . $name] = $floatingContent;
			$lastOffset = $start + strlen($match[0][0]);
		}
		
		if ($lastOffset < strlen($templateContent)) {
			$floatingContent .= trim(substr($templateContent, $lastOffset));
		}
		
		if (!empty(trim($templateContent)) && !isset($sections['content']))
			$sections['content'] = trim($templateContent);
		
		return [$layout, $sections];
	}
	
	
	private function compileLayoutChain(?string $layout, array $sections):string
	{
		// No layout? Just process the current sections
		if (!$layout)
			return $this->processTemplate($sections['content'] ?? '');
		$layoutFile = $this->getViewFile($layout);
		
		if (!is_readable($layoutFile))
			throw new Error("Layout view $layout not found");
		$layoutContent = file_get_contents($layoutFile);
		
		// Check if the layout extends another
		[$parentLayout, $parentSections] = $this->resolveExtendsAndSections($layoutContent);
		
		// Merge child into parent (child takes priority)
		$mergedSections = array_merge($parentSections, $sections);
		
		// Recursively go all the way up the chain (Check if the layout extends another)
		$compiledParent = $this->compileLayoutChain($parentLayout, $mergedSections);
		
		// Now process @yield for this layout level
		return $this->processTemplate($this->yieldContent($compiledParent, $mergedSections, $layout));
	}
	
	protected function discoverComponents():void
	{
		$baseLen = strlen($this->componentPath) + 1;
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->componentPath)
		);
		
		foreach ($iterator as $file) {
			if ($file->getExtension() !== 'php') continue;
			
			$relPath = substr($file->getPathname(), $baseLen, -4); // Remove base and .php
			$tag = strtolower(str_replace(DIRECTORY_SEPARATOR, '-', $relPath));
			
			$realPath = correctDirPath($file->getPathname());
			$code = file_get_contents($realPath);
			
			$namespace = $this->extractNamespace($code);
			$className = basename($realPath, '.php');
			$fqn = "$namespace\\$className";
			self::$componentMap[$tag] = $fqn;
		}
	}
	
	protected function renderComponent(string $tag, array $props, ?string $slot = NULL):string
	{
		if (self::$renderDepth++ > self::$maxDepth)
			throw new RuntimeException("Component recursion too deep: <$tag>");
		
		$componentName = strtolower(str_replace('-', '', $tag));
		
		if (!isset(self::$componentMap[$componentName]))
			return "<!-- Unknown component <$tag> -->";
		
		if (!empty($slot))
			$props['slot'] = $slot;
		
		$componentClass = self::$componentMap[$componentName];
		self::$renderDepth--; // Decrement after return
		return "<?php $componentClass::make(" . var_export($props, true) . ")->output(); ?>";
	}
	
	protected function extractNamespace(string $code):string
	{
		if (preg_match('/namespace\s+(.+);/', $code, $matches))
			return trim($matches[1]);
		return '';
	}
	
	protected function parseAttributes(string $raw):array
	{
		$attrs = [];
		preg_match_all('/(\w+)=(["\'])(.*?)\2/', $raw, $matches, PREG_SET_ORDER);
		foreach ($matches as $match)
			$attrs[$match[1]] = $match[3];
		return $attrs;
	}
	
	private function processTemplate(string $content):string
	{
		// Replace Template Comment
		$content = preg_replace_callback('/({{--(\s?(.*?)\s?)--}})/s', fn ($match) => "<!-- " . trim($match[2]) . " -->", $content);
		
		// FORM Helpers
		// Replace @csrf with actual csrf_token
		$content = preg_replace_callback('/@csrf/', fn () => '<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">', $content);
		// Replace @method with actual http method
		$content = preg_replace_callback('/@method\s?\((.*?)\)/', fn ($match) => '<input type="hidden" name="_method" value="<?= ' . $match[1] . ' ?>">', $content);
		
		// Replace include
		// Match: @include('view', ['key' => 'value'])
		/*$content = preg_replace_callback('/@include\s?\(["\'](.*?)["\'](.*?)\)/', fn ($matches) => "<?= (" . __CLASS__ . "::renderStatic('$matches[1]', get_defined_vars())); ?>", $content);*/
		$content = preg_replace_callback('/@include\s?\(\s?[\'"](.*?)[\'"]\s?(?:,\s?(.*?))?\s?\)/', fn ($matches) => "<?= (" . __CLASS__ . "::renderStatic('$matches[1]', $matches[2] ?? get_defined_vars())); ?>", $content);
		
		// Replace Variables
		$content = preg_replace_callback('/{!!\s?(.*?)\s?!!}/', fn ($match) => "<?= {$match[1]} ?>", $content);
		$content = preg_replace_callback('/{{\s?(.*?)\s?}}/', fn ($match) => $match[1] === '$slot' ? "<?= $match[1] ?>" : "<?= htmlspecialchars({$match[1]}) ?>", $content);
		
		// Replace Switch-Case
		$content = preg_replace('/@switch\s*\((.*?)\)/', '<?php switch($1): ?>', $content);
		$content = preg_replace('/@case\s*\((.*?)\)/', '<?php case $1: ?>', $content);
		$content = str_replace('@default', '<?php default: ?>', $content);
		$content = str_replace('@endswitch', '<?php endswitch; ?>', $content);
		
		// Replace break
		$content = preg_replace_callback('/(@break(\s?\((.*?)\))?)/', fn ($matches) => (array_key_exists(3, $matches)) ? '<?php if(' . $matches[3] . '): ?>break;<?php endif; ?>' : '<?php break; ?>', $content);
		
		// Replace dd
		$content = preg_replace_callback('/@dd\((.*)\)/', fn ($matches) => "<?php dd($matches[1]) ?>", $content);
		
		// Replace foreach
		$content = preg_replace_callback('/@foreach\s*\((.+?)\s+as\s+(.+?)\)/', function ($matches) {
			$iterable = trim($matches[1]);
			$variables = trim($matches[2]);
			return "<?php foreach ($iterable as $variables): ?>";
		}, $content);
		$content = str_replace('@endforeach', '<?php endforeach; ?>', $content);
		
		// Replace if/else/endif
		$content = preg_replace_callback('/@if\s?\((.*)\)/', fn ($matches) => "<?php if ($matches[1]): ?>", $content);
		$content = preg_replace_callback('/@elseif\s?\((.*)\)/', fn ($matches) => "<?php elseif ($matches[1]): ?>", $content);
		$content = str_replace('@else', '<?php else: ?>', $content);
		$content = str_replace('@endif', '<?php endif; ?>', $content);
		
		// Process custom directives
		foreach (self::$directives as $name => $handler)
			$content = preg_replace_callback("/@$name\\s*(\\((.*?)\\))?", fn ($matches) => $handler($matches[2] ?? ''), $content);
		
		// Handle self-closing components like <x-alert ... />
		$content = preg_replace_callback('/<hodos:([\w\-:]+)([^>]*?)\s*\/>/', function ($matches) {
			$tag = $matches[1];
			$attrs = $this->parseAttributes($matches[2]);
			return $this->renderComponent($tag, $attrs, '');
		}, $content);
		
		// Handle components with content <x-alert>...</x-alert>
		return preg_replace_callback('/<hodos:([\w\-:]+)([^>]*)>(.*?)<\/hodos:\1>/s', function ($matches) {
			$tag = $matches[1];
			$attrs = $this->parseAttributes($matches[2]);
			$slot = $matches[3];
			return $this->renderComponent($tag, $attrs, $slot);
		}, $content);
	}
	
	private function getViewFile(?string $view = NULL):string
	{
		$path = constructViewFilePath($view ?? $this->view);
		$viewDir = env('APP_VIEWS_DIR', 'views');
		$xsPath = correctDirPath(getRootPath() . "/$viewDir/$path.xs.php");
		$phpPath = correctDirPath(getRootPath() . "/$viewDir/$path.php");
		return file_exists($xsPath) ? $xsPath : (file_exists($phpPath) ? $phpPath : $xsPath);
	}
	
	private function yieldContent($templateContent, $sections, $layout):string
	{
		// Replace @yield with section content
		return preg_replace_callback('/@yield\s?\(["\'](.*?)["\']\)/', function ($match) use ($sections, $layout) {
			return $sections[$layout . $match[1]] ?? '';
		}, $templateContent);
	}
}
