<?php

namespace Hodos\Base;

use Exception;
use mysqli;

final class DB
{
	public ?mysqli $connection;
	
	private static $instance;
	
	private function __construct()
	{
	}
	
	public static function __instance()
	{
		if (!self::$instance)
			self::$instance = new self;
		return self::$instance->config();
	}
	
	private function config()
	{
		try {
			$env = strtoupper(config('APP_ENV'));
			$host = env("DB_HOST_$env") ?? env('DB_HOST');
			$user = env("DB_USER_$env") ?? env('DB_USER');
			$port = (int) (env("DB_PORT_$env") ?? env('DB_PORT'));
			$password = env("DB_PASSWORD_$env") ?? env('DB_PASSWORD');
			$database = env("DB_DATABASE_$env") ?? env('DB_DATABASE');
			
			self::$instance->connection = new mysqli($host, $user, $password, $database, $port);
		} catch (Exception $exception) {
			dd('<strong>Error: </strong>' . $exception->getMessage(), '<strong>in file: </strong>' . $exception->getFile(), '<strong>on line: </strong>' . $exception->getLine());
		}
		return self::$instance;
	}
}
