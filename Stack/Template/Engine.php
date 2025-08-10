<?php
namespace Hodos\Stack\Template;

use Closure;
use Error;
use Hodos\Stack\Support\TemplateProcessor;
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
	
	protected array $sections = [];
	protected array $stacks = [];
	
	public function __construct(public string $view, public ?array $data = [], public ?string $templateType = 'view', ?string $componentPath = NULL, ?string $cachePath = NULL)
	{
		if (!self::$instance)
			self::$instance = $this;
		
		$this->cachePath = correctDirPath($cachePath ?: getRootPath() . '/system/framework/cache/views');
		$this->componentPath = correctDirPath($componentPath ?: getRootPath() . '/app/Components/Views');
		$this->discoverComponents();
	}
	
	public static function renderStatic(string $template, ?array $data = [], ?string $templateType = 'view'):View
	{
		if (!self::$instance)
			self::$instance = new static($template, $data, $templateType);
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
	
	private function buildCacheFile($filename, $templateFile):string
	{
		$cacheFile = "$this->cachePath/$filename.php";
		
		if (!is_readable($templateFile))
			dd(new Error(mb_convert_case($this->templateType, MB_CASE_TITLE) . " $filename not found"));
		
		if (!file_exists($cacheFile) || filemtime($cacheFile) < filemtime($templateFile)) {
			if (!is_dir(dirname($cacheFile))) {
				mkdir(dirname($cacheFile), 0777, true);
			}
			$compiled = $this->compileLayoutChain($templateFile);
			file_put_contents($cacheFile, $compiled);
		}
		return $cacheFile;
	}
	
	private function compileLayoutChain(string $viewFile):string
	{
		$chain = [];
		$content = file_get_contents($viewFile);
		$chain[] = $content;
		
		while (preg_match('/@extends\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', $content, $matches)) {
			$parent = $matches[1];
			$content = preg_replace('/@extends\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/', '', $content);
			$this->extractSectionsAndStacks($content);
			$viewFile = $this->getViewFile($parent);
			$content = file_get_contents($viewFile);
			$chain[] = $content;
		}
		
		$this->extractSectionsAndStacks($content);
		
		// Inject from base layout upward
		$base = array_pop($chain);
		while ($part = array_pop($chain)) {
			$base = $this->injectSectionsAndStacks($base);
		}
		return $this->processTemplate($this->injectSectionsAndStacks($base));
	}
	
	protected function extractFloatingContent(string $content):string
	{
		// First, strip known structured blocks before searching for floating content
		$cleaned = $content;
		
		// Remove all @section blocks
		$cleaned = preg_replace('/@section\s*\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endsection/s', '', $cleaned);
		
		// Remove all @push blocks
		$cleaned = preg_replace('/@push\s*\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endpush/s', '', $cleaned);
		
		// Remove all @prepend blocks
		$cleaned = preg_replace('/@prepend\s*\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endprepend/s', '', $cleaned);
		
		// Remove any @extends and @yield lines (they're not floating content)
		$cleaned = preg_replace('/@extends\s*\(\s*[\'"].+?[\'"]\s*\)/', '', $cleaned);
		$cleaned = preg_replace('/@yield\s*\(\s*[\'"].+?[\'"]\s*\)/', '', $cleaned);
		
		// The remaining content is what floats outside any known blocks
		return trim($cleaned);
	}
	
	private function extractSectionsAndStacks(string $content):void
	{
		preg_match_all('/@section\s*\(\s*[\'"](.*?)[\'"]\s*\)(.*?)@endsection/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
		
		$lastOffset = 0;
		foreach ($matches as $key => $match) {
			$name = $match[1][0];
			$body = $match[2][0];
			$cleaned = str_replace($match[0][0], '', $content);
			if (str_contains($body, '@parent') && isset($this->sections[$name])) {
				$body = str_replace('@parent', $this->sections[$name], $body);
			}
			
			$start = (int) $match[0][1];
			if ($start > $lastOffset) {
				$intermediate = $this->extractFloatingContent(substr($content, $lastOffset, $start - $lastOffset));
				$lastOffset = $start + strlen($match[0][0]);
			}
			$this->sections[$name] = !empty($intermediate) ? "$intermediate\n" . $body : $body;
		}
		
		preg_match_all('/@(push|prepend)\s*\(\s*[\'"](.*?)[\'"]\s*\)(.*?)@end(push|prepend)/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
		foreach ($matches as $match) {
			$type = $match[1][0];
			$name = $match[2][0];
			$body = $match[3][0];
			if (!isset($this->stacks[$name])) $this->stacks[$name] = [];
			if ($type === 'prepend') {
				array_unshift($this->stacks[$name], $body);
			} else {
				$this->stacks[$name][] = $body;
			}
		}
	}
	
	private function injectSectionsAndStacks(string $content):string
	{
		$content = preg_replace_callback('/@yield\s*\(\s*[\'"](.*?)[\'"]\s*\)/', function ($match) {
			$name = $match[1];
			return $this->sections[$name] ?? '';
		}, $content);
		
		$content = preg_replace_callback('/@stack\s*\(\s*[\'"](.*?)[\'"]\s*\)/', function ($match) {
			$name = $match[1];
			return isset($this->stacks[$name]) ? implode(PHP_EOL, $this->stacks[$name]) : '';
		}, $content);
		return $content;
	}
	
	// Component discovery
	protected function discoverComponents():void
	{
		$baseLen = strlen($this->componentPath) + 1;
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->componentPath));
		
		foreach ($iterator as $file) {
			if ($file->getExtension() !== 'php') continue;
			
			$relPath = substr($file->getPathname(), $baseLen, -4);
			$tag = strtolower(str_replace(DIRECTORY_SEPARATOR, '-', $relPath));
			
			$realPath = correctDirPath($file->getPathname());
			$code = file_get_contents($realPath);
			
			$namespace = $this->extractNamespace($code);
			$className = basename($realPath, '.php');
			self::$componentMap[$tag] = "$namespace\\$className";
		}
	}
	
	protected function renderComponent(string $tag, array $props, ?string $slot = NULL):string
	{
		if (self::$renderDepth++ > self::$maxDepth)
			dd(new RuntimeException("Component recursion too deep: <$tag>"));
		
		$componentName = strtolower(str_replace('-', '', $tag));
		
		if (!isset(self::$componentMap[$componentName]))
			return "<!-- Unknown component <$tag> -->";
		
		$componentClass = self::$componentMap[$componentName];
		$props = var_export(array_merge($props, ['slot' => '__SLOT__']), true);
		
		self::$renderDepth--;
		return "<?php ob_start() ?>$slot<?php \$__slot = ob_get_clean() ?><?= $componentClass::make($props)->withSlot(\$__slot)->output(); ?>";
	}
	
	protected function extractNamespace(string $code):string
	{
		if (preg_match('/namespace\s+(.+);/', $code, $matches))
			return trim($matches[1]);
		return '';
	}
	
	protected function parseAttributes(string $raw): array
	{
		$attrs = [];
		preg_match_all('/(\w+)(=(["\'])(.*?)\3)?/', $raw, $matches, PREG_SET_ORDER);
		foreach ($matches as $match)
			$attrs[$match[1]] = $match[4] ?? true;
		return $attrs;
	}
	
	private function processTemplate(string $content):string
	{
		$processor = new TemplateProcessor($content);
		
		$content = $processor
			->processComments() // <- Replace Template Comment
			->processVariables() // <- Replace Variables [{{...}}, {!...!}]
			->processFormUtilities() // <- Form Helpers [@csrf, @method()]
			->processGeneralUtilities() // <- General Utilities [@dd]
			->processLayoutUtilities() // <- Layout Utilities [@include]
			->processConditionals() // <- Conditional Statements [@for()...@elseif()...@else...@endif, @switch()...@case()...@break...@default...@endswitch]
			->processLoops() // <- Loop Statements [@foreach]
			->processCustomDirectives(self::$directives) // <- Process custom (User-defined) directives
			->getProcessed(); // <- Final processed content
		
		// Handle self-closing components like <hodos:component ... />
		$content = preg_replace_callback('/<hodos:([\w\-:]+)([^>]*?)\s*\/>/', function ($matches) {
			$tag = $matches[1];
			$attrs = $this->parseAttributes($matches[2]);
			return $this->renderComponent($tag, $attrs, '');
		}, $content);
		
		// Handle components with content <hodos:component>...</hodos:component>
		return preg_replace_callback('/<hodos:([\w\-:]+)([^>]*)>(.*?)<\/hodos:\1>/s', function ($matches) {
			$tag = $matches[1];
			$attrs = $this->parseAttributes($matches[2]);
			$slot = $this->processTemplate($matches[3]); // 💡 now supports nested components
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
}
