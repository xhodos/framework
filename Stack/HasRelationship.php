<?php

namespace Hodos\Stack;

use Exception;

trait HasRelationship
{
	public function belongsTo(string $model, $localKey = NULL, $foreignKey = 'id')
	{
		$belongs = $this->belongs($model, $localKey, $foreignKey);
		if ($belongs) {
			$related = $belongs->first();
			$related->related = $this;
			return $related;
		}
	}
	
	public function belongsToMany(string $model, $localKey = NULL, $foreignKey = 'id')
	{
		$belongs = $this->belongs($model, $localKey, $foreignKey);
		if ($belongs) {
			$related = $belongs->first();
			foreach ($related as $value)
				$value->related = $this;
			return $related;
		}
	}
	
	public function hasMany(string $model, $foreignKey = NULL, $localKey = 'id')
	{
		$has = $this->has($model, $foreignKey, $localKey);
		if ($has) {
			$related = $has->get();
			foreach ($related as $value)
				$value->related = $this;
			return $related;
		}
	}
	
	public function hasOne(string $model, $foreignKey = NULL, $localKey = 'id')
	{
		$has = $this->has($model, $foreignKey, $localKey);
		if ($has) {
			$related = $has->first();
			$related->related = $this;
			return $related;
		}
	}
	
	private function belongs(string $model, $localKey, $foreignKey)
	{
		if (class_exists($model)) {
			$foreignKeyExists = false;
			$relatedClass = new $model;
			
			if (!$localKey)
				$localKey = getUnderscoredClassName(get_class($relatedClass)) . '_id';
			
			foreach ($relatedClass->showTableColumnData() as $key => $columns)
				if (strtolower($columns->Field) === strtolower($foreignKey)) {
					$foreignKeyExists = true;
					break;
				}
			
			if (!$foreignKeyExists)
				throw new Exception("Unknown column: $foreignKey; in table $relatedClass->table", 1);
			
			if (empty($this->$localKey))
				throw new Exception("Unknown column: $localKey; in table $this->table", 1);
			return $relatedClass::where([$foreignKey => $this->$localKey]);
		}
		return false;
	}
	
	private function has(string $model, $foreignKey, $localKey)
	{
		if (class_exists($model)) {
			$relatedClass = new $model;
			$foreignKeyExists = false;
			
			if (!$foreignKey)
				$foreignKey = getUnderscoredClassName(get_class($this)) . '_id';
			
			foreach ($relatedClass->showTableColumnData() as $key => $columns)
				if (strtolower($columns->Field) === strtolower($foreignKey)) {
					$foreignKeyExists = true;
					break;
				}
			
			if (!$foreignKeyExists)
				throw new Exception("Unknown column: $foreignKey; in table $relatedClass->table", 1);
			
			if (empty($this->$localKey))
				throw new Exception("Unknown column: $localKey; in table $this->table", 1);
			return $relatedClass::where([$foreignKey => $this->$localKey]);
		}
		return false;
	}
}
