<?php

namespace Hodos\Stack\Support;

use Hodos\Stack\Template\Engine;

class TemplateProcessor
{
	private ?string $content;
	
	public function __construct(public string $templateContent)
	{
		$this->content = $templateContent;
	}
	
	public function processComments():static
	{
		$this->content = preg_replace_callback('/({{--(\s?(.*?)\s?)--}})/s', fn ($match) => "<!-- " . trim($match[2]) . " -->", $this->content);
		return $this;
	}
	
	public function processCustomDirectives(array $directives):static
	{
		// Process custom directives
		foreach ($directives as $name => $handler)
			$this->content = preg_replace_callback("/@$name\\s*(\\((.*?)\\))?", fn ($matches) => $handler($matches[2] ?? ''), $this->content);
		return $this;
	}
	
	public function processConditionals():static
	{
		// Replace Switch-Case
		$this->content = preg_replace('/@switch\s*\((.*?)\)/', '<?php switch($1): ?>', $this->content);
		$this->content = preg_replace('/@case\s*\((.*?)\)/', '<?php case $1: ?>', $this->content);
		$this->content = str_replace('@default', '<?php default: ?>', $this->content);
		$this->content = str_replace('@endswitch', '<?php endswitch; ?>', $this->content);
		
		// Replace break
		$this->content = preg_replace_callback('/(@break(\s?\((.*?)\))?)/', fn ($matches) => (array_key_exists(3, $matches)) ? '<?php if(' . $matches[3] . '): ?>break;<?php endif; ?>' : '<?php break; ?>', $this->content);
		
		// Replace if/else/endif
		$this->content = preg_replace_callback('/@if\s?\((.*)\)/', fn ($matches) => "<?php if ($matches[1]): ?>", $this->content);
		$this->content = preg_replace_callback('/@elseif\s?\((.*)\)/', fn ($matches) => "<?php elseif ($matches[1]): ?>", $this->content);
		$this->content = str_replace('@else', '<?php else: ?>', $this->content);
		$this->content = str_replace('@endif', '<?php endif; ?>', $this->content);
		return $this;
	}
	
	public function processGeneralUtilities():static
	{
		// Replace dd
		$this->content = preg_replace_callback('/@dd\((.*)\)/', fn ($matches) => "<?php dd($matches[1]) ?>", $this->content);
		return $this;
	}
	
	public function processLayoutUtilities():static
	{
		// Replace include
		// Match: @include('view', ['key' => 'value'])
		$this->content = preg_replace_callback('/@include\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(\[[^\)]*\]))?\s*\)/', function ($matches) {
			$view = $matches[1];
			if (!empty($matches[2]))
				return "<?= " . Engine::class . "::renderStatic('$view', $matches[2]); ?>";
			return "<?= " . Engine::class . "::renderStatic('$view', get_defined_vars()); ?>";
		}, $this->content);
		return $this;
	}
	
	public function processFormUtilities():static
	{
		// Replace @csrf with actual csrf_token
		$this->content = preg_replace_callback('/@csrf/', fn () => '<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">', $this->content);
		// Replace @method with actual http method
		$this->content = preg_replace_callback('/@method\s?\((.*?)\)/', fn ($match) => '<input type="hidden" name="_method" value="<?= ' . $match[1] . ' ?>">', $this->content);
		return $this;
	}
	
	public function processLoops():static
	{
		// Replace foreach
		$this->content = preg_replace_callback('/@foreach\s*\((.+?)\s+as\s+(.+?)\)/', function ($matches) {
			$iterable = trim($matches[1]);
			$variables = trim($matches[2]);
			return "<?php foreach ($iterable as $variables): ?>";
		}, $this->content);
		$this->content = str_replace('@endforeach', '<?php endforeach; ?>', $this->content);
		return $this;
	}
	
	public function processVariables():static
	{
		// Replace Variables
		$this->content = preg_replace_callback('/{{\s?(.*?)\s?}}/', fn ($match) => $match[1] === '$slot' ? "<?= $match[1] ?>" : "<?= htmlspecialchars({$match[1]}) ?>",
			preg_replace_callback('/{!!\s?(.*?)\s?!!}/', fn ($match) => "<?= {$match[1]} ?>", $this->content));
		return $this;
	}
	
	public function getProcessed():string
	{
		$content = $this->content;
		$this->content = '';
		return $content;
	}
}
