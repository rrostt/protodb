<?php

require_once('db.php');

class DB {
	static $prefix = "";
	static function config($params) {
		if (isset($params['prefix'])) {
			self::$prefix = $params['prefix'];
		}
	}
	static function tablename($table) {
		return self::$prefix . $table;
	}
	static function ensureTable($table) {
		$tablename = self::$prefix . $table;
		$query = "DESCRIBE `$tablename`";
		if (!mysql_query($query)) {
			$create_query = "CREATE TABLE `$tablename` (`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY)";
			mysql_query($create_query);
			echo mysql_error();
		}
	}

	static function ensureColumn($table, $column) {
		self::ensureTable($table);
		$tablename = self::$prefix . $table;
		$results = mysql_query("DESCRIBE `$tablename` `$column`");
		$count = mysql_num_rows($results);
		if ($count==0) {
			$query = "ALTER TABLE `$tablename` ADD `$column` TEXT";
			mysql_query($query);
			echo mysql_error();
		}
	}

	static function ensureFromWhere($table,$where) {
		if (is_array($where)) {
			foreach($where as $col => $val) {
				self::ensureColumn($table,$col);
			}
		}
	}

	function parseJoin($join, $rhs) {	// parseJoin("<", "updates(rooms.id=updates.rooms_id)")
		$cmd = "LEFT JOIN";
		switch ($join) {
		case "<": $cmd = "LEFT JOIN"; break;
		case ">": $cmd = "RIGHT JOIN"; break;
		case "<>": $cmd = "JOIN"; break;
		}
		
		//  updates(rooms.id=updates.rooms_id)
		//  ----------------------------------	$rhs
		//  -------                             $p[0]
		//          --------------------------  $p[1]
		//          --------                    a
		//                   -----------------  b
		//          -----
		$p = explode("(", $rhs);
		$p[1] = substr($p[1],0,strlen($p[1])-1);
		$ab = explode("=", $p[1]);
		$as = explode(".", $ab[0]);
		$bs = explode(".", $ab[1]);
		
		return array(
			"join" => $cmd,
			"table" => $p[0],
			"on" => $p[1],
			"t1" => $as[0],
			"k1" => $as[1],
			"t2" => $bs[0],
			"k2" => $bs[1]
		);
	}

	function parseTableStatement($table) {
		$parts = explode(" ", $table);
		if (count($parts)>1) {
			$i = 0;
			$statement = "`" . DB::tablename($parts[0]) . "` " . $parts[0];	// prc_rooms rooms
			DB::ensureTable($parts[0]);
	
			while($i<count($parts)-1) {
				$join = self::parseJoin($parts[$i+1], $parts[$i+2]);
				// ensures
				DB::ensureTable($join['table']);
				DB::ensureColumn($join['t1'], $join['k1']);
				DB::ensureColumn($join['t2'], $join['k2']);
				$statement = $statement . " " . $join['join'] . " `" . DB::tablename($join['table']) . "` " . $join['table'] . " ON " . $join['on'];
				$i+=2;
			}
			return $statement;
		} else {
			return "`" . DB::tablename($table) . "`";
		}
	}

	static function getWherestring($where) {
		$wherestr = "";
		if (is_array($where)) {
			$conds = array();
			foreach($where as $col => $val) {
				$conds[] = "`$col`='$val'";
			}
			$wherestr = implode(" AND ", $conds);
		} else if ($where!==null) {
			$wherestr = "`id`='$where'";
		} else {
			$wherestr = "TRUE";
		}
		return $wherestr;
	}

	static function insert($table, $values) {
		$cols = array();
		$vals = array();
		foreach($values as $column => $value) {
			self::ensureColumn($table,$column);
			$cols[] = "`" . $column . "`";
			$vals[] = "'" . mysql_real_escape_string($value) . "'";
		}
		$cols = implode(',',$cols);
		$vals = implode(',',$vals);
		$tablename = self::$prefix . $table;
		$query = "REPLACE INTO `$tablename` ($cols) VALUES ($vals)";
		mysql_query($query);
		echo mysql_error();
		
		$id = mysql_insert_id();
		$rows = self::get($table, $id);
		return $rows[0];
	}

	/*
		$where
			array of columns = value pairs conditioned with AND
		or
			id value
	*/
	static function get($table, $where=null, $what = null) {
		if (strpos($table, " ")===FALSE)
		{
			self::ensureTable($table);
			self::ensureFromWhere($table, $where);
		}

		$wherestr = self::getWherestring($where);

//		$tablename = self::$prefix . $table;
		$tablestatement = self::parseTableStatement($table);
		if ($what===null) {
			$what = "*";
		}

		return fetch_rows("SELECT $what FROM $tablestatement WHERE $wherestr");
	}

	static function getOne($table, $where=null) {
		$arr = self::get($table, $where);
		return $arr[0];
	}
	
	static function getNear($table, $lon,$lat,$radius,$where=null) {
		if (strpos($table, " ")===FALSE)
		{
			self::ensureTable($table);
			self::ensureFromWhere($table, $where);
		}

		$wherestr = self::getWherestring($where);

//		$tablename = self::$prefix . $table;
		$tablestatement = self::parseTableStatement($table);

		$query = "SELECT *,
			6378000 * 2 * ASIN(
				SQRT(
					POWER( SIN(('$lat' - ABS(lat)) * pi()/180 / 2), 2 )
					+
					COS( '$lat' * pi()/180 ) * COS( ABS(lat) * pi()/180 )
					*
					POWER( SIN(('$lon' - lon) * pi()/180 / 2), 2 )
				)
			)
			as distance FROM $tablestatement 
			WHERE $wherestr
			ORDER BY distance"; // AND distance < $radius";

		return fetch_rows($query);
	}

	static function set($table, $where, $values) {
		$wherestr = self::getWherestring($where);
		self::ensureFromWhere($table, $where);

		$sets = array();
		foreach($values as $col => $val) {
			self::ensureColumn($table,$col);
			$sets[] = "`$col`='$val'";
		}
		$setstr = implode(',', $sets);
		
		$tablename = self::$prefix . $table;
		$query = "UPDATE `$tablename` SET $setstr WHERE $wherestr";
		mysql_query($query);
		echo mysql_error();
	}
	
	static function drop($table=null) {
		if ($table!==null) {
			$tablename = self::$prefix . $table;
			mysql_query("DROP TABLE `$tablename`");
			echo mysql_error();
		} else {
			$rows = fetch_rows("SHOW TABLES");
			foreach($rows as $name) {
				$name = array_values($name);
				$name = $name[0];
				if (strpos($name, self::$prefix)===0)
					mysql_query("DROP TABLE `$name`");
			}
		}
	}
}

//DB::config( array("prefix" => "dbtest_") );
//DB::drop();
//DB::insert( "users", array( "name" => "mattias" ));
//DB::set("users", null, array( "age" => "12" ));
//echo json_encode(DB::get("users"));

if (isset($_REQUEST['_protodb'])) {
	DB::config( array( "prefix" => $dbprefix ));

	if ($_SERVER['REQUEST_METHOD']=="POST") {
		// drop, set, insert
		switch($_POST['cmd']) {
		case "insert":
			$table = $_POST['table'];
			$values = $_POST['values'];
			$obj = DB::insert($table, $values);
			header("Content-Type: application/json");
			echo json_encode($obj);
			break;
		case "set":
			$table = $_POST['table'];
			$where = isset($_POST["where"])?$_POST['where']:null;
			$values = $_POST['values'];
			DB::set($table, $where, $values);
			break;
		case "drop":
			$table = $_POST['table'];
			DB::drop($table);
			break;
		}
	} else {
		switch($_POST['cmd']) {
		case "getNear":
			// get(table, lon,lat,distance, where)
			$table = $_GET['table'];
			$lon = $_GET['lon'];
			$lat = $_GET['lat'];
			$distance = $_GET['distance'];
			$where = isset($_GET["where"])?$_GET['where']:null;
			header("Content-Type: application/json");
			echo json_encode(DB::getNear($table, $lon,$lat,$distance, $where));
			break;
		case "get":
		default:
			// get(table, where)
			$table = $_GET['table'];
			$where = isset($_GET["where"])?$_GET['where']:null;
			header("Content-Type: application/json");
			echo json_encode(DB::get($table, $where));
			break;
		}
	}
} else if (isset($_REQUEST['_js'])) {
	header("Content-Type: application/javascript");
?>
var protodb = (function() {
	function get(table, where, cb) {
		if (typeof where==="function") {	// where is the callback
			$.get("protodb.php", {"_protodb": true, cmd: "get", table: table}, where);
		} else {
			$.get("protodb.php", {"_protodb": true, cmd: "get", table: table, where: where}, cb);
		}
	}
	function insert(table, values,cb) {
		$.post("protodb.php", {"_protodb": true, cmd: "insert", table: table, values: values}, cb);
	}
	function set(table, where, values,cb) {
		$.post("protodb.php", {"_protodb": true, cmd: "set", table: table, where: where, values: values}, cb);
	}
	function drop(table,cb) {
		$.post("protodb.php", {"_protodb": true, cmd: "drop", table: table}, cb);
	}
	return {
		get : get,
		insert : insert,
		set : set,
		drop : drop,
	};
})();
<?
}

if (isset($_REQUEST['_test'])) {
	DB::config(array( "prefix" => $dbprefix ));

	$table = "rooms < room_updates(rooms.id=room_updates.room_id) < people(room_updates.person_id=people.id)"; //$_REQUEST['table'];
//	DB::parseTableStatement($table);
	print_r(DB::get($table));
}

?>