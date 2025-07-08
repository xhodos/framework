<?php

namespace Hodos\Base;

use App\Models\User;
use Hodos\Stack\XObject;

use Error;

class Auth
{
	private ?string $control = 'default';
	private ?string $table = 'users';
	
	private ?XObject $cases;
	private ?XObject $controls;
	
	private static ?Auth $instance = NULL;
	
	public static function __instance()
	{
		if (!self::$instance)
			self::$instance = new self;
		
		self::$instance->cases = new XObject();
		self::$instance->controls = new XObject();
		return self::$instance;
	}
	
	public static function control(?string $control = 'default'):self
	{
		$instance = self::__instance();
		$instance->control = $control;
		return $instance->init();
	}
	
	private static function getModel(string $class):Model
	{
		return new $class;
	}
	
	public static function check()
	{
		$instance = self::__instance();
		return !empty($_SESSION[SESSION_NAME . "_{$instance->table}_auth"]);
	}
	
	public static function destroy()
	{
		$instance = self::__instance();
		unset($_SESSION[SESSION_NAME . "_{$instance->table}_auth"]);
	}
	
	public static function login(object|string|int $id)
	{
		$instance = self::__instance();
		if (gettype($id) === 'object') {
			if (property_exists($id, 'id'))
				$id = $id->id;
			else
				return false;
		}
		$_SESSION[SESSION_NAME . "_{$instance->table}_auth"] = $id;
		return true;
	}
	
	/**
	 * @return Auth|null
	 */
	private function init()
	{
		$controls = config('auth.controls');
		$cases = config('auth.cases');
		
		if (!empty($cases) && is_array($cases)) {
			foreach ($cases as $i => $case) {
				$this->cases->{strtolower($i)} = xobject()->fromArray($case);
				foreach ($controls as $j => $control)
					if (strtolower($i) === strtolower($control['case']))
						$this->controls->{strtolower($j)} = xobject()->fromArray(array_merge($control, $case));
			}
		}
		
		if (property_exists($this->controls, $this->control)) {
			if (strtolower($this->controls->{$this->control}->use) === 'engine') {
				$model = $this::getModel($this->controls->{$this->control}->model);
				$table = $model->getTable();
			} else
				$table = $this->controls->{$this->control}->table;
			$this->table = $table;
		}
		return self::$instance;
	}
}
