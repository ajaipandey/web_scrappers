<?php
class Crawler extends CI_Model {

	function __construct(){
		parent::__construct();
		set_time_limit(0);
		$this->load->model('psslib');
		$this->load->model('curl');
		$this->DCNumber ='';
		$this->type		='';
		$this->location	='';
		$this->description= '';
		}
	
	function initialize(){
		$this->http->cookie_file = '/tmp/cookie_arrest_data_'.make_slug($this->county.' '.$this->state,'_').'.txt';
		} 
	function array_exists($table,$condition) {
	if(isset($table) && $condition==''){
		$query = $this->db->get($table);
	}
	if($condition!=''){
			return $this->db->query("
			SELECT * FROM $table 
			$condition
			")->result_array();
	}
		return $query->result_array();
			
	}
	
	function check_status()
	{
		$query= $this->db->get('cron_status');
		return $query->result_array();
	}
	
	
	function insert_array($table,$arrest){
		
		$this->db->insert($table,$arrest);
		
	}
	
	
	function cron_exists($table,$condition)
	{
	if(isset($table) && $condition==''){
		$query = $this->db->get($table);
		}
		if($condition!=''){
		return $this->db->query("SELECT * 
		from $table $condition")->result_array();
		}
			
		return $query->result_array();
		
	}
	
	function insertodbc($odbcconn,$tableName, $array)
	{

		$queryStr = '';
		$valueStr = '';
		foreach($array as $key=>$value){
		
			$queryStr.= mysql_real_escape_string($key).',';
			$valueStr.= "'".mysql_real_escape_string($value)."',";
		
		}
		$queryStr = preg_replace('/\,$/s','',$queryStr);
		$valueStr = preg_replace('/\,$/s','',$valueStr);

	    $sql = "INSERT INTO $tableName($queryStr) values ($valueStr)";
		
		$this->db->insert($tableName,$array);
		
	}
	function odbc()
	{
	$odbc_conn=odbc_connect('FDOC_October_2013.mdb','neelesh','neelesh777#');
	return $odbc_conn;
	
	}
	
	function development_database(){
		$db['hostname'] = 'localhost';
		$db['username'] = 'neelesh';
		$db['password'] = 'neelesh777#';
		$db['database'] = 'neelesh_data';
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
}

?>