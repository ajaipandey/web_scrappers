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
		$this->db1        	= $this->crawler->odbc();
		$this->db 		  	= $this->crawler->development_database();
		$this->State		="FL";
		
	}
	function index()
	{   
		if(file_exists($this->proxyFile))
		{
			$this->proxyStatus=$this->psslib->checkProxy();
		}
		$this->psslib->updateLog("Going to check cron status..");
		$table='CRON_STATUS';
		$condition='WHERE state="FL" and date=CURDATE()';
		$res=$this->crawler->cron_exists($table,$condition);
		$count=count($res);
		if($count==0)
		{ 	
			echo "Crawler FL Running\n";
			$date = date('y-m-d');
			$values=array('status'=>'Running',
			'state'=>$this->State,'date'=>$date);
			$this->psslib->updateLog("Going to insert cron status..");
			$this->db->insert('CRON_STATUS',$values);
			$this->startCrawler();
			$odbc_conn = $this->crawler->odbc();
			$result = odbc_tables($odbc_conn);
			while (odbc_fetch_row($result))
			{
				if(odbc_result($result,"TABLE_TYPE")=="TABLE")
				{
					$tableName=odbc_result($result,"TABLE_NAME");
					$tableName1 ="INMATE_ACTIVE_ROOT";
					$tableName2 ="INMATE_RELEASE_ROOT";
					$tableName3 ="OFFENDER_ROOT";
					$state_code	="FL";
					$image_status=0;
					if($tableName1!='')
					{
						$res1 = odbc_exec($odbc_conn,"SELECT * FROM $tableName1");
						while($dataArray = odbc_fetch_array($res1)) {
							$state_array=array();
							$state_array['DCNumber']	= $dataArray['DCNumber'];
							$state_array['state_code']	= $state_code;
							$state_array['image_status']= $image_status;
							$state_cond="WHERE DCNumber='".$state_array['DCNumber']."'";
							$state_result=$this->crawler->array_exists($tableName1,$state_cond);
							if(count($state_result)==0)
							{
								$this->crawler->insertodbc($odbc_conn,'STATES',$state_array);
							}
						}
					}
					if($tableName2!='')
					{
						$res2 = odbc_exec($odbc_conn,"SELECT * FROM $tableName2");
						while($dataArray = odbc_fetch_array($res2)) {
							$state_array2=array();
							$state_array2['DCNumber']	= $dataArray['DCNumber'];
							$state_array2['state_code']	= $state_code;
							$state_array2['image_status']= $image_status;
							$state_cond2="WHERE DCNumber='".$state_array2['DCNumber']."'";
							$state_result2=$this->crawler->array_exists($tableName2,$state_cond2);
							if(count($state_result2)==0)
							{
								$this->crawler->insertodbc($odbc_conn,'STATES',$state_array2);
							}
						}
					}
					if($tableName3!='')
					{
						$res3 = odbc_exec($odbc_conn,"SELECT * FROM $tableName3");
						while($dataArray = odbc_fetch_array($res3)) {
							$state_array3=array();
							$state_array3['DCNumber']	= $dataArray['DCNumber'];
							$state_array3['state_code']	= $state_code;
							$state_array3['image_status']= $image_status;
							$state_cond3="WHERE DCNumber='".$state_array3['DCNumber']."'";
							$state_result3=$this->crawler->array_exists($tableName3,$state_cond3);
							if(count($state_result3)==0)
							{
								$this->crawler->insertodbc($odbc_conn,'STATES',$state_array3);
							}
						}
					}
					##-----Table Data----
					$res = odbc_exec($odbc_conn,"SELECT * FROM $tableName");
					while($dataArray = odbc_fetch_array($res)) {
						$this->crawler->insertodbc($odbc_conn,$tableName1,$dataArray);
					}
				}
			}
			$this->selectDcnumber();
				
		}
		else
		{
			echo "Already run today";
		}
		$this->update();
		$this->success();
	}
	function startCrawler()
	{
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
		$this->psslib->updateLog(" Going to select DCNumber from 'state' table ...");
		
		$conditions="WHERE `state_code`='FL' and image_status=0";
		$tablename="STATES";
		$result=$this->crawler->array_exists($tablename,$conditions);
		foreach($results as $result)
		{
			$DCNumber=$result['DCNumber'];
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
			//$state_condition="where DCNumber='".$inmate_imagesArray['DCNumber']."' and image_status=0 and state_code='FL'";
			//$state_sql="UPDATE $state_tablename SET image_status=1 $state_condition";
			$this->psslib->updateLog(" Going to update image_status in  $state_tablename table");
			$this->updatestatus();
			
			
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $inmate_imagesTable ");
		}
	}
	
	
	function updatestatus()
	{
		$status=array('image_status'=>1);
		$conditions = array('DCNumber' => $inmate_imagesArray['DCNumber'], 'image_status' =>0,'state_code'=>'FL');
		
		$this->db->where($conditions);
        $this->db->update('STATES', $status); 
	}
	function update()
	{
		$status=array('status'=>'Success');
		
		$date = date('y-m-d');
		$this->db->where('date',$date);
        $this->db->update('CRON_STATUS', $status); 
	}

	function success()
	{
		echo "Complete Successfully";
	}

}

?>