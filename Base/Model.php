<?php

namespace Hodos\Base;

use Exception;
use mysqli;
use mysqli_sql_exception;
use stdClass;
use Hodos\Stack\BuildQuery;
use Hodos\Stack\Grammar;
use Hodos\Stack\HasRelationship;

class Model
{
	use BuildQuery, HasRelationship;
	
	public $attributes;
	
	protected $columns = [];
	
	protected $hidden = [];
	
	protected ?mysqli $db;
	
	protected $query;
	
	protected $table;
	
	private $original;
	
	private int $count;
	
	private ?string $statement = NULL;
	
	private array $queryStack = [];
	
	protected array $operators = [
		'comparison' => [],
		'logical' => [],
	];
	
	public function __construct()
	{
		$this->setTable();
		$this->db = DB::__instance()->connection;
		
		if (!self::$instance || (self::$instance && (strtolower(get_class(self::$instance)) !== get_class($this))))
			self::$instance = $this;
	}
	
	public function getTable()
	{
		return $this->table;
	}
	
	public function get(array $columns = ['*'])
	{
		$result = [];
		
		try {
			$this->buildQuery('SELECT');
			$query = $this->performGet($columns);
			$this->count = $query->num_rows;
			
			while ($row = $query->fetch_object())
				$result[] = $row;
			$temp_result = $result;
			
			foreach ($temp_result as $key => $values) {
				$instance = new $this;
				$result[$key] = $instance;
				$instance->original = $values;
				$instance->attributes = new stdClass();
				
				foreach ($values as $key => $value)
					if (!in_array($key, $this->hidden)) {
						$instance->$key = $value;
						$instance->attributes->$key = $value;
					}
			}
			unset($temp_result);
			return $result;
		} catch (Exception $exception) {
			dd($exception->getMessage(), $exception->getTrace());
		}
	}
	
	public static function all()
	{
		$instance = self::__instantiate();
		$instance->statement = '';
		return $instance->get();
	}
	
	public static function first()
	{
		$instance = self::__instantiate();
		return ($instance->statement ? $instance->get() : $instance->all())[0];
	}
	
	public static function insert(array $attributes)
	{
		$instance = self::__instantiate();
		try {
			if (!empty($attributes))
				$instance->statement = '';
			else
				throw new Exception('Empty SQL statement.');
			
			$instance->buildQuery('INSERT');
			$statement = $instance->prepareInsertStatement($attributes);
			
			try {
				$query = $instance->performQuery($statement);
				
				if (!$query)
					return false;
				return $query;
			} catch (Exception $exception) {
				throw $exception;
			}
		} catch (Exception $exception) {
			throw $exception;
		}
	}
	
	public function update(array $attributes)
	{
		try {
			$this->buildQuery('UPDATE');
			$query = $this->performUpdate($attributes);
			if (!$this->db->affected_rows)
				return false;
			return $query;
		} catch (Exception $exception) {
			throw $exception;
		}
	}
	
	public function count()
	{
		$this->get();
		return $this->count;
	}
	
	private function performGet($columns)
	{
		$columsToString = implode(', ', $columns);
		$statement = preg_replace("/\{table\}/", "`$this->table`", preg_replace("/\{columns\}/", $columsToString, $this->statement));
		return $this->performQuery($statement);
	}
	
	private function prepareInsertStatement($attributes)
	{
		try {
			return $this->validateInsert($attributes);
		} catch (Exception $exception) {
			throw $exception;
		}
	}
	
	private function performUpdate($attributes)
	{
		$pairCount = 0;
		$column_value_pairs = '';
		$attributeCount = count($attributes);
		
		foreach ($attributes as $column => $value) {
			$pairCount++;
			$column_value_pairs .= "`$column` = '$value'" . ($pairCount < $attributeCount ? ', ' : NULL);
		}
		
		$statement = preg_replace("/\{table\}/", "`$this->table`", preg_replace("/\{column_value_pairs\}/", $column_value_pairs, $this->statement));
		return $this->performQuery($statement);
	}
	
	/**
	 * Summary of performQuery
	 *
	 * @param mixed $statement
	 * @return bool|\mysqli_result
	 * @throws mysqli_sql_exception
	 */
	private function performQuery($statement)
	{
		$this->statement = NULL;
		$this->query = $statement;
		
		try {
			return $this->db->execute_query($this->query);
		} catch (mysqli_sql_exception $exception) {
			$message = $exception->getMessage() . "<p>Query: $this->query</p>";
			throw new mysqli_sql_exception($message);
		}
	}
	
	private function setTable(?string $table = NULL)
	{
		if (!empty($table))
			$this->table = $table;
		else {
			if (empty($this->table)) {
				$underscored_name = getUnderscoredClassName(get_called_class());
				$grammar = new Grammar($underscored_name);
				$this->table = $grammar->getPlural();
			}
		}
	}
	
	private function showTableColumnData()
	{
		$column_details = [];
		$columns = $this->db->query("SHOW COLUMNS FROM `$this->table`");
		while ($row = $columns->fetch_object())
			$column_details[] = $row;
		return $column_details;
	}
	
	private function validateInsert(array $attributes)
	{
		$columns = '';
		$values = '';
		
		$pairCount = 0;
		$attributeCount = count($attributes);
		
		$missing_colums = [];
		$available_colums = [];
		
		$default_colums = [];
		$required_columns = [];
		$enum_column_pairs = [];
		$column_details = $this->showTableColumnData();
		
		foreach ($column_details as $key => $column_detail) {
			if (empty($column_detail->Default)) {
				if (strtolower($column_detail->Null) === 'no' && !str_contains($column_detail->Extra, 'auto_increment'))
					$required_columns[] = strtolower($column_detail->Field);
			} else {
				$default_colums[$column_detail->Field] = $column_detail->Default;
			}
			
			if (str_contains($column_detail->Type, 'enum(')) {
				preg_match_all("/('\w+')/", $column_detail->Type, $matches);
				if (!empty($matches[0]))
					$enum_column_pairs[strtolower($column_detail->Field)] = $matches[0];
			}
		}
		
		foreach ($attributes as $key => $value) {
			$column = strtolower($key);
			if (in_array($column, $required_columns))
				$available_colums[] = $column;
			
			if (array_key_exists($column, $enum_column_pairs)) {
				if (!in_array($value, $enum_column_pairs[$column]))
					$attributes[$column] = array_key_exists($column, $default_colums) ? $default_colums[$column] : $enum_column_pairs[$column][0];
			}
		}
		$missing_colums = array_diff($required_columns, $available_colums);
		
		foreach ($attributes as $column => $value) {
			$pairCount++;
			$columns .= "`$column`" . ($pairCount < $attributeCount ? ', ' : NULL);
			$values .= (is_string($value) ? (!$value ? '?' : "'$value'") : (!empty($value) ? $value : 'NULL')) . ($pairCount < $attributeCount ? ', ' : NULL);
		}
		$statement = preg_replace("/\{table\}/", "`$this->table`", preg_replace("/\{columns\}/", "($columns)", preg_replace("/\{values\}/", "($values)", $this->statement)));
		
		if (!empty($missing_colums)) {
			$colum_to_string = implode(', ', $missing_colums);
			throw new mysqli_sql_exception("Error: The following fields are required but missing in the query: $colum_to_string<p>Query: $statement</p>", 1);
		}
		return $statement;
	}
}
