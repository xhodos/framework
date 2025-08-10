<?php

namespace Hodos\Base;

use Exception;
use Hodos\Stack\XObject;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use ReflectionClass;
use stdClass;
use Hodos\Stack\BuildQuery;
use Hodos\Stack\Grammar;
use Hodos\Stack\HasRelationship;

#[\AllowDynamicProperties]
class Model
{
	use BuildQuery, HasRelationship;
	
	public ?XObject $attributes;
	
	protected $columns = [];
	
	protected $hidden = [];
	
	protected ?mysqli $db;
	
	protected $query;
	
	protected $table;
	
	private ?XObject $original;
	
	private int $count;
	
	private $selected = [];
	
	protected ?string $statement = NULL;
	
	private array $queryStack = [];
	
	private array $operators = [
		'comparison' => [],
		'logical' => [],
	];
	
	public function __construct()
	{
		$this->setTable();
		$this->db = DB::__instance()->connection;
	}
	
	public static function getTable()
	{
		return self::__instantiate()->table;
	}
	
	public static function all()
	{
		$instance = self::__instantiate();
		$instance->statement = '';
		return $instance->get();
	}
	
	public static function basicPaginate(int $perPage, ?int $page = 1, ?array $orderByColumns = ['id' => 'asc']):?XObject
	{
		$instance = self::__instantiate();
		$page = max($page, 1);
		$total_items = $instance->count();
		
		if ($perPage) {
			$offset = ($page - 1) * $perPage;
			$total_pages = ceil($total_items / $perPage);
			
			if ($offset >= $total_items) {
				$page = $total_pages;
				$offset = $page > 1 ? $total_items - 1 : 0;
			}
			$instance->orderBy($orderByColumns)->limit($perPage)->offset($offset);
		} else {
			$total_pages = 1;
			$instance->orderBy($orderByColumns);
		}
		
		$pagination = new stdClass();
		$data = xobject();
		
		$items = $instance->get();
		$pagination->has_prev = $page > 1;
		$pagination->has_next = $page < $total_pages;
		$pagination->prev = $pagination->has_prev ? $page - 1 : NULL;
		$pagination->next = $pagination->has_next ? $page + 1 : NULL;
		$pagination->current = $page;
		$paginationDetails = compact('items', 'pagination', 'total_items', 'total_pages');
		return $data->fromArray($paginationDetails);
	}
	
	public static function count()
	{
		$instance = self::__instantiate();
		$statementPartial = !empty($instance->statement) ? " $instance->statement" : '';
		$statement = "SELECT COUNT(*) AS total FROM `$instance->table` $statementPartial";
		return $instance->performQuery($instance->cleanClause($statement))->fetch_object()->total ?? 0;
	}
	
	public static function limit(int $limit)
	{
		$instance = self::__instantiate();
		
		if (!$instance->statement)
			$instance->statement = '';
		$instance->statement .= !str_contains($instance->statement, ' LIMIT ') ? " LIMIT $limit" : '';
		return $instance;
	}
	
	public static function first():mixed
	{
		$instance = self::__instantiate();
		
		if ($instance->statement) {
			$result = $instance->get();
			return !empty($result) ? $result[0] : NULL;
		}
		return !empty($instance->all()) ? $instance->all()[0] : NULL;
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
				return self::__instantiate()::where(['id' => $instance->db->insert_id])->first();
			} catch (Exception $exception) {
				return $exception;
			}
		} catch (Exception $exception) {
			return $exception;
		}
	}
	
	public static function offset(int $offset)
	{
		$instance = self::__instantiate();
		
		if (!$instance->statement)
			$instance->statement = '';
		$instance->statement .= !str_contains($instance->statement, ' OFFSET ') ? " OFFSET $offset" : '';
		return $instance;
	}
	
	public static function orderBy(?array $orderByColumns = ['id' => 'desc'])
	{
		$instance = self::__instantiate();
		$orderByStatement = 'ORDER BY ';
		
		if (!$instance->statement)
			$instance->statement = '';
		
		foreach ($orderByColumns as $column => $direction)
			$orderByStatement .= "`$instance->table`." . (gettype($column) !== 'string' ? "`$direction` ASC" : "`$column` " . strtoupper($direction)) . (array_key_last($orderByColumns) !== $column ? ' , ' : NULL);
		$instance->statement .= !str_contains($instance->statement, ' ORDER BY ') ? " $orderByStatement" : '';
		return $instance;
	}
	
	public static function stackObject():XObject
	{
		$instance = self::__instantiate();
		$final_result = xobject();
		$result = !$instance->statement ? $instance->all() : $instance->get();
		
		foreach ($result as $key => $value)
			$final_result->{$key} = xobject()::fromArray((array) $value->attributes);
		return $final_result;
	}
	
	public static function toArray():array
	{
		$instance = self::__instantiate();
		$final_result = [];
		
		if ($instance->query && str_contains($instance->query, 'SELECT')) {
			$result = $instance->performQuery($instance->query);
			while ($row = $result->fetch_object())
				$final_result[] = (array) $row;
		} else
			foreach ((!$instance->statement ? $instance->all() : $instance->get()) as $key => $value)
				$final_result[] = (array) $value->attributes;
		return count($final_result) === 1 ? $final_result[0] : $final_result;
	}
	
	public function delete():mysqli_result|Exception|bool
	{
		try {
			if (!$this->statement)
				$this->buildStatement();
			else
				$this->statement = "WHERE " . preg_replace('/\b(AND|OR)\s*$/i', '', implode(' ', $this->queryStack));
			$this->buildQuery('DELETE');
			$this->statement = str_replace('{table}', "`$this->table`", $this->statement);
			$query = $this->performQuery($this->statement);
			if (!$this->db->affected_rows)
				return false;
			return $query;
		} catch (Exception $exception) {
			return $exception;
		}
	}
	
	public function get(array $columns = ['*'])
	{
		$result = [];
		
		try {
			$this->buildQuery('SELECT');
			$statement = $this->performGet($columns);
			$query = $this->performQuery($this->cleanClause($statement));
			$this->count = $query->num_rows;
			
			while ($row = $query->fetch_object())
				$result[] = $row;
			$temp_result = $result;
			
			foreach ($temp_result as $key => $values) {
				$instance = new $this;
				$result[$key] = $instance;
				$this->buildSingleInstance($instance, $statement, $values);
			}
			unset($temp_result);
			return $result;
		} catch (Exception $exception) {
			dd($exception->getMessage(), $exception->getTrace());
		}
	}
	
	public function getSelected():array
	{
		return $this->selected;
	}
	
	public function update(array $attributes):mysqli_result|Exception|bool
	{
		try {
			if (!$this->statement)
				$this->buildStatement();
			$this->buildQuery('UPDATE');
			$query = $this->performUpdate($attributes);
			if (!$this->db->affected_rows)
				return false;
			return $query;
		} catch (Exception $exception) {
			return $exception;
		}
	}
	
	private function buildSingleInstance(self $instance, string $statement, object $values):void
	{
		$instance->columns = $this->columns;
		$instance->db = $this->db;
		$instance->hidden = $this->hidden;
		$instance->operators = $this->operators;
		$instance->query = $this->query;
		$instance->queryStack = $this->queryStack;
		$instance->statement = $statement;
		
		$instance->original = xobject();
		$instance->attributes = xobject();
		
		foreach ($values as $j => $value) {
			foreach ($instance->columns as $column)
				if ($column === $j)
					$instance->selected[$column] = $value;
			
			if (!in_array($j, $this->hidden)) {
				$instance->{$j} = $value;
				$instance->attributes->{$j} = $value;
			}
			$instance->original->{$j} = $value;
		}
	}
	
	private function buildStatement():void
	{
		$primary_column = '';
		$primary_value = NULL;
		
		foreach ($this->showTableColumnData() as $key => $column)
			if (strtoupper($column->Key) === 'PRI') {
				$primary_column = $column->Field;
				break;
			} else {
				if (array_key_last($this->showTableColumnData()) === $key)
					$primary_column = $this->showTableColumnData()[0]->Field;
			}
		foreach ($this->attributes as $column => $value)
			if (strtolower($column) === strtolower($primary_column)) {
				$primary_value = $value;
				break;
			}
		$this->buildWhere([$primary_column => $primary_value], 'AND', '=');
	}
	
	private function performGet($columns):array|string|null
	{
		$columnsToString = implode(', ', $columns);
		return preg_replace("/\{table\}/", "`$this->table`", preg_replace("/\{columns\}/", $columnsToString, $this->statement));
	}
	
	/**
	 * Summary of performQuery
	 *
	 * @param mixed $statement
	 * @return bool|mysqli_result
	 * @throws mysqli_sql_exception
	 */
	private function performQuery(mixed $statement):mysqli_result|bool
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
	
	private function cleanClause(mixed $statement):string
	{
		$instanceReflection = new ReflectionClass($this);
		if ($instanceReflection->hasMethod('showTrashed'))
			$statement = $instanceReflection->getMethod('showTrashed')->invoke($this) ? $statement : $this->cleanDeleted($statement);
		return $statement;
	}
	
	private function cleanDeleted(string $query)
	{
		$part = "WHERE `$this->softDeleteColumn` IS ";
		
		if (str_contains($query, 'WHERE'))
			$query = str_replace('WHERE', $part . ($this->showTrashedOnly() ? "NOT " : "") . "NULL AND", $query);
		else {
			if (preg_match('/\b(LIMIT|OFFSET|ORDER BY)\b/i', $query)) {
				$replaced = false;
				$query = preg_replace_callback('/\b(LIMIT|OFFSET|ORDER BY)\b/i', function ($match) use (&$replaced, $part) {
					if (!$replaced) {
						$replaced = true;
						return $part . ($this->showTrashedOnly() ? "NOT " : "") . "NULL " . $match[0];
					}
					return $match[0];
				}, $query);
			} else
				$query .= $part . ($this->showTrashedOnly() ? "NOT " : "") . "NULL";
		}
		return $query;
	}
	
	private function performUpdate($attributes):mysqli_result|bool
	{
		$pairCount = 0;
		$column_value_pairs = '';
		$attributeCount = count($attributes);
		
		foreach ($attributes as $column => $value) {
			$pairCount++;
			$column_value_pairs .= "`$column` = " . (!is_null($value) ? (is_numeric($value) || is_bool($value) ? (is_bool($value) ? (int) $value : $value) : "'$value'") : "NULL") . ($pairCount < $attributeCount ? ', ' : NULL);
		}
		$statement = str_replace("{table}", "`$this->table`", str_replace("{column_value_pairs}", $column_value_pairs, $this->statement));
		return $this->performQuery($this->cleanClause($statement));
	}
	
	private function prepareInsertStatement($attributes):array|string
	{
		try {
			return $this->validateInsert($attributes);
		} catch (Exception $exception) {
			return $exception;
		}
	}
	
	private function setTable(?string $table = NULL):void
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
	
	private function showTableColumnData():array
	{
		$column_details = [];
		$columns = $this->db->query("SHOW COLUMNS FROM `$this->table`");
		while ($row = $columns->fetch_object())
			$column_details[] = $row;
		return $column_details;
	}
	
	private function validateInsert(array $attributes):array|string
	{
		$columns = '';
		$values = '';
		
		$pairCount = 0;
		$attributeCount = count($attributes);
		
		$available_columns = [];
		
		$default_columns = [];
		$required_columns = [];
		$enum_column_pairs = [];
		$column_details = $this->showTableColumnData();
		
		foreach ($column_details as $key => $column_detail) {
			if (empty($column_detail->Default)) {
				if (strtolower($column_detail->Null) === 'no' && !str_contains($column_detail->Extra, 'auto_increment'))
					$required_columns[] = strtolower($column_detail->Field);
			} else {
				$default_columns[$column_detail->Field] = $column_detail->Default;
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
				$available_columns[] = $column;
			
			if (array_key_exists($column, $enum_column_pairs)) {
				if (!in_array($value, $enum_column_pairs[$column]))
					$attributes[$column] = array_key_exists($column, $default_columns) ? $default_columns[$column] : $enum_column_pairs[$column][0];
			}
		}
		$missing_columns = array_diff($required_columns, $available_columns);
		
		foreach ($attributes as $column => $value) {
			$pairCount++;
			$columns .= "`$column`" . ($pairCount < $attributeCount ? ', ' : NULL);
			$values .= (is_string($value) ? (!$value ? '?' : "'$value'") : (!empty($value) ? $value : 'NULL')) . ($pairCount < $attributeCount ? ', ' : NULL);
		}
		$statement = str_replace("{table}", "`$this->table`", str_replace("{columns}", "($columns)", str_replace("{values}", "($values)", $this->statement)));
		
		if (!empty($missing_columns)) {
			$colum_to_string = implode(', ', $missing_columns);
			throw new mysqli_sql_exception("Error: The following fields are required but missing in the query: $colum_to_string<p>Query: $statement</p>", 1);
		}
		return $statement;
	}
}
