<?php

namespace Hodos\Stack;

trait SoftDeletable
{
	private bool $isSoftDeleted = true;
	
	private bool $trashed = false;
	
	public static function withTrashed():static
	{
		$instance = self::__instantiate();
		$instance->trashed = true;
		return $instance;
	}
	
	public function isTrashed():bool
	{
		return $this->trashed;
	}
}
