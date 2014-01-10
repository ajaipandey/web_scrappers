<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
error_reporting(E_ERROR | E_WARNING | E_PARSE);
class oh extends CI_Controller {
	var $proxyFile='application/models/proxy.txt';
	function __construct(){
		parent::__construct();
		$this->load->model('crawler');
		$this->siteName	    = "ohio";
        $this->cookFile	    = "application/logs/".$this->siteName."_cookies.txt";
        $this->errorLog     = "application/logs/".$this->siteName."_errorLog.txt";
        $this->statusLog    = "application/logs/".$this->siteName."_statusLog.txt";
        $this->logFile      = "application/logs/".$this->siteName."_logFile.txt";
        $this->currentLog   = "application/logs/".$this->siteName."_currentLog.txt";
        $this->proxyLog     = "application/logs/".$this->siteName."_proxyLog.txt";
        $this->htmlDoc      = new DomDocument();
		$this->url          = "http://www.drc.ohio.gov/OffenderSearch/search.aspx";
		$this->website      = "http://www.drc.ohio.gov/OffenderSearch/";
		$this->State		= "OH";
		$this->db = $this->crawler->development_database();
	}
	function index()
	{
		if(file_exists($this->proxyFile))
		{
			$this->proxyStatus=$this->psslib->checkProxy();
		}
		$table='CRON_STATUS';
		$condition='WHERE status="Running" and state="OH"';
		$res=$this->crawler->cron_exists($table,$condition);
		$status=$res[0]['status'];
		if($status=="Running")
		{
			echo "Already Running.";
		}
		else
		{   
			$table='CRON_STATUS';
			$condition='WHERE state="OH" and date=CURDATE()';
			$res=$this->crawler->cron_exists($table,$condition);
			$count=count($res);
			if($count==0)
			{   echo "Crawler OH Running\n";
				$date = date('y-m-d');
				$values=array('status'=>'Running',
				'state'=>$this->State,'date'=>$date);
				$this->db->insert('CRON_STATUS',$values);
				$this->runCrawler();
			}
			else
			{
				 echo "Already run on today";
			}
			$this->update();
			$this->success();
		}
	}
	function runCrawler()
	{
		$this->unlinkfiles();
		$seacrhName='a';
		$this->searchPage($seacrhName);
		$this->psslib->updateLog(" Going to search page by $seacrhName ...");
		
	}

	function searchPage()
	{
		$url=$this->url;
		$countyArray = array();
		$resultFile=$this->psslib->getandcheckPage($url,"");
		#$resultFile=file_get_contents('home.html');
		####-----Parse Country Array--------####
		@$this->htmlDoc->loadHTML($resultFile);
		$this->xpath = new DOMXPath($this->htmlDoc);
		$xpathVal='//select[@id="ctl00_ContentPlaceHolder1_DDL_ComCounty"]/option/@value';
		$nodelist = $this->xpath->query($xpathVal);
		foreach($nodelist as $n) {
			$tempData=trim($n->nodeValue);
			if(!preg_match('/Select\s+County/is',$tempData)){
				$countyArray[]=$tempData;
			}

		}

		if(preg_match('/id\W+aspnetForm.*?(.*?)<\/form>/is',$resultFile,$match))
		{
			$key1='a';
			$checkrecords=0;
			$formData=$this->psslib->parseFormData($match[1]);
			$resultFile2=$this->gotoFiltration($checkrecords,$formData,$key1);##Search By Fisrt Name
			// $this->nextPagebyname($resultFile2);exit;   //just for testing
			if($checkrecords>=500){
				for($i=97;$i<123;$i++) {##LOOP for Last name From a-z
					$key2=chr($i);
					$resultFile2=$this->gotoFiltration($checkrecords,$formData,$key1,$key2);##Search By Fisrt Name and Lastname Iteration
					if($checkrecords>=500){
						foreach($countyArray as $comCountyKey){
							$resultFile2=$this->gotoFiltration($checkrecords,$formData,$key1,$key2,$comCountyKey);##Search By Fisrt Name and Lastname Iteration
							if($checkrecords>=500){
								foreach($countyArray as $resCountyKey){
									$resultFile2=$this->gotoFiltration($checkrecords,$formData,$key1,$key2,$comCountyKey,$resCountyKey);##Search By Fisrt Name and Lastname Iteration
									$this->nextPagebyname($resultFile2);
								}
							}else{

								$this->nextPagebyname($resultFile2);
							}
						}
					}else{
						$this->psslib->updateLog(" Going to search page after search by first for $key1 and last name for $key2...");
						$this->nextPagebyname($resultFile2);
					}
				}
			}else{
				$this->psslib->updateLog(" Going to search page after search by last name for $key1...");
				$this->nextPagebyname($resultFile2);
			}
		}else{
			$this->psslib->errorLog(" -----Search Form Not Found-----!");
		}
	}

	function gotoFiltration(&$checkrecords,$formData,$fname=null,$lname=null,$ComCounty=null,$ResCounty=null)
	{
		if($fname!=null){
			$formData['ctl00$ContentPlaceHolder1$Txt_FName']=$fname;
		}
		if($lname!=null){
			$formData['ctl00$ContentPlaceHolder1$Txt_LName']=$lname;
		}
		if($ComCounty!=null){
			$formData['ctl00$ContentPlaceHolder1$DDL_ComCounty']=$ComCounty;
		}
		if($ResCounty!=null){
			$formData['ctl00$ContentPlaceHolder1$DDL_ResCounty']=$ResCounty;
		}

		#-------------Form Data Submition-----------#
		$formData['ctl00$ContentPlaceHolder1$Btn_Search']='Search';
		$formData['ctl00$ContentPlaceHolder1$RBL_Status']='A';
		$contents=$this->psslib->getContent($formData);
		$url2="http://www.drc.ohio.gov/OffenderSearch/search.aspx";
		$resultFile2=$this->psslib->getandcheckPage($url2,$contents,'');
		if(preg_match('/id\W+ctl00_ContentPlaceHolder1_Lbl_RecCnt.*?>\s*Records\s+Found\:\s+(\d+)\s+/is',$resultFile2,$match)){
			$checkrecords=$match[1];
		}
		return $resultFile2;
	}

	function nextPagebyname($nextResult)
	{
		$this->psslib->updateLog(" Going to nextpage after search by name...");
		@$this->htmlDoc->loadHTML($nextResult);
		$totalPage = 1;
		$this->xpath = new DOMXPath($this->htmlDoc);
		$xpathVal='//span[@id="ctl00_ContentPlaceHolder1_Lbl_PageCnt"]';
		$nodelist = $this->xpath->query($xpathVal);
		$errorFlag=0;
		foreach($nodelist as $n) {
			$errorFlag=1;
			$page=$n->c14n();
			if(preg_match('/Page\s*(\d*)\s*of\s*(\d*)/is',$page,$match)){
				$totalPage=$match[2];
			}
			else{
				$this->psslib->appendFile($this->psslib->errorLog," totalPage not found on website ");
			}
		}
		if($errorFlag==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog," span id 'ctl00_ContentPlaceHolder1_Lbl_PageCnt' not found on website ");
		}
		$second='';
		if(preg_match('/a\s*href\W+javascript\:__doPostBack\((.*?)\,/is',$nextResult,$match))
		{
			$second= $match[1];
			$second = preg_replace('/&#39;/is','',$second);
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog,"a href javascript\:__doPostBack not found");
		}
		$first='ctl00$ContentPlaceHolder1$UpdPnl_Results';
		$pagenumber=1;
		if(preg_match('/(<form.*?<\/form>)/is',$nextResult,$match))
		{
			$formpage=$match[1];
			$formData2=$this->psslib->parseFormData($formpage);
			$formData2['__EVENTARGUMENT']="Page".'$'.$pagenumber;
			$formData2['__EVENTTARGET']=$second;
			$ab='ctl00$ContentPlaceHolder1$ScriptManager1';
			$formData2["$ab"]="$first|$second";
			$formData2['__ASYNCPOST']='true';
			$contents=$this->psslib->getcontent($formData2);
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog,"form not found for pagnumber ($pagenumber)..");
		}
		$pagesResult=$nextResult;
		do
		{
			//$this->writeToFile("page$pagenumber.html",$pagesResult);
			@$this->htmlDoc->loadHTML($pagesResult);
			$this->psslib->updateLog("Going to Parse numberUrl for pagenumber ($pagenumber).......");
			$this->xpath = new DOMXPath($this->htmlDoc);
			$xpathVal='//table[@class="GV_General"]//tr[@class="GV_Row"]//td[3]//a/@href';
			$nodelist = $this->xpath->query($xpathVal);
			$errorFlag2=0;
			foreach($nodelist as $n)
			{
				$errorFlag2=1;
				$numberUrl=$n->nodeValue;
				if(!preg_match('/^http/is',$numberUrl))
				{
					$numberUrl=$this->website.$numberUrl;
				}
				else
				{
					$this->psslib->appendFile($this->psslib->errorLog,"numberUrl not found for pagenumber ($pagenumber)...");
				}
				$this->getFinalPage($numberUrl);
			}
			if($totalPage>=$pagenumber)
			{
				if($pagenumber>5){
					$viewstate='';
					$eventvalidation='';
					if(preg_match('/\__VIEWSTATE\|(.*?)\|.*?\__EVENTVALIDATION\|(.*?)\|/is',$pagesResult,$match))
					{
						$viewstate=$match[1];
						$eventvalidation=$match[2];
						$formData2['__VIEWSTATE']=$viewstate;
						$formData2['__EVENTVALIDATION']=$eventvalidation;
					}
				}
				$pagenumber=$pagenumber+1;
				$formData2['__EVENTARGUMENT']="Page".'$'.$pagenumber;
				$contents=$this->psslib->getcontent($formData2);
				$url1='http://www.drc.ohio.gov/OffenderSearch/results.aspx';
				$this->psslib->updateLog("Going to click Nextpage for pagenumber ($pagenumber) .......");
				$pagesResult=$this->psslib->getandcheckPage($url1,$contents,'');
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog,"pagination failed because of $totalPage>=$pagenumber ");
				break;
			}
		}while(1);
	}
	function getFinalPage($numberUrl)
	{
		$this->psslib->updateLog(" Going to final page for get the final data for $numberUrl ...");
		$finalresult=$this->psslib->getandcheckPage($numberUrl,'');
		$finalArray = array();

		#################### About #########################
		$AbArray=array();
		$Name='';
		$FirstName='';
		$LastName='';
		$MiddleName='';
		$NameSuffix='';
		if(preg_match('/class\W+detailTitle.*?>(.*?)</is',$finalresult,$match))
		{
			$Name=trim($match[1]);
			$Name = preg_replace('/\s+/is',' ',$Name);
			$NameArr=explode(' ',$Name);
			if(count($NameArr)==1)
			{
				$FirstName=$NameArr[0];
			}
			if(count($NameArr)==2)
			{
				$FirstName=$NameArr[0];
				$LastName=$NameArr[1];
			}
			if(count($NameArr)==3)
			{
				$FirstName=$NameArr[0];
				$MiddleName=$NameArr[1];
				$LastName=$NameArr[2];
				if(strlen($LastName)<=2)
				{
					$NameSuffix	= $LastName;
					$LastName	= $MiddleName;
					$MiddleName	= "";
				}
				if(strlen($FirstName)<=2)
				{
					$NameSuffix = $NameArr[0];
					$FirstName  = $NameArr[1];
					$LastName	= $NameArr[2];
					$MiddleName = '';
				}
			}
			if(count($NameArr)==4)
			{
				$FirstName  = $NameArr[0];
				$MiddleName = $NameArr[1];
				$LastName	= $NameArr[2];
				$NameSuffix	= $NameArr[3];
			}
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog,"Name not found ");
		}

		$Number='';
		if(preg_match('/>\s*Number:(.*?)<\/span>/is',$finalresult,$match))
		{
			$Number = trim($match[1]);
			$Number = preg_replace('/<.*?>/is','',$Number);
			$Number = preg_replace('/\s+/is',' ',$Number);
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog,"Number not found ");
		}
		@$this->htmlDoc->loadHTML($finalresult);
        $this->xpath = new DOMXPath($this->htmlDoc);
		$xpathVal='//span[@id="ctl00_ContentPlaceHolder1_Lbl_DOB"]';
        $nodelist = $this->xpath->query($xpathVal);
		$errorFlag=0;
		$DOB='';
		foreach($nodelist as $n)
		{
			$errorFlag=1;
			$DOB=trim($n->nodeValue);
			$DOB = preg_replace('/\s+/is',' ',$DOB);
			$DOB = date('Y-m-d', strtotime($DOB));
		}
		if($errorFlag==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"DOB not found ");
		}
		$xpathVal2='//span[@id="ctl00_ContentPlaceHolder1_Lbl_Sex"]';
        $nodelist2 = $this->xpath->query($xpathVal2);
		$errorFlag1=0;
		$Gender='';
		foreach($nodelist2 as $n)
		{
			$errorFlag1=1;
			$Gender=trim($n->nodeValue);
			$Gender = preg_replace('/\s+/is',' ',$Gender);

		}
		if($errorFlag1==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"Gender not found ");
		}
		$xpathVal23='//span[@id="ctl00_ContentPlaceHolder1_Lbl_Race"]';
        $nodelist23 = $this->xpath->query($xpathVal23);
		$errorFlag2=0;
		$Race='';
		foreach($nodelist23 as $n)
		{
			$errorFlag2=1;
			$Race=trim($n->nodeValue);
			$Race = preg_replace('/\s+/is',' ',$Race);

		}
		if($errorFlag2==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"Race not found ");
		}

		$xpathVal3='//span[@id="ctl00_ContentPlaceHolder1_Lbl_AdminDate"]';
        $nodelist3 = $this->xpath->query($xpathVal3);
		$errorFlag3=0;
		$Admission_date='';
		foreach($nodelist3 as $n)
		{
			$errorFlag3=1;
			$Admission_date=trim($n->nodeValue);
			$Admission_date = preg_replace('/\s+/is',' ',$Admission_date);
			$Admission_date = date('Y-m-d', strtotime($Admission_date));
		}
		if($errorFlag3==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"Admission_date not found ");
		}

		$xpathVal4='//span[@id="ctl00_ContentPlaceHolder1_Lbl_Inst"]';
        $nodelist4 = $this->xpath->query($xpathVal4);
		$errorFlag4=0;
		$Institution='';
		foreach($nodelist4 as $n)
		{
			$errorFlag4=1;
			$Institution=trim($n->nodeValue);
			$Institution = preg_replace('/\s+/is',' ',$Institution);

		}
		if($errorFlag4==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"Institution not found ");
		}
		$xpathVal5='//span[@id="ctl00_ContentPlaceHolder1_Lbl_Status"]';
        $nodelist5 = $this->xpath->query($xpathVal5);
		$errorFlag5=0;
		$status='';
		foreach($nodelist5 as $n)
		{
			$errorFlag5=1;
			$status=trim($n->nodeValue);
			$status = preg_replace('/\s+/is',' ',$status);

		}
		if($errorFlag5==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"status not found ");
		}

		$xpathVal7='//span[@id="ctl00_ContentPlaceHolder1_Lbl_ResCounty"]';
        $nodelist7 = $this->xpath->query($xpathVal7);
		$errorFlag7=0;
		$Residential_County='';
		foreach($nodelist7 as $n)
		{
			$errorFlag7=1;
			$Residential_County=trim($n->nodeValue);
			$Residential_County = preg_replace('/\s+/is',' ',$Residential_County);

		}
		if($errorFlag7==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"Residential_County not found ");
		}
		$xpathVal8='//img[@id="ctl00_ContentPlaceHolder1_Img_Offender"]/@src';
        $nodelist8 = $this->xpath->query($xpathVal8);
		$errorFlag8=0;
		$imageUrl='';
		foreach($nodelist8 as $n)
		{
			$errorFlag8=1;
			$imageUrl=trim($n->nodeValue);
			$imageUrl = preg_replace('/\s+/is',' ',$imageUrl);
			if(!preg_match('/^http/is',$imageUrl))
			{
				$imageUrl=$this->website.$imageUrl;
			}

		}
		if($errorFlag8==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"imageUrl not found ");
		}
		$AbArray['FirstName']	= trim($FirstName);
		$AbArray['MiddleName']	= trim($MiddleName);
		$AbArray['LastName']	= trim($LastName);
		$AbArray['NameSuffix']	= trim($NameSuffix);
		$AbArray['Facility']	= trim($Institution);
		$AbArray['imageUrl']	= trim($imageUrl);
		$AbArray['status']		= trim($status);
		$AbArray['BirthDate']	= trim($DOB);
		$AbArray['DCNumber']	= trim($Number);
		$AbArray['Sex']			= trim($Gender);
		$AbArray['Race']		= trim($Race);
		$AbArray['Admission_date']=trim($Admission_date);
		$AbArray['Residential_County']= trim($Residential_County);
		$finalArray['About']=$AbArray;

		###################### offenseInformation #####################

		if(preg_match('/>\s*Offense\s*Information\s*.*?>(.*?)class\W+detailTable/is',$finalresult,$match))
		{
			$offensepage=$match[1];
			@$this->htmlDoc->loadHTML($offensepage);
			$this->xpath = new DOMXPath($this->htmlDoc);
			$xpathVal9='//table';
			$nodelist9 = $this->xpath->query($xpathVal9);
			$offenseName='';
			$committing='';
			$addmission_date='';
			$orc='';
			$Degree_of_Felony='';
			$count='';
			foreach($nodelist9 as $n)
			{
				$fArray=array();
				$info=$n->c14n();
				//echo $info=$n->c14n();
				$xpathVal1='.//tr/td[1]/span';
				$nodelist1 = $this->xpath->query($xpathVal1,$n);
				foreach($nodelist1 as $l)
				{
					$offenseName=$l->nodeValue;
					$fArray[]=$offenseName;
				}
				$xpathVal1='.//tr[2]/td[2]/span[2]';
				$nodelist1 = $this->xpath->query($xpathVal1,$n);
				foreach($nodelist1 as $ll)
				{
					$committing=$ll->nodeValue;
					$fArray[]=$committing;
				}
				$xpathVal1='.//tr[2]/td[3]//span[2]';
				$nodelist1 = $this->xpath->query($xpathVal1,$n);
				foreach($nodelist1 as $ma)
				{
					$addmission_date=$ma->nodeValue;
					$fArray[]=$addmission_date;
				}
				$xpathVal1='.//tr[1]/td[3]//span[2]';
				$nodelist1 = $this->xpath->query($xpathVal1,$n);
				foreach($nodelist1 as $ms)
				{
					$orc=$ms->nodeValue;
					$fArray[]=$orc;
				}
				$xpathVal1='.//tr[2]/td[4]//span[2]';
				$nodelist1 = $this->xpath->query($xpathVal1,$n);
				foreach($nodelist1 as $mt)
				{
					$Degree_of_Felony=$mt->nodeValue;
					$fArray[]=$Degree_of_Felony;

				}
				$xpathVal1='.//tr[1]/td[2]//span[2]';
				$nodelist1 = $this->xpath->query($xpathVal1,$n);
				foreach($nodelist1 as $mm)
				{
					$count=$mm->nodeValue;
					$fArray[]=$count;
				}
				$Xarray=array();
				if(count($fArray)>0){
					$tArray[]=$fArray;
					$finalArray['offenseInfo']=$tArray;
				}
			}
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," Offense Information not found ");
		}
		######################### SentenseInformation ##################

		@$this->htmlDoc->loadHTML($finalresult);
		$this->xpath = new DOMXPath($this->htmlDoc);
		$xpathVal='//span[@id="ctl00_ContentPlaceHolder1_Lbl_SI_SB2"]';
		$nodelist = $this->xpath->query($xpathVal);
		$sInfo=array();
		$errorFlag9=0;
		foreach($nodelist as $n)
		{
			$errorFlag9=1;
			$ttt=$n->c14n();
			$StatedPrisonTerm='';
			$ExpirationStatedTerm='';
			$ActualReleaseDate='';
			if(preg_match('/(.*?)<\/br>(.*?)<\/br>(.*)/is',$ttt,$match))
			{
				$StatedPrisonTerm=$match[1];
				$StatedPrisonTerm = preg_replace('/<.*?>/is','',$StatedPrisonTerm);
				$ExpirationStatedTerm=$match[2];
				$ExpirationStatedTerm = preg_replace('/<.*?>/is','',$ExpirationStatedTerm);
				$ActualReleaseDate=$match[3];
				$ActualReleaseDate = preg_replace('/<.*?>/is','',$ActualReleaseDate);
				if($ActualReleaseDate!='')
				{
					$ActualReleaseDate = date('Y-m-d', strtotime($ActualReleaseDate));
				}
			}

			$sInfo['StatedPrisonTerm']		= $StatedPrisonTerm;
			$sInfo['ExpirationStatedTerm']	= $ExpirationStatedTerm;
			$sInfo['ActualReleaseDate']		= $ActualReleaseDate;
			$finalArray['sentenseInfo']		= $sInfo;
		}
		if($errorFlag9==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog," id 'ctl00_ContentPlaceHolder1_Lbl_SI_SB2' of span not found  ");
		}
		######################### PRCInfo ###########################################
		@$this->htmlDoc->loadHTML($finalresult);
		$this->xpath = new DOMXPath($this->htmlDoc);
		$xpathVal='//span[@id="ctl00_ContentPlaceHolder1_Lbl_PHInfo"]';
		$nodelist = $this->xpath->query($xpathVal);
		$PRCInfo=array();
		$errorFlag22=0;
		foreach($nodelist as $n)
		{
			$errorFlag22=1;
			$ttt=$n->c14n();
			$SVSdate='';
			$POsupervision='';
			$APAOffice='';
			if(preg_match('/(.*?)<\/br>(.*?)<\/br>(.*)/is',$ttt,$match))
			{
				$SVSdate = trim($match[1]);
				$SVSdate = preg_replace('/<.*?>/is','',$SVSdate);
				$POsupervision = trim($match[2]);
				$POsupervision = preg_replace('/<.*?>/is','',$POsupervision);
				$APAOffice	= trim($match[3]);
				$APAOffice	= preg_replace('/<.*?>/is','',$APAOffice);
			}
			$PRCInfo['APAOffice'] = $APAOffice;
			$PRCInfo['SuperVisionStartDate']= $SVSdate;
			$PRCInfo['PeriodOfSupervision']	= $POsupervision;
			$finalArray['PostReleaseInfo']	= $PRCInfo;
		}
		if($errorFlag22==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog," id 'ctl00_ContentPlaceHolder1_Lbl_PHInfo' of span not found  ");
		}


		//----------------- for INMATE_ACTIVE_ROOT table--------------
		$IA_rootArray=array();
		$IA_rootArray['FirstName']		= $AbArray['FirstName'];
		$IA_rootArray['MiddleName']		= $AbArray['MiddleName'];
		$IA_rootArray['LastName']		= $AbArray['LastName'];
		$IA_rootArray['NameSuffix']		= $AbArray['NameSuffix'];
		$IA_rootArray['DCNumber']		= $AbArray['DCNumber'];
		$IA_rootArray['BirthDate']		= $AbArray['BirthDate'];
		$IA_rootArray['Sex']			= $AbArray['Sex'];
		$IA_rootArray['Race']			= $AbArray['Race'];
		$IA_rootArray['Facility']		= $AbArray['Facility'];
		$IA_rootArray['PrisonReleaseDate']= $sInfo['ActualReleaseDate'];
		$IA_rootArray['ReceiptDate']	  = $AbArray['Admission_date'];
		if($sInfo['ActualReleaseDate']!=null)
		{
			$IA_rootArray['ReleaseDateFlag']="Y";
		}
		$IA_rootTable="INMATE_ACTIVE_ROOT";
		if($IA_rootArray['DCNumber']!='')
		{
			$IA_rootCondition="WHERE DCNumber='".$IA_rootArray['DCNumber']."' and BirthDate like '%".$IA_rootArray['BirthDate']."%' and FirstName='".$IA_rootArray['FirstName']."'";
			$IA_rootResult=$this->crawler->array_exists($IA_rootTable,$IA_rootCondition);
			if(count($IA_rootResult)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $IA_rootTable table  ");
				$this->crawler->insert_array($IA_rootTable,$IA_rootArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_rootTable table ");
			}
		}
		//------------------ INMATE_ACTIVE_INCARHIST ---------------------
		$IA_incarhistArray=array();
		$IA_incarhistArray['DCNumber']   = $AbArray['DCNumber'];
		$IA_incarhistArray['ReleaseDate']= $sInfo['ActualReleaseDate'];
		$IA_incarhistArray['ReceiptDate']= $AbArray['Admission_date'];
		$IA_incarhistTable = "INMATE_ACTIVE_INCARHIST";
		if($IA_incarhistArray['DCNumber']!='')
		{
			if($IA_incarhistArray['ReleaseDate']!='' && $IA_incarhistArray['ReceiptDate']!='')
			{
				$IA_incarhistCondition="WHERE DCNumber='".$IA_incarhistArray['DCNumber']."' and ReceiptDate='".$IA_incarhistArray['ReceiptDate']."'";
				$IA_incarhistResult=$this->crawler->array_exists($IA_incarhistTable,$IA_incarhistCondition);
				if(count($IA_incarhistResult)==0)
				{
					$this->psslib->updateLog(" Going to insert records in $IA_incarhistTable table  ");
					$this->crawler->insert_array($IA_incarhistTable,$IA_incarhistArray);
				}
				else
				{
					$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_incarhistTable table ");
				}
			}
		}
		//------------------ INMATE_ACTIVE_OFFENSES_CPS ---------------------
		foreach($finalArray['offenseInfo'] as $key)
		{
			$offenses_cpsArray['County']			= trim($key[1]);
			$offenses_cpsArray['DCNumber']			= $AbArray['DCNumber'];
			$offenses_cpsArray['prisonterm']		= $sInfo['StatedPrisonTerm'];
			$offenses_cpsArray['ParoleTerm']		= $PRCInfo['PeriodOfSupervision'];
			$offenses_cpsArray['AdjudicationCharge']	  = trim($key[0]);
			$offenses_cpsArray['adjudicationcharge_descr']= trim($key[3]);
			$offenses_cpsTable="INMATE_ACTIVE_OFFENSES_CPS";
			if($offenses_cpsArray['DCNumber']!='')
			{
				$offenses_cpsCondition="WHERE AdjudicationCharge='".$offenses_cpsArray['AdjudicationCharge']."' and DCNumber='".$offenses_cpsArray['DCNumber']."' and  adjudicationcharge_descr='".$offenses_cpsArray['adjudicationcharge_descr']."'";
				$offenses_cpsResult=$this->crawler->array_exists($offenses_cpsTable,$offenses_cpsCondition);
				if(count($offenses_cpsResult)==0)
				{
					$this->psslib->updateLog(" Going to insert records in $offenses_cpsTable table  ");
					$this->crawler->insert_array($offenses_cpsTable,$offenses_cpsArray);
				}
				else
				{
					$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $offenses_cpsTable table ");
				}
			}
		}

		//------------------ INMATE_ACTIVE_ALIASES ---------------------
		$IA_aliasesArray=array();
		$IA_aliasesArray['DCNumber']	= $AbArray['DCNumber'];
		$IA_aliasesArray['FirstName']	= $AbArray['FirstName'];
		$IA_aliasesArray['MiddleName']	= $AbArray['MiddleName'];
		$IA_aliasesArray['LastName']	= $AbArray['LastName'];
		$IA_aliasesArray['NameSuffix']	= $AbArray['NameSuffix'];
		$IA_aliasesTable ="INMATE_ACTIVE_ALIASES";
		if($IA_aliasesArray['DCNumber']!='')
		{
			$IA_aliasesCondition = " WHERE DCNumber='".$IA_aliasesArray['DCNumber']."' and FirstName='".$IA_aliasesArray['FirstName']."'";
			$IA_aliasesResult=$this->crawler->array_exists($IA_aliasesTable,$IA_aliasesCondition);
			if(count($IA_aliasesResult)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $IA_aliasesTable table  ");
				$this->crawler->insert_array($IA_aliasesTable,$IA_aliasesArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_aliasesTable table ");
			}
		}

		//----------------- for STATES table --------------------------
		$stateArray=array();
		$stateArray['DCNumber']	 = $AbArray['DCNumber'];
		$stateArray['state_code']= $this->State;
		$stateArray['image_status']= 1;
		$stateTablename="STATES";
		if($stateArray['DCNumber']!='')
		{
			$stateCondition="where DCNumber='".$stateArray['DCNumber']."' and state_code='".$stateArray['state_code']."'";
			$stete_result=$this->crawler->array_exists($stateTablename,$stateCondition);
			if(count($stete_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $stateTablename table  ");
				$this->crawler->insert_array($stateTablename,$stateArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $stateTablename table ");
			}
		}
		//----------------- for INMATE_IMAGES table --------------------------
		$Imagesource=$this->psslib->getimagesource($imageUrl);
		$inmate_imagesArray=array();
		$inmate_imagesArray['DCNumber']	 = $AbArray['DCNumber'];
		$inmate_imagesArray['haveImage'] = $Imagesource;
		$inmate_imagesArray['updated']	 = date('y-m-d');
		$inmate_imagesTable="INMATE_IMAGES";
		if($inmate_imagesArray['DCNumber']!='')
		{
			$inmate_imagesCondition="where DCNumber='".$inmate_imagesArray['DCNumber']."' and haveImage='".$inmate_imagesArray['haveImage']."'";
			$inmate_images_result=$this->crawler->array_exists($inmate_imagesTable,$inmate_imagesCondition);
			if(count($inmate_images_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $inmate_imagesTable table ");
				$this->crawler->insert_array($inmate_imagesTable,$inmate_imagesArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $inmate_imagesTable table ");
			}
		}
	}
	function unlinkfiles()
	{
		if(file_exists($this->cookFile)){unlink($this->cookFile);}
		if(file_exists($this->errorLog)){unlink($this->errorLog);}
		if(file_exists($this->logFile)){unlink($this->logFile);}
		if(file_exists($this->currentLog)){unlink($this->currentLog);}

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