<?php

namespace Hodos\Base;

use Hodos\Stack\XObject;

class Validator
{
	private ?XObject $errorBag;
	private static ?Validator $instance = NULL;
	
	private function __construct()
	{
		if (!self::$instance || (self::$instance && (strtolower(get_class(self::$instance)) !== get_class($this))))
			self::$instance = $this;
	}
	
	public static function init(array $data, array $rules):ValidatorResponse
	{
		$ValidatorObject = new Validator;
		$data_keys = array_keys($data);
		
		foreach ($rules as $key => $rule) {
			if (in_array('required', $rule))
				if (!in_array($key, $data_keys))
					$is_required[] = $key;
		}
		
		$ValidatorObject->make($data, $rules);
		return new ValidatorResponse($ValidatorObject);
	}
	
	private function make(array $data, array $rules):void
	{
		$this->errorBag = new XObject();
		$checkBailed = [];
		
		foreach ($rules as $field => $ruleSets) {
			foreach ($ruleSets as $ruleSet) {
				$ruleSplit = preg_split("/:/", $ruleSet);
				$rule = $ruleSplit[0];
				$ruleOption = $ruleSplit[1] ?? NULL;
				
				switch ($rule) {
					case 'bail':
						$checkBailed[] = $field;
						break;
					case 'required':
						if (!isset($data[$field]))
							$this->checkValidate($field, 'isRequired');
						break;
					case 'string':
						if ((isset($data[$field]) && empty($data[$field])) || (!empty($data[$field]) && !is_string($data[$field])))
							$this->checkValidate($field, 'isNotString');
						break;
					case 'numeric':
						if (!is_numeric($data[$field]))
							$this->checkValidate($field, 'isNotNumeric');
						break;
					case 'email':
						if (isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL))
							$this->checkValidate($field, 'isNotEmail');
						break;
					case 'array':
						if (isset($data[$field]) && !is_array($data[$field]))
							$this->checkValidate($field, 'isNotArray');
						break;
					case 'date':
						$format = $ruleOption ?? 'Y-m-d';
						if (isset($data[$field]) && !date_create_from_format($format, $data[$field]))
							$this->checkValidate($field, 'isNotDate');
						break;
					default:
						# code...
						break;
				}
			}
		}
		
		if (!empty($checkBailed))
			foreach ($checkBailed as $field)
				$this->checkBailed(array_unique([$field]));
	}
	
	/**
	 * Summary of checkValidate
	 *
	 * @param string $field
	 * @param {('isRequired'|isNotArray|isNotDate|isNotString|isNotEmail|isNotNumeric)} $ruleFunc
	 * @return void
	 */
	private function checkValidate(string $field, string $ruleFunc)
	{
		if (empty($this->errorBag->$field))
			$this->errorBag->$field = [];
		$this->{$ruleFunc}(array_unique([$field]));
	}
	
	private function checkBailed(array $fields):void
	{
		foreach ($fields as $key => $field) {
			if (!empty($this->errorBag->$field) && count($this->errorBag->$field) > 1) {
				$errorCount = count($this->errorBag->$field);
				array_splice($this->errorBag->$field, 1, $errorCount - 1);
			}
		}
	}
	
	private function isRequired(array $fields):void
	{
		$this->pushError($fields, 'field is required.');
	}
	
	private function isNotArray(array $fields):void
	{
		$this->pushError($fields, 'field must be an array.');
	}
	
	private function isNotDate(array $fields):void
	{
		$this->pushError($fields, 'field must be a valid date string.');
	}
	
	private function isNotString(array $fields):void
	{
		$this->pushError($fields, 'field must be a string.');
	}
	
	private function isNotEmail(array $fields):void
	{
		$this->pushError($fields, 'field must be a valid E-Mail address.');
	}
	
	private function isNotNumeric(array $fields):void
	{
		$this->pushError($fields, 'field must be numeric.');
	}
	
	private function pushError(array $fields, string $message)
	{
		foreach ($fields as $key => $field)
			$this->errorBag->$field[] = 'The ' . mb_convert_case(str_replace('_', ' ', $field), MB_CASE_TITLE) . " $message";
	}
	
	public function setErrorBag(XObject $errorBag):void
	{
		$this->errorBag = $errorBag;
	}
	
	public function validatorErrors():?XObject
	{
		return $this->errorBag;
	}
	
	public function validatorPassed():bool
	{
		return $this->validatorDidPass();
	}
	
	public function validatorFailed():bool
	{
		return !$this->validatorDidPass();
	}
	
	private function validatorDidPass():bool
	{
		return empty((array) $this->errorBag);
	}
}
