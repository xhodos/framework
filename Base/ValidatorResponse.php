<?php

namespace Hodos\Base;

use Hodos\Stack\XObject;

class ValidatorResponse
{
	private static ?ValidatorResponse $instance = NULL;
	public static ?XObject $errors;
	
	public function __construct(public Validator $validator)
	{
		self::$instance = $this;
	}
	
	public function validate():?XObject
	{
		self::$errors = $this->errors();
		return $this->errors();
	}
	
	public static function unstackErrors():void
	{
		self::$errors = new XObject();
		self::$instance->validator->setErrorBag(self::$errors);
	}
	
	public function stackErrors(array $errors):void
	{
		foreach ($errors as $key => $error) {
			if (is_array($error))
				foreach ($error as $value)
					self::$errors->$key[] = $value;
			else
				self::$errors->$key[] = $error;
		}
		$this->validator->setErrorBag(self::$errors);
	}
	
	public function errors():?XObject
	{
		return $this->validator->validatorErrors();
	}
	
	public function failed():bool
	{
		return $this->validator->validatorFailed();
	}
	
	public function passed():bool
	{
		return $this->validator->validatorPassed();
	}
}
