<?php
	//$mysql_con = mysql_connect('localhost', 'root', '') or die('Could not connect:'.mysql_error());
	//mysql_select_db('arrest_data') or die(mysql_error());

	$odbc_conn=odbc_connect('FDOC_October_2013','','');
	//print $odbc_conn=odbc_connect("Driver={Microsoft Access Driver (*.mdb)};Dbq=FDOC_October_2013.mdb", '', '');
	 $result = odbc_tables($odbc_conn);
	while (odbc_fetch_row($result)){
		if(odbc_result($result,"TABLE_TYPE")=="TABLE"){
			#$tableName=odbc_result($result,"TABLE_NAME");
			$tableName='offender_offenses_ccs';
			##-----Table Data-----
			$res = odbc_exec($odbc_conn,"SELECT * FROM $tableName");
			while( $dataArray = odbc_fetch_array($res) ) {
				print INSERT($tableName,$dataArray);die;
			}
		}
	}


	function INSERT($tableName, $array)
	{
	
	echo "hiii";die;
		$queryStr = '';
		$valueStr = '';
		foreach($array as $key=>$value){
			$queryStr.= mysql_real_escape_string($key).',';
			$valueStr.= "'".mysql_real_escape_string($value)."',";
		}
		$queryStr = preg_replace('/\,$/s','',$queryStr);
		$valueStr = preg_replace('/\,$/s','',$valueStr);

		$sql = "INSERT INTO $tableName($queryStr) values ($valueStr)";
		$res = mysql_query($sql);
		if (!$res){
			$return =  mysql_error();
		}else{
			$return =  mysql_insert_id();
		}
		return $return;
	}
	
	
	function development_database(){
		$db['hostname'] = 'localhost';
		$db['username'] = 'root';
		$db['password'] = '';
		$db['database'] = 'arrest_data';
		$db['dbdriver'] = 'mysql';
		$db['dbprefix'] = '';
		$db['pconnect'] = TRUE;
		$db['db_debug'] = TRUE;
		$db['cache_on'] = FALSE;
		$db['cachedir'] = '';
		$db['char_set'] = 'utf8';
		$db['dbcollat'] = 'utf8_general_ci';
		$db['swap_pre'] = '';
		$db['autoinit'] = TRUE;
		$db['stricton'] = FALSE;
		return $this->load->database($db, TRUE);
	}

?>