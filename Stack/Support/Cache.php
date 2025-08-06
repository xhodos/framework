<?php

namespace Hodos\Stack\Support;

class Cache
{
	protected string $cachePath;
	
	public function __construct(?string $path = NULL)
	{
		$this->cachePath = correctDirPath($path ?? getRootPath() . '/system/framework/cache');
		
		if (!is_dir($this->cachePath))
			mkdir($this->cachePath, 0755, true);
	}
	
	public function get(string $key, $default = NULL):mixed
	{
		$path = $this->getFilePath($key);
		
		if (!file_exists($path))
			return $default;
		
		$data = unserialize(file_get_contents($path));
		
		if ($data['expires'] !== NULL && $data['expires'] < time()) {
			unlink($path);
			return $default;
		}
		return $data['value'];
	}
	
	public function set(string $key, mixed $value, ?int $ttl = NULL):void
	{
		$data = [
			'value' => $value,
			'expires' => $ttl ? time() + $ttl : NULL,
		];
		
		file_put_contents($this->getFilePath($key), serialize($data));
	}
	
	public function has(string $key):bool
	{
		return $this->get($key) !== NULL;
	}
	
	public function forget(string $key):void
	{
		$path = $this->getFilePath($key);
		if (file_exists($path))
			unlink($path);
	}
	
	protected function getFilePath(string $key):string
	{
		return $this->cachePath . '/' . md5($key) . '.cache';
	}
}
