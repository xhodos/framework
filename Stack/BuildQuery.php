<?php

namespace Hodos\Stack;

use Exception;
use Hodos\Base\Model;

trait BuildQuery
{
	private static $instance;
	
	private array $statementBuilder = [
		'SELECT' => "SELECT {columns} FROM {table}",
		'INSERT' => "INSERT INTO {table} {columns} VALUES {values}",
		'UPDATE' => "UPDATE {table} SET {column_value_pairs}",
	];
	
	public static function __instantiate()
	{
		if (!self::$instance || (self::$instance && (strtolower(get_class(self::$instance)) !== get_class(new (get_called_class())))))
			self::$instance = new (get_called_class());
		return self::$instance;
	}
	
	public static function where(array $query, string $operator = 'AND', string $comparator = '='):Model
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
	
	private function buildWhere(array $queries, string $operator, string $comparator)
	{
		foreach ($this->showTableColumnData() as $key => $columnData) {
			$field = $columnData->Field;
			unset($this->$field);
		}
		
		if (!$this->statement)
			$this->statement = "WHERE";
		$queryCount = count($queries);
		
		foreach ($queries as $key => $value) {
			if (!str_ends_with($this->statement, 'WHERE') && !str_ends_with($this->statement, $operator))
				$this->statement .= " $operator ";
			else
				$this->statement .= " ";
			$comp = $comparator === '=' || $comparator === '!=' ? (!is_null($value) ? $comparator : ($comparator === '!=' ? "IS NOT" : "IS")) : $comparator;
			$val = $comparator === '=' || $comparator === '!=' ? (!is_null($value) ? (is_numeric($value) || is_bool($value) ? (is_bool($value) ? (int) $value : $value) : "'$value'") : "NULL") : $value;
			$this->statement .= "`$key` $comp " . $val . ($key < ($queryCount - 1) ? " $operator" : NULL);
			
			if (!in_array($key, $this->columns))
				$this->columns[] = $key;
		}
		
		$this->query = $this->statement;
		$this->buildOperators($comparator, $operator);
	}
	
	/**
	 * Summary of buildQuery
	 *
	 * @param string $statement :"INSERT","SELECT","UPDATE"
	 * @return void
	 * @throws \Exception
	 */
	private function buildQuery(string $statement)
	{
		if (!is_string($this->statement))
			throw new Exception('Empty SQL statement.');
		$this->statement = $this->statementBuilder[$statement] . " $this->statement";
	}
	
	private function buildOperators($comparator, $operator)
	{
		if (!in_array($comparator, $this->operators['comparison']))
			$this->operators['comparison'][] = $comparator;
		
		if (!in_array($operator, $this->operators['logical']))
			$this->operators['logical'][] = $operator;
	}
}
