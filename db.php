<?
/**
Some database help functions. Also establishes database connection to get started quickly.
db_url, db_user, db_passwd, and database are set in config.
**/


		require_once('config.php');

        mysql_connect($db_url, $db_user, $db_passwd);
        @mysql_select_db($database) or die("could not select database");
        
        function fetch_rows($query)
        {
        	$r = mysql_query($query);
        	echo mysql_error();
			if (!$r) return array();
        	$rows = array();
        	while($row = mysql_fetch_assoc($r))
        	{
        		$rows[] = $row;
        	}
        	mysql_free_result($r);
        	return $rows;
        }

        function fetch_row($query)
        {
        	$r = mysql_query($query);
        	echo mysql_error();
        	return $r?mysql_fetch_assoc($r):null;
        }

        function fetch_value($query)
        {
        	$r = mysql_query($query);
        	echo mysql_error();
        	$arr = mysql_fetch_array($r);
        	return $arr[0];
        } 

?>
