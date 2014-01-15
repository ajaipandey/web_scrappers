<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class fl extends CI_Controller {
	var $proxyFile='application/models/proxy.txt';
    function __construct(){
		parent::__construct();
		$this->load->model('crawler');
		$this->siteName	   	= "fl";
		$this->proxyStatus	= 0;
		$this->cookFile		= "application/logs/".$this->siteName."_cookies.txt";
		$this->errorLog   	= "application/logs/".$this->siteName."_errorLog.txt";
		$this->logFile     	= "application/logs/".$this->siteName."_logFile.txt";
		$this->currentLog  	= "application/logs/".$this->siteName."_currentLog.txt";
		$this->proxyLog    	= "application/logs/".$this->siteName."_proxyLog.txt";
		$this->headerFname	= 'head.txt';
		$this->downloadurl	= 'http://www.dc.state.fl.us/pub/obis_request.html';
		//$this->db1        	= $this->crawler->odbc();
		$this->db 		  	= $this->crawler->development_database();
		$this->State		="FL"; 
		
	}
	function index()
	{   
		$dateArray = array('DetainerDate'=>'DetainerDate','RemovalDate'=>'RemovalDate','ReceiptDate'=>'ReceiptDate','ReleaseDate'=>'ReleaseDate','OffenseDate'=>'OffenseDate','DateAdjudicated'=>'DateAdjudicated','BirthDate'=>'BirthDate','PrisonReleaseDate'=>'PrisonReleaseDate','SupervisionTerminationDate'=>'SupervisionTerminationDate');
		if(file_exists($this->proxyFile))
		{
			$this->proxyStatus=$this->psslib->checkProxy();
		}
			$this->startCrawler();
			echo "going to fetch data from mdb file\n";
			$db = new PDO('odbc:MDB-FL','root','');
			$tablesArray=array();
			$q = $db->prepare("SELECT * FROM MSysObjects WHERE Type=1 AND Flags=0");
			$q->execute();
			$table_fields = $q->fetchALL(PDO::FETCH_ASSOC);
			$q->closeCursor();			
			foreach($table_fields as $Key)
			{
				$tableName=$Key['Name'];
				$stmt = $db->prepare("select * from $tableName ");
				$stmt->execute();
				while($result = $stmt->fetch(PDO::FETCH_ASSOC))
				{
					foreach($result as $key=>$value){
						if(isset($dateArray[$key])) {
							if (array_key_exists($key, $dateArray) and $result[$key]!=null) {
								$result[$key]= date('Y-m-d', strtotime($result[$key]));
							}
						}
					}
					if($tableName!='CONTENTS'){
						$table_cond="WHERE DCNumber='".$result['DCNumber']."'";
						$table_result=$this->crawler->array_exists($tableName,$table_cond);
						if(count($table_result)==0)
						{
							$this->psslib->updateLog("Going to insert record from mdb file into database..");
							$this->crawler->insertodbc($db,$tableName,$result);
						}
					}else{
						$this->crawler->insertodbc($db,$tableName,$result);
					}
				}
				$stmt->closeCursor();
				if($tableName=="INMATE_ACTIVE_ROOT")
				{
					$stmt1 = $db->prepare("select * from $tableName ");
					$stmt1->execute();
					$state_code	="FL";
					$image_status=0;
					$statetableName1="STATES";
					while($result1 = $stmt1->fetch(PDO::FETCH_ASSOC))
					{
						$state_array1=array();
						$state_array1['DCNumber']	= $result1['DCNumber'];
						$state_array1['state_code']	= $state_code;
						$state_array1['image_status']= $image_status;
						$state_cond1="WHERE DCNumber='".$state_array1['DCNumber']."'";
						$state_result1=$this->crawler->array_exists($statetableName1,$state_cond1);
						if(count($state_result1)==0)
						{
							$this->psslib->updateLog("Going to insert record from mdb $tableName into $statetableName1 ..");
							$this->crawler->insertodbc($db,'STATES',$state_array1);
						}
						
					}
					$stmt1->closeCursor();
				}
				if($tableName=="INMATE_RELEASE_ROOT")
				{
					$stmt2 = $db->prepare("select * from $tableName ");
					$stmt2->execute();
					$state_code	="FL";
					$image_status=0;
					$statetableName2="STATES";
					while($result2 = $stmt2->fetch(PDO::FETCH_ASSOC))
					{
						$state_array2=array();
						$state_array2['DCNumber']	= $result2['DCNumber'];
						$state_array2['state_code']	= $state_code;
						$state_array2['image_status']= $image_status;
						$state_cond2="WHERE DCNumber='".$state_array2['DCNumber']."'";
						$state_result2=$this->crawler->array_exists($statetableName2,$state_cond2);
						if(count($state_result2)==0)
						{
							$this->psslib->updateLog("Going to insert record from mdb $tableName into $statetableName2 ..");
							$this->crawler->insertodbc($db,'STATES',$state_array2);
						}
					}
					$stmt2->closeCursor();
				}
				if($tableName=="OFFENDER_ROOT")
				{
					$stmt3 = $db->prepare("select * from $tableName ");
					$stmt3->execute();
					$state_code	="FL";
					$image_status=0;
					$statetableName3="STATES";
					while($result3 = $stmt3->fetch(PDO::FETCH_ASSOC))
					{
						$state_array3=array();
						$state_array3['DCNumber']	= $result3['DCNumber'];
						$state_array3['state_code']	= $state_code;
						$state_array3['image_status']= $image_status;
						$state_cond3="WHERE DCNumber='".$state_array3['DCNumber']."'";
						$state_result3=$this->crawler->array_exists($statetableName3,$state_cond3);
						if(count($state_result3)==0)
						{
							$this->psslib->updateLog("Going to insert record from mdb $tableName into $statetableName3 ..");
							$this->crawler->insertodbc($db,'STATES',$state_array3);
						}
					}
					$stmt3->closeCursor();
				}
			}
			$this->selectDcnumber();
			$this->success();
	}
	
	function startCrawler()
	{    
		echo "run fl crawler\n";
		$this->psslib->updateLog("Going to download data from given url..");
		#$this->myGET('ip.html','http://x5.net/ip.html',"S");
		$this->myGET('downloadPage.html',$this->downloadurl,"S");
		$downloadPage=file_get_contents('downloadPage.html');
		if(preg_match('/>\s*Download\s*the\s*\w+\s+\d+\s*[,\s*\d+\s]*Information/is',$downloadPage,$match)) {
			$downloadPage=$this->psslib->before($match[0],$downloadPage);
			if(preg_match('/.+href\W+(.*?)[\'\"]/is',$downloadPage,$match)){
				$mdbfileurl1=$match[1];
				$this->clean_compalete_url($mdbfileurl1);
				$this->myGET('mdbPage1.html',$mdbfileurl1);
				$mdbPage1=file_get_contents('mdbPage1.html');
				if(preg_match('/\W+downloadUrl\W+(http.*?)[\'\"]/is',$mdbPage1,$match))
				{
					$mdbfileurl2=$match[1];
					$this->clean_compalete_url($mdbfileurl2);
					$this->myGET('mdbPage2.html',$mdbfileurl2);
					$mdbPage2=file_get_contents('mdbPage2.html');
					if(preg_match('/>\s*Download\s*anyway\s*</is',$mdbPage2,$match)){
						$mdbPage2=$this->psslib->before($match[0],$mdbPage2);
						if(preg_match('/.+href\W+(.*?)[\'\"]/is',$mdbPage2,$match)){
							$mdbfileurl3=$match[1];
							$this->clean_compalete_url($mdbfileurl3);
							if(!preg_match('/^http/is',$mdbfileurl3)){
								$mdbfileurl3="http://docs.google.com/".$mdbfileurl3;
							}
							$this->psslib->updateLog("Going to save data in mdb file..");
							$this->myGET('Florida.mdb',$mdbfileurl3);
							echo "mdb file has been saved\n";
						}
					}
				}
			}
		}
	}

	function myGET($resultFname,$url,$cookieSetOrRead=null)
	{
		if($this->proxyStatus==1){
			$this->psslib->configureProxy();
	        $this->proxyIP		= $this->psslib->proxyIP;
			$this->proxyPort	= $this->psslib->proxyPort;
			$this->proxyUserPwd	= $this->psslib->proxyUserPwd;
			$curlStr = "curl -x $this->proxyIP:$this->proxyPort -U $this->proxyUserPwd --compressed -A 'Mozilla/5.0 (Windows NT 6.1; rv:25.0) Gecko/20100101 Firefox/25.0' -L ";
		}else{
			$curlStr = "curl --compressed -A 'Mozilla/5.0 (Windows NT 6.1; rv:25.0) Gecko/20100101 Firefox/25.0' -L ";
		}
		if($this->headerFname){
			if($cookieSetOrRead == "S"){
				$curlStr .= "-c '$this->cookFile' -D '$this->headerFname' ";
			}else{
				$curlStr .= "-b '$this->cookFile' -c $this->cookFile -D '$this->headerFname' ";
			}
		}

		 $curlStr .= "'".$url."' -o '$resultFname'";
		`$curlStr`;
		return 1;
	}

	function clean_compalete_url(&$url)
	{
		$url = preg_replace('/<.*?>/is', '', $url);
		$url = preg_replace('/&amp;/is', '&', $url);
		$url = str_replace("\\", '', $url);
		$url = trim($url);
	}
	function selectDcnumber()
	{
		$this->psslib->updateLog(" Going to select DCNumber from 'STATES' table ...");
		
		$conditions="WHERE `state_code`='FL' and image_status=0";
		$tablename="STATES";
		$result=$this->crawler->array_exists($tablename,$conditions);
		foreach($result as $results)
		{
			$DCNumber=$results['DCNumber'];
			$this->getDCNumber($DCNumber);
		}
	}
	function getDCNumber($DCNumber)
	{
		$this->psslib->updateLog("Going to add DCNUmber =($DCNumber) in Url...");
		$first=substr($DCNumber,0,1);
		$finalimageUrl="http://www.dc.state.fl.us/InmatePhotos/$first/"."$DCNumber"."."."jpg";
		$Imagesource=$this->psslib->getimagesource($finalimageUrl);
		$inmate_imagesArray=array();
		$inmate_imagesArray['DCNumber'] = trim($DCNumber);
		$inmate_imagesArray['haveImage']= $Imagesource;
		$inmate_imagesArray['updated']	= date('y-m-d');
		$inmate_imagesTable = "INMATE_IMAGES";
		$inmate_imagesCondition="where DCNumber='".$inmate_imagesArray['DCNumber']."'";
		$state_result=$this->crawler->array_exists($inmate_imagesTable,$inmate_imagesCondition);
		if(count($state_result)==0)
		{
			$this->psslib->updateLog(" Going to insert records in $inmate_imagesTable table");
			$this->crawler->insert_array($inmate_imagesTable,$inmate_imagesArray);
			$state_tablename='STATES';
			$this->psslib->updateLog(" Going to update image_status in  $state_tablename table");
			$this->updatestatus($inmate_imagesArray);
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $inmate_imagesTable ");
		}
	}
	function updatestatus($inmate_imagesArray)
	{
		$this->psslib->updateLog("Going to Update 'STATE' table ..");
		$status=array('image_status'=>1);
		$conditions = array('DCNumber' => $inmate_imagesArray['DCNumber'], 'image_status' =>0,'state_code'=>'FL');
		$this->db->where($conditions);
        $this->db->update('STATES', $status); 
	}

	function success()
	{
		echo "Complete Successfully";
	}

}

?>
