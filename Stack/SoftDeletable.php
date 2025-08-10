<?php

namespace Hodos\Stack;

trait SoftDeletable
{
	private bool $isSoftDeleted = true;
	
	private bool $onlyTrashed = false;
	private bool $trashed = false;
	
	public static function withTrashed():static
	{
		$instance = self::__instantiate();
		
		if (!$instance->statement)
			$instance->statement = '';
		
		$instance->onlyTrashed = false;
		$instance->trashed = true;
		return $instance;
	}
	
	public static function trashed():static
	{
		$instance = self::__instantiate();
		
		if (!$instance->statement)
			$instance->statement = '';
		
		$instance->onlyTrashed = true;
		$instance->trashed = false;
		return $instance;
	}
	
	public function restore()
	{
		return $this->update([$this->softDeleteColumn => NULL]);
	}
	
	public function showTrashed():bool
	{
		return $this->trashed;
	}
	
	public function showTrashedOnly():bool
	{
		return $this->onlyTrashed;
	}
}
