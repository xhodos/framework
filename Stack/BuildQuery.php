<?php

namespace Hodos\Stack;

use Exception;
use Hodos\Base\Model;
use ReflectionClass;

trait BuildQuery
{
	private static ?self $instance = NULL;
	
	protected $softDeleteColumn = 'deleted_at';
	
	private array $statementBuilder = [
		'SELECT' => "SELECT {columns} FROM {table}",
		'INSERT' => "INSERT INTO {table} {columns} VALUES {values}",
		'UPDATE' => "UPDATE {table} SET {column_value_pairs}",
		'DELETE' => "DELETE FROM {table}",
	];
	
	public static function __instantiate()
	{
		if (!self::$instance || (self::$instance && (strtolower(get_class(self::$instance)) !== strtolower(get_class(new (get_called_class()))))))
			self::$instance = new (get_called_class());
		return self::$instance;
	}
	
	public static function where(array $query, string $operator = 'AND', string $comparator = '=')
	{
		$instance = self::__instantiate();
		$instance->buildWhere($query, $operator, $comparator);
		return $instance;
	}
	
	public static function orWhere(array $query)
	{
		return self::where($query, 'OR');
	}
	
	public static function whereGt(array $query)
	{
		return self::where($query, comparator : '>');
	}
	
	public static function orWhereGt(array $query)
	{
		return self::where($query, 'OR', '>');
	}
	
	public static function whereLt(array $query)
	{
		return self::where($query, comparator : '<');
	}
	
	public static function orWhereLt(array $query)
	{
		return self::where($query, 'OR', '<');
	}
	
	public static function whereNot(array $query)
	{
		return self::where($query, comparator : '!=');
	}
	
	public static function orWhereNot(array $query)
	{
		return self::where($query, 'OR', '!=');
	}
	
	private function buildWhere(array $queries, string $operator, string $comparator):void
	{
		foreach ($this->showTableColumnData() as $columnData) {
			$field = $columnData->Field;
			unset($this->$field);
		}
		
		if (!$this->statement)
			$this->statement = "WHERE";
		
		$queryStack = '';
		$queryCount = count($queries);
		
		foreach ($queries as $key => $value) {
			if (!str_ends_with($this->statement, 'WHERE') && !str_ends_with($this->statement, $operator)/* || $instanceReflection->hasMethod('isTrashed')*/)
				$this->statement .= " $operator ";
			else
				$this->statement .= " ";
			$comp = $comparator === '=' || $comparator === '!=' ? (!is_null($value) ? $comparator : ($comparator === '!=' ? "IS NOT" : "IS")) : $comparator;
			$val = $comparator === '=' || $comparator === '!=' ? (!is_null($value) ? (is_numeric($value) || is_bool($value) ? (is_bool($value) ? (int) $value : $value) : "'$value'") : "NULL") : $value;
			$queryStack .= "`$key` $comp $val";
			$this->statement .= "`$key` $comp " . $val . ($key < ($queryCount - 1) ? " $operator" : NULL);
			
			if (!in_array($key, $this->columns))
				$this->columns[] = $key;
		}
		$this->query = $this->statement;
		$this->queryStack[] = $queryStack;
		$this->buildOperators($comparator, $operator);
	}
	
	/**
	 * Summary of buildQuery
	 *
	 * @param string $statement 'SELECT','INSERT','UPDATE','DELETE'
	 * @return void
	 * @throws Exception
	 */
	private function buildQuery(string $statement):void
	{
		if (!is_string($this->statement))
			throw new Exception('Empty SQL statement.');
		$this->statement = $this->statementBuilder[$statement] . " $this->statement";
	}
	
	/**
	 * @param $comparator
	 * @param $operator
	 * @return void
	 */
	private function buildOperators($comparator, $operator):void
	{
		if (!in_array($comparator, $this->operators['comparison']))
			$this->operators['comparison'][] = $comparator;
		
		if (!in_array($operator, $this->operators['logical']))
			$this->operators['logical'][] = $operator;
	}
}
