<?php

namespace Hodos\Stack\Errors;

use Error;
use Throwable;

class ViewError extends Error
{
	public function __construct(string $message = "View not found", int $code = 0, ?Throwable $previous = NULL)
	{
		parent::__construct($message, $code, $previous);
	}
}
