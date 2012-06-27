<?php
	class Db
	{
		static private $link;
		static private $log = false;
		
		static public function SetLogging($log)
		{
			self::$log = $log;
		}
		
		static public function Init()
		{
			if (self::$link != NULL)
			{
				return;
			}
			
			self::$link = mysql_connect(MYSQL_HOSTNAME, MYSQL_USERNAME, MYSQL_PASSWORD);
			if (!self::$link)
			{
				throw new Exception("Db::Init() failed because mysql_connect() failed.");
			}
			
			mysql_query("USE `".MYSQL_DATABASE."`", self::$link); // TODO: error checking
			print mysql_error();
			mysql_query("SET NAMES utf8", self::$link); // TODO: error checking
			print mysql_error();
			mysql_query("SET CHARACTER SET utf8", self::$link); // TODO: error checking
			print mysql_error();
		}
		
		static public function Escape($var)
		{
			self::Init();
			
			if (is_int($var) || is_float($var))
			{
				return $var;
			}
			elseif (is_bool($var))
			{
				return (int) $var;
			}
			elseif (is_null($var))
			{
				return "NULL";
			}
			elseif (is_string($var))
			{
				return "'".mysql_real_escape_string($var, self::$link)."'";
			}
			else
			{
				return "'".mysql_real_escape_string(serialize($var), self::$link)."'";
			}
		}
		
		static public function EscapeSet($array)
		{
			$sql = "";
			foreach ($array as $key => $value)
			{
				$sql .= "`" . $key . "` = " . self::Escape($value) . ", ";
			}
			return substr($sql, 0, -2);
		}
		
		static public function Query($sql, $key=null)
		{
			$sql = trim($sql);
			
			if (self::$log)
			{
				Log::Message("Db::Query: " . substr($sql, 0, 2000));
			}
			
			$result = array();
			
			self::Init();
			
			$res = mysql_query($sql, self::$link);
			
			if ($errno = mysql_errno())
			{
				throw new Exception(
					"Db::Query() failed because mysql_query() failed.\n".
					"Errno: ".$errno."\n".
					"Error: ".mysql_error()."\n".
					"Query: ".$sql."\n"
				);
			}
			
			$num = 0;
			if ($res != NULL && strpos(trim(strtolower($sql)), "select") === 0)
			{
				while ($row = mysql_fetch_assoc($res))
				{
					if ($key == null || !array_key_exists($key, $row))
					{
						$i = $num;
					}
					else
					{
						$i = $row[$key];
					}
					$result[$i] = $row;
					$num ++;
				}
				if (self::$log)
				{
					Log::Message("  Rows returned: " . $num);
				}
			}
			elseif (strpos(trim(strtolower($sql)), "insert") === 0)
			{
				$result = mysql_insert_id(self::$link);
				if (self::$log)
				{
					Log::Message("  Last insert id: " . $result);
				}
			}
			
			return $result;
		}
		
		static public function StartTransaction()
		{
			self::Query("SET autocommit=0");
			self::Query("BEGIN");
		}
		
		static public function Commit()
		{
			self::Query("COMMIT");
		}
		
		static public function Rollback()
		{
			self::Query("ROLLBACK");
		}
	}
?>