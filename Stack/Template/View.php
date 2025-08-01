<?php
namespace Hodos\Stack\Template;

use RuntimeException;
use Throwable;

class View
{
	public function __construct(public string $view, public array $params = [])
	{
	}
	
	public function __toString():string
	{
		try {
			return $this->render(true); // Silent, returns as string
		} catch (Throwable $e) {
			return "View error: " . $e->getMessage();
		}
	}
	
	public function render(bool $silent = false, $output = 'php://output'):self|string
	{
		if (!file_exists($this->view))
			throw new RuntimeException("View file {$this->view} not found.");
		
		if (!empty($this->params))
			extract($this->params, EXTR_SKIP);
		
		ob_start();
		include $this->view;
		$content = ob_get_clean();
		
		if ($silent)
			return $content; // Just return as string
		
		// Write to a custom output stream (defaults to stdout)
		$stream = fopen($output, 'w');
		if ($stream) {
			fwrite($stream, $content);
			fclose($stream);
		} else
			throw new RuntimeException("Could not open output stream: $output");
		return $this;
	}
	
	public function setParams(array $data):self
	{
		$this->params = array_merge($this->params, $data);
		return $this;
	}
	
}
