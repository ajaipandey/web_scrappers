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
		$query= $this->db->get('CRON_STATUS');
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
		$tableArray['INMATE_RELEASE_ROOT'] 			= array('releasedateflag_descr'=>'ReleaseDateFlag');
		$tableArray['INMATE_RELEASE_OFFENSES_CPS'] = array('adjudication_descr'=>'adjudicationcharge_descr');
		$tableArray['INMATE_RELEASE_OFFENSES_prpr']= array('adjudication_descr'=>'adjudicationcharge_descr');
		$tableArray['INMATE_RELEASE_DETAINERS'] 		= array('detainertype_descr'=>'DetainerType','cancelwithdrawn_descr'=>'CancelWithdrawn');
		$tableArray['OFFENDER_OFFENSES_CCS'] 			= array('adjudication_descr'=>'adjudicationcharge_descr');
		$tableArray['INMATE_ACTIVE_ROOT'] 				= array('releasedateflag_descr'=>'ReleaseDateFlag');
		$tableArray['INMATE_ACTIVE_OFFENSES_CPS'] 	= array('adjudication_descr'=>'adjudicationcharge_descr');
		$tableArray['INMATE_ACTIVE_OFFENSES_prpr'] = array('adjudication_descr'=>'adjudicationcharge_descr');
		$tableArray['INMATE_ACTIVE_DETAINERS'] 		= array('detainertype_descr'=>'DetainerType','cancelwithdrawn_descr'=>'CancelWithdrawn');
		$tablenName['INMATE_RELEASE_SCARSMARKS']		="INMATE_RELEASE_SCARS";
		$tablenName['INMATE_ACTIVE_SCARSMARKS']		="INMATE_ACTIVE_SCARS";
		$array1=array();
		if(isset($tablenName[$tableName])) {
				$tableName=$tablenName[$tableName];
		}
		foreach($array as $key=>$value){
			if(isset($tableArray[$tableName])) {
				if (array_key_exists($key, $tableArray[$tableName])) {
					$key=$tableArray[$tableName][$key];
					continue;
				}
			}
			$array1[$key]=$value;
		}
		$this->db->insert($tableName,$array1);
	
	}

	function odbc()
	{
		$odbc_conn=odbc_connect('MDB-FL','root','');
		return $odbc_conn;
	
	}
	
	function development_database(){
		$db['hostname'] = 'localhost';
		$db['username'] = 'root';
		$db['password'] = '';
		$db['database'] = 'doc_data';
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
