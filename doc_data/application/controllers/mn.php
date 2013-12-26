<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class mn extends CI_Controller {

	var $proxyFile='application/models/proxy.txt';
	function __construct(){
		parent::__construct();
		$this->load->model('crawler');
		$this->siteName	    = "montana";
		$this->cookFile	    = "application/logs/".$this->siteName."_cookies.txt";
		$this->errorLog     = "application/logs/".$this->siteName."_errorLog.txt";
		$this->resume       = "application/logs/".$this->siteName."_resume.txt";
		$this->logFile      = "application/logs/".$this->siteName."_logFile.txt";
		$this->currentLog   = "application/logs/".$this->siteName."_currentLog.txt";
		$this->proxyLog     = "application/logs/".$this->siteName."_proxyLog.txt";
		$this->htmlDoc      = new DomDocument();
		$this->url          = "https://app.mt.gov/conweb/";
		$this->website      = "https://app.mt.gov";
		$this->State		= "MN";
		$this->db 			= $this->crawler->development_database();
		}
	function index()
	{
		if(file_exists($this->proxyFile))
		{
			$this->proxyStatus=$this->psslib->checkProxy();
		}
		$table='cron_status';
		$condition='WHERE status="Running" and state="MN"';
		$res=$this->crawler->cron_exists($table,$condition);
		$status=$res[0]['status'];
		if($status=="Running")
		{
			echo "Already Running.";
		}
		else
		{
			$table='cron_status';
			$condition='WHERE state="MN" and date=CURDATE()';
			$res=$this->crawler->cron_exists($table,$condition);
			$count=count($res);
			if($count==0)
			{
				$date = date('y-m-d');
				$values=array('status'=>'Running',
				'state'=>$this->State,'date'=>$date);
				$this->db->insert('cron_status',$values);
				$this->runCrawler();
			}
			else
			{
				echo "Already run today";
			}
			$this->update();
			}
		}
		
	function runCrawler()
	{
		$this->unlinkfiles();
		$this->searchPage();
	}
	function searchPage()
	{
		$url=$this->url;
		$offenceArray = array();
		$resultFile=$this->psslib->getandcheckPage($url,"");
		####-----Parse Offence Array--------####
		@$this->htmlDoc->loadHTML($resultFile);
		$this->xpath = new DOMXPath($this->htmlDoc);
		$xpathVal='//select[@id="ActiveOffenderType"]/option/@value';
		$nodelist = $this->xpath->query($xpathVal);
		foreach($nodelist as $n) {
			$tempData=trim($n->nodeValue);
			if($tempData!=null){
				$offenceArray[]=$tempData;
			}
		}
		/*$key2="aa";
		$key1='';
		$checkrecords="500";
		$value='';
		$resultFile2=$this->gotoFiltration($checkrecords,$value,$key1,$key2);
		$this->parseMainPage($resultFile2,$key2);*/
		if(preg_match('/<form(.*?)<\/form>/is',$resultFile,$match))
		{
			$checkrecords=1;
			$keyArray1 = $this->statusKeyExistence("/(.*?)::/is");#check First Existence
			foreach($offenceArray as $value)
			{
				if(!in_array($value,$keyArray1)){
					$keyArray2 = $this->statusKeyExistence("/$value::(.*?)::/is");#check First Existence
					for($i=97;$i<123;$i++){##LOOP for Last name From a-z
						$key11=chr($i);
						for($j=97;$j<123;$j++) {##LOOP for Last name From a-z
							$key1=$key11.chr($j);
							if(!in_array($key1,$keyArray2)){
								$resultFile2=$this->gotoFiltration($checkrecords,$value,$key1);##Search By Fisrt Name and Lastname Iteration
								$mykey="$value::$key1";
								if($checkrecords==1){
									$keyArray3=$this->statusKeyExistence("/$value::$key1::(.*?)::/is");#check First Existence
									for($k=97;$k<123;$k++) {##LOOP for Last name From a-z
										$key21=chr($k);
										if(!in_array($key21,$keyArray3)){
											$resultFile2=$this->gotoFiltration($checkrecords,$value,$key1,$key21);##Search By Fisrt Name and Lastname Iteration
											$mykey="$value::$key1::$key21";
											if($checkrecords==0){
												for($m=97;$m<123;$m++) {##LOOP for Last name From a-z
													$key2=$key21.chr($m);
													$resultFile2=$this->gotoFiltration($checkrecords,$value,$key1,$key2);##Search By Fisrt Name and Lastname Iteration
													$mykey="$value::$key1::$key21::$key2";
													$this->parseMainPage($resultFile2,$mykey);
												}
											}else{
												$this->parseMainPage($resultFile2,$mykey);
											}
										}
									}
								}else{
									$this->parseMainPage($resultFile2,$mykey);
								}
							}
						}
					}
				}
			}
		}else{
			print "-----Search Form Not Found-----!";
		}
	}
	function gotoFiltration(&$checkrecords,$offenceType=null,$fname=null,$lname=null)
	{
		$url2="https://app.mt.gov/conweb/?OffenderNumber=&LastName=$lname&FirstName=$fname&YearOfBirth=&Sex=&Race=&ActiveOffenderType=$offenceType";
		$url2 = preg_replace('/\s+/is','+',$url2);
		$resultFile2=$this->psslib->getandcheckPage($url2,'','');
	
		if(preg_match('/Search\s*has\s*too\s*many\s*results\.\s*Please\s*narrow\s*down\s*your\s*search\./is',$resultFile2,$match)){
			$checkrecords=1;
		}
		return $resultFile2;
	}
	function parseMainPage($resultFile,$mykey)
	{
		$this->resumeLogs("$mykey");
		if(preg_match('/No\s+offenders\s+found\s+matching\s+that\s+search\./is',$resultFile)){
			$this->psslib->appendFile($this->psslib->errorLog," ---- No Records found for $mykey!--------");
		}elseif(preg_match('/class\W+table_center\W+/is',$resultFile,$match))
		{
			$this->psslib->updateLog(" Going to main page for getting the nameurl for $mykey...");
			@$this->htmlDoc->loadHTML($resultFile);
			$this->xpath = new DOMXPath($this->htmlDoc);
			$xpathVal='//div[contains(@class,"table_center")]//a/@href';
			$nodelist = $this->xpath->query($xpathVal);
			$errorFlag=0;
			foreach($nodelist as $n)
			{
				$errorFlag=1;
				$nameurl=trim($n->nodeValue);
				if(!preg_match('/^http/is',$nameurl))
				{
					$nameurl=$this->website.$nameurl;
				}
				$finalresult=$this->psslib->getandcheckPage($nameurl,'');
				$this->getFinalPage($finalresult,$mykey);
			}
		}
		else
		{
			if(preg_match('/>\s*Offender\s*Information\s*</is',$resultFile,$match))
			{
				$this->psslib->updateLog(" Going to main page without nameurl for $mykey...");
				$this->getFinalPage($resultFile,$mykey);
			}
		}
	}

	function getFinalPage($finalresult,$mykey)
	{
		$this->psslib->updateLog("Going to final page for get the final data for $mykey...");
		$finalresult=preg_replace('/&nbsp;/is','',$finalresult);
		$finalresult=preg_replace('/&amp;/is','&',$finalresult);
		$finalArray=array();
		if(preg_match('/<div\s*class\W+info_box\s*\"\s*>(.*?)<\/div>/is',$finalresult,$match))
		{
			$tempResult=$match[1];
			$DCNumber='';
			$Name='';
			$currentStatus='';
			$statusLastUpdate='';
			$gender='';
			$prison='';
			if(preg_match('/>\s*DOC\s*ID.*?>(.*?)</is',$tempResult,$match))
			{
				$DCNumber=trim($match[1]);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," DocId not found ");
			}
			$FirstName='';
			$MiddleName='';
			$LastName='';
			$NameSuffix='';
			if(preg_match('/>\s*NAME.*?>(.*?)<\/span>/is',$tempResult,$match))
			{
				$Name=trim($match[1]);
				$Name=preg_replace('/<.*?>/is','',$Name);
				$Name=preg_replace('/^\s+/is','',$Name);
				$Name=preg_replace('/\s+$/is','',$Name);
				$Name=preg_replace('/\W+/is',' ',$Name);
				$Name=explode(' ',$Name);
				if(count($Name)==1)
				{
					$FirstName	= $Name[0];
				}
				if(count($Name)==2)
				{
					$FirstName	= $Name[0];
					$LastName	= $Name[1];
				}
				if(count($Name)==3)
				{
					$FirstName = $Name[0];
					$FirstName=$this->psslib->htmlEntityToHtml($FirstName);
					$MiddleName= $Name[1];
					$MiddleName=$this->psslib->htmlEntityToHtml($MiddleName);
					$LastName  = $Name[2];
					$LastName=$this->psslib->htmlEntityToHtml($LastName);
					if(strlen($LastName)<=2)
					{
						$NameSuffix=$LastName;
						$LastName = $MiddleName;
						$MiddleName="";
					}
					if(strlen($FirstName)<=2)
					{
						$NameSuffix = $Name[0];
						$FirstName  = $Name[1];
						$LastName	= $Name[2];
						$MiddleName= '';
					}
				}
				if(count($Name)==4)
				{
					$FirstName	= $Name[0];
					$MiddleName = $Name[1];
					$LastName	= $Name[2];
					$NameSuffix = $Name[3];
					if(strlen($LastName)<=2)
					{
						$FirstName	= $Name[0];
						$MiddleName	= $Name[1];
						$NameSuffix	= $LastName;
						$LastName	= $Name[3];
					}
				}
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," Name not found ");
			}
			if(preg_match('/>\s*CURRENT\s*STATUS.*?>(.*?)</is',$tempResult,$match))
			{
				$currentStatus=$match[1];
				$currentStatus=preg_replace('/<.*?>/is','',$currentStatus);
				$currentStatus=preg_replace('/\s+$/is','',$currentStatus);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," currentStatus not found ");
			}
			if(preg_match('/>\s*STATUS\s*LAST\s*UPDATED.*?>(.*?)</is',$tempResult,$match))
			{
				$statusLastUpdate=$match[1];
				$statusLastUpdate=preg_replace('/<.*?>/is','',$statusLastUpdate);
				$statusLastUpdate=preg_replace('/\s+$/is','',$statusLastUpdate);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," statusLastUpdate not found ");
			}
			if(preg_match('/>\s*GENDER.*?>(.*?)</is',$tempResult,$match))
			{
				$gender=$match[1];
				$gender=preg_replace('/<.*?>/is','',$gender);
				$gender=preg_replace('/^\s+/is','',$gender);
				$gender=preg_replace('/\s+$/is','',$gender);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," gender not found ");
			}
			if(preg_match('/>\s*PRISON.*?>(.*?)<\/p>/is',$tempResult,$match))
			{
				$prison=$match[1];
				$prison=preg_replace('/<.*?>/is','',$prison);
				$prison=preg_replace('/\s+/is',' ',$prison);
				$prison=preg_replace('/\s+$/is','',$prison);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," prison not found ");
			}
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," class 'info_box' of div not found on website....");
		}
		$finalArray['DCNumber']		= trim($DCNumber);
		$finalArray['FirstName']	= trim($FirstName);
		$finalArray['MiddleName']	= trim($MiddleName);
		$finalArray['LastName']		= trim($LastName);
		$finalArray['NameSuffix']	= trim($NameSuffix);
		$finalArray['currentStatus']= trim($currentStatus);
		$finalArray['statusLastUpdate']= trim($statusLastUpdate);
		$finalArray['Sex']		= trim($gender);
		$finalArray['Facility']	= trim($prison);
		$ImageUrl='';
		if(preg_match('/class\W+img_cell\s*\"\s*>\s*<img.*?src\s*=\s*[\"\'](.*?)[\"\']/is',$finalresult,$match))
		{
			$ImageUrl=$match[1];
			if(!preg_match('/^http/is',$ImageUrl))
			{
				$ImageUrl=$this->website.$ImageUrl;

			}
			$finalArray['ImageUrl']=trim($ImageUrl);
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," ImageUrl not found on website ");
		}
		if(preg_match('/>\s*PHYSICAL\s*AND\s*DEMOGRAPHIC\s*CHARACTERISTICS.*?>(.*?)<\/div>\s*<\/div>/is',$finalresult,$match))
		{
			$tempPhysicalfile=$match[1];
			while(preg_match('/<span\s*class\W+blue_font\s*\"\s*>/is',$tempPhysicalfile,$match))
			{
				$key1=null;
				$value1=null;
				$tempPhysicalfile=$this->psslib->after($match[0],$tempPhysicalfile);
				if(preg_match('/(.*?)<\/span>/is',$tempPhysicalfile,$match))
				{
					$key1=trim($match[1]);
					$key1=preg_replace('/\:/is','',$key1);
					$key1=preg_replace('/<.*?>/is','',$key1);
					$key1=preg_replace('/\s+/is',' ',$key1);
				}
				if(preg_match('/<\/span>(.*?)</is',$tempPhysicalfile,$match))
				{
					$value1=trim($match[1]);
					$value1=preg_replace('/\:/is','',$value1);
					$value1=preg_replace('/<.*?>/is','',$value1);
					$value1=preg_replace('/\s+/is',' ',$value1);
				}
				$finalArray[$key1]=$value1;
			}
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog,"PHYSICAL AND DEMOGRAPHIC CHARACTERISTICS  not found  ....");
		}
		$aka='';
		if(preg_match('/class\W+aka\s*\"\s*>(.*?)<\/p>/is',$finalresult,$match))
		{
			$aka=trim($match[1]);
			$aka=preg_replace('/<.*?>/is','',$aka);
			$aka=preg_replace('/\s+/is',' ',$aka);
			$finalArray['aka']=$aka;
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog,"aka not found ");
		}
		########################## for tatoo information ##########################

		$tattooresult=$finalresult;
		$errorFlag=0;
		$tattooArray=array();
		while(preg_match('/<span\s*class\W+blue_font\s*smallshow\"\s*/is',$tattooresult,$match))
		{
			$errorFlag=1;
			$value=null;
			$type1=null;
			$tattooresult=$this->psslib->after($match[0],$tattooresult);
			if(preg_match('/>\s*TYPE\:\s*<\/span>(.*?)<\/p>/is',$tattooresult,$match))
			{
				$type1=trim($match[1]);
				$type1=preg_replace('/\s+/is',' ',$type1);
				$type1=preg_replace('/\s+$/is','',$type1);
			}
			if(preg_match('/\s*DESCRIPTION\:\s*<\/span>(.*?)<\/p>/is',$tattooresult,$match))
			{

				$value=trim($match[1]);
				$value=preg_replace('/\s+/is',' ',$value);
				$value=preg_replace('/\s+$/is','',$value);
				$value=preg_replace('/&quot;/is','',$value);
			}
			if($type1!=null and $value!=""){
				$tattooArray[$type1]=$value;
			}
		}
		if($errorFlag==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"class 'blue_font smallshow' not found");
		}

		################### for inmate_active_scars table ########################
		foreach($tattooArray as $key=>$value)
		{
			$key=preg_replace('/\W+/is',' ',$key);
			$key = explode(' ',$key);
			if(count($key)==1)
			{
				$tatootype=$key[0];
			}
			if(count($key)==2)
			{
				$tatootype = $key[0];
				$location = $key[1];
			}
			if(count($key)==3)
			{
				$tatootype = $key[0];
				$location = "$key[1] $key[2]";
			}
			if(count($key)==4)
			{
				$tatootype = $key[0];
				$location = "$key[1] $key[2] $key[3]";
			}
			if(count($key)==5)
			{
				$tatootype = $key[0];
				$location = "$key[1] $key[2] $key[3] $key[4]";
			}
			$tattoo_Array =array();
			$tattoo_Array['type']		= $tatootype;
			$tattoo_Array['Description']= $value;
			$tattoo_Array['location']	= $location;
			$tattoo_Array['DCNumber']	= $DCNumber;
			$tattoo_Tablename="inmate_active_scars";
			if($tattoo_Array['DCNumber']!='')
			{
				$tattoo_condition = "WHERE `DCNumber`='".$tattoo_Array['DCNumber']."'and Type='".$tattoo_Array['type']."' and Location='".$tattoo_Array['location']."' and Description='".$tattoo_Array['Description']."'";
				$tattoo_result=$this->crawler->array_exists($tattoo_Tablename,$tattoo_condition);
				if(count($tattoo_result)==0)
				{
					$this->psslib->updateLog(" Going to insert records in $tattoo_Tablename table ");
					$this->crawler->insert_array($tattoo_Tablename,$tattoo_Array);
				}
				else
				{
					$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $tattoo_Tablename table ");
				}
			}

		}
		
		########################### offense information ############################
		$offenseArray=array();
		$i=0;
		$errorFlag1=0;
		while(preg_match('/class\W+law_info\">(.*?)class\W+wb_Text/is',$finalresult,$match))
		{
			$tarray=array();
			$errorFlag1=1;
			$temp=$match[1];
			$finalresult=$this->psslib->after($match[0],$finalresult);
			while(preg_match('/<span\s*class\W+blue_font\">/is',$temp,$match))
			{
				$temp=$this->psslib->after($match[0],$temp);
				if(preg_match('/(.*?)<\/span>(.*?)</is',$temp,$match))
				{
					$value	= trim($match[2]);
					$value	= preg_replace('/\s+$/is','',$value);
					$key	= trim($match[1]);
					$key	= preg_replace('/\:/is','',$key);
					$key	= preg_replace('/\s+$/is','',$key);
				}
				$tarray[$key]=$value;

			}
			$offenseArray[$i]=$tarray;
			$i++;
		}

		if($errorFlag1==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"class 'law_info' not found ");
		}

		for($i=0;$i<count($offenseArray);$i++)
		{
			$County='';
			$CaseNumber = "";
			$OffenseDate="";
			$AdjudicationCharge='';
			$Adjudication='';
			$DateAdjudicated='';
			$newArr = $offenseArray[$i];
			if(array_key_exists("DOCKET",$newArr))
			{
				$CaseNumber = $newArr['DOCKET'];
			}
			if(array_key_exists("OFFENSE DATE",$newArr))
			{
				$OffenseDate = $newArr['OFFENSE DATE'];
				$OffenseDate = date('Y-m-d', strtotime($OffenseDate));
			}
			if(array_key_exists("COUNTY",$newArr))
			{
				$County = $newArr['COUNTY'];
			}
			if(array_key_exists("OFFENSE",$newArr))
			{
				$AdjudicationCharge = $newArr['OFFENSE'];
			}
			if(array_key_exists("SENTENCE TYPE",$newArr))
			{
				$Adjudication = $newArr['SENTENCE TYPE'];
			}
			if(array_key_exists("SENTENCE PRONOUNCED",$newArr))
			{
				$DateAdjudicated = $newArr['SENTENCE PRONOUNCED'];
				$DateAdjudicated = date('Y-m-d', strtotime($DateAdjudicated));
			}
			###################  for inmate_active_offenses_cps table ###############
			$IA_offenses_cpsArray = array();
			$IA_offenses_cpsArray['DCNumber']			= trim($DCNumber);
			$IA_offenses_cpsArray['OffenseDate']		= trim($OffenseDate);
			$IA_offenses_cpsArray['AdjudicationCharge']	= trim($AdjudicationCharge);
			$IA_offenses_cpsArray['Adjudication']		= trim($Adjudication);
			$IA_offenses_cpsArray['DateAdjudicated']	= trim($DateAdjudicated);
			$IA_offenses_cpsArray['County']				= trim($County);
			$IA_offenses_cpsArray['CaseNumber']			= trim($CaseNumber);
			$IA_offenses_cpsArray['prisonterm']			= trim($prison);
			$IA_offenses_cpstablename= "inmate_active_offenses_cps";
			if($IA_offenses_cpsArray['DCNumber']!='')
			{
				$offenses_cps_condition	 = "where DCNumber='".$IA_offenses_cpsArray['DCNumber']."' and CaseNumber='".$IA_offenses_cpsArray['CaseNumber']."' and AdjudicationCharge='".$IA_offenses_cpsArray['AdjudicationCharge']."' and OffenseDate like '%".$IA_offenses_cpsArray['OffenseDate']."%'";
				$offenses_cps_result=$this->crawler->array_exists($IA_offenses_cpstablename,$offenses_cps_condition);
				if(count($offenses_cps_result)==0)
				{
					$this->psslib->updateLog(" Going to insert records in $IA_offenses_cpstablename table");
					$this->crawler->insert_array($IA_offenses_cpstablename,$IA_offenses_cpsArray);
				}
				else
				{
					$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_offenses_cpstablename table ");
				}
			}
		}

		################### for inmate_active_root table ########################
		$IActive_Rootarray = array();
		$Height = trim($finalArray['HEIGHT']);
		$Height = preg_replace('/FT/is','.',$Height);
		$Height = preg_replace('/IN/is','',$Height);
		$Height = preg_replace('/\s+$/is','',$Height);
		$Weight	= trim($finalArray['WEIGHT']);
		$Weight = preg_replace('/lbs/is','',$Weight);
		$Weight = preg_replace('/\s+$/is','',$Weight);
		$IActive_Rootarray['Height']		= $Height;
		$IActive_Rootarray['Weight']		= $Weight;
		$IActive_Rootarray['DCNumber']		= trim($DCNumber);
		$IActive_Rootarray['FirstName']		= trim($FirstName);
		$IActive_Rootarray['MiddleName']	= trim($MiddleName);
		$IActive_Rootarray['LastName']		= trim($LastName);
		$IActive_Rootarray['NameSuffix']	= trim($NameSuffix);
		$IActive_Rootarray['Sex']			= trim($gender);
		$IActive_Rootarray['Facility']		= trim($prison);
		$IActive_Rootarray['Race']			= trim($finalArray['RACE']);
		$IActive_Rootarray['YearOfBirth']	= trim($finalArray['YEAR OF BIRTH']);
		$IActive_Rootarray['HairColor']		= trim($finalArray['HAIR COLOR']);
		$IActive_Rootarray['EyeColor']		= trim($finalArray['EYE COLOR']);
		$IActive_Rootarray['Build']			= trim($finalArray['BUILD']);
		$IActive_Rootarray['LRHanded']		= trim($finalArray['L/R HANDED']);
		$IActive_Rootarray['Skintone']		= trim($finalArray['SKIN TONE']);
		$IActive_Rootarray['Birthplace']	= trim($finalArray['BIRTH PLACE']);
		$IActive_Rootarray['Citizenship']	= trim($finalArray['CITIZENSHIP']);
		$IActive_Rootarray['Mtresident']	= trim($finalArray['MT RESIDENT']);
		$IA_rootTablename="inmate_active_root";
		if($IActive_Rootarray['DCNumber']!='')
		{
			$active_root_condition="where `DCNumber`='".$IActive_Rootarray['DCNumber']."' and FirstName='".$IActive_Rootarray['FirstName']."' and LastName='".$IActive_Rootarray['LastName']."'";
			$active_root_result=$this->crawler->array_exists($IA_rootTablename,$active_root_condition);
			if(count($active_root_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $IA_rootTablename table");
				$this->crawler->insert_array($IA_rootTablename,$IActive_Rootarray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_rootTablename table ");
			}
		}

		########################for IAactive_detainers table #############################
		/*
		//Skip this insertion 
		$IAactive_detainersArray=array();
		$IAactive_detainersArray['DCNumber']= trim($DCNumber);
		$IA_detainerstablename="inmate_active_detainers";
		$detainers_condition=" WHERE DCNumber='".$IAactive_detainersArray['DCNumber']."'";
		$detainers_result=$this->crawler->array_exists($IA_detainerstablename,$detainers_condition);
		//$this->updateLog(" Going to insert records in $IA_detainerstablename table");
		if(count($detainers_result)==0)
		{
			$this->crawler->insert_array($IA_detainerstablename,$IAactive_detainersArray);
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_detainerstablename table ");
		}
		
		

		################### for inmate_active_incarhist table #############################
		$IA_incarhistArray=array();
		$IA_incarhistArray['DCNumber']= trim($DCNumber);
		$IA_incarhisttablename="inmate_active_incarhist";
		$incart_condition=" WHERE DCNumber='".$IA_incarhistArray['DCNumber']."'";
		$incart_result=$this->pssdb->Select($IA_incarhisttablename,$incart_condition);
		$this->updateLog(" Going to insert records in $IA_incarhisttablename table");
		if($incart_result==0)
		{
			$this->Insert($IA_incarhisttablename,$IA_incarhistArray);
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_incarhisttablename table ");
		}
		*/

		################### for inmate_active_aliases table ###########################
		$IA_aliasesArray = array();
		$IA_aliasesArray['DCNumber']	= $DCNumber;
		$IA_aliasesArray['FirstName']	= trim($FirstName);
		$IA_aliasesArray['MiddleName']	= trim($MiddleName);
		$IA_aliasesArray['LastName']	= trim($LastName);
		$IA_aliasesArray['NameSuffix']	= trim($NameSuffix);
		$IA_aliasestable="inmate_active_aliases";
		if($IA_aliasesArray['DCNumber']!='')
		{
			$aliases_condition=" WHERE DCNumber='".$IA_aliasesArray['DCNumber']."' and FirstName='".$IA_aliasesArray['FirstName']."' and LastName='".$IA_aliasesArray['LastName']."'";
			$aliases_result=$this->crawler->array_exists($IA_aliasestable,$aliases_condition);
			if(count($aliases_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $IA_aliasestable table");
				$this->crawler->insert_array($IA_aliasestable,$IA_aliasesArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_aliasestable table ");
			}
		}

		############################## for states table ###########################
		$stateArray=array();
		$stateArray['DCNumber']	 = trim($DCNumber);
		$stateArray['state_code']= $this->State;
		$stateArray['image_status']= 1;
		$stateTablename="states";
		if($stateArray['DCNumber']!='')
		{
			$stateCondition="where DCNumber='".$stateArray['DCNumber']."' and state_code='".$stateArray['state_code']."'";
			$stete_result=$this->crawler->array_exists($stateTablename,$stateCondition);
			if(count($stete_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $stateTablename table");
				$this->crawler->insert_array($stateTablename,$stateArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $stateTablename table ");
			}
		}
		############################## for inmate_images table ###########################

		$inmate_imagesArray=array();
		$finalimageUrl=$finalArray['ImageUrl'];
		$Imagesource=$this->psslib->getimagesource($finalimageUrl);
		$inmate_imagesArray['DCNumber']		= trim($DCNumber);
		$inmate_imagesArray['haveImage']	= $Imagesource;
		$inmate_imagesArray['updated']		= date('y-m-d');
		$inmate_imagesTable="inmate_images";
		if($inmate_imagesArray['DCNumber']!='')
		{
			$inmate_imagesCondition="where DCNumber='".$inmate_imagesArray['DCNumber']."' and haveImage='".$inmate_imagesArray['haveImage']."'";
			$stete_result=$this->crawler->array_exists($inmate_imagesTable,$inmate_imagesCondition);
			if(count($stete_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $inmate_imagesTable table");
				$this->crawler->insert_array($inmate_imagesTable,$inmate_imagesArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $inmate_imagesTable table");
			}
		}
	}
	function unlinkfiles()
	{
		if(file_exists($this->cookFile)){unlink($this->cookFile);}
		if(file_exists($this->errorLog)){unlink($this->errorLog);}
		if(file_exists($this->logFile)){unlink($this->logFile);}
		if(file_exists($this->resume)){unlink($this->resume);}
		if(file_exists($this->currentLog)){unlink($this->currentLog);}

	}

	function resumeLogs($resumeLog)
	{
		$this->psslib->appendFile($this->resume,$resumeLog."\n");
	}

	function statusKeyExistence($pattern, $flag=0)
	{
		$keyArray = array();
		if(file_exists($this->resume)){
			$file = fopen($this->resume, "r");
			while(!feof($file)){
				$tempData = fgets($file);
				if(preg_match($pattern,$tempData,$match)){
					$temp = trim($match[1]);
					if(!in_array($temp, $keyArray)){
						$keyArray[] = $temp;
					}
				}
			}
			fclose($file);
			if($flag==0){
				array_pop($keyArray);
			}
		}
		return $keyArray;
	}
	function update()
	{
		$status=array('status'=>'Success');
		
		$date = date('y-m-d');
		$this->db->where('date',$date);
        $this->db->update('cron_status', $status); 
	}
}
?>