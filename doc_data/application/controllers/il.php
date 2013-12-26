<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class il extends CI_Controller {
	/*var $proxyPort;
	var $htmlDoc;
	var $xpath;
	var $proxyDoneArr = array();
	var $proxyMaxHits = 50;
	var $proxyStatus = 0;
	var $proxyArr;
	var $proxyCount = 0;
	var $proxyDetail = '';
	var $websiteCurrHits = 0;*/
	var $proxyFile='application/models/proxy.txt';

	function __construct(){
		parent::__construct();
		$this->load->model('crawler');
		$this->siteName	  = "illinois";
        $this->cookFile	  = "application/logs/".$this->siteName."_cookies.txt";
        $this->errorLog   = "application/logs/".$this->siteName."_errorLog.txt";
        $this->statusLog  = "application/logs/".$this->siteName."_statusLog.txt";
        $this->logFile    = "application/logs/".$this->siteName."_logFile.txt";
		$this->proxyLog   = "application/logs/".$this->siteName."_proxyLog.txt";
        $this->currentLog = "application/logs/".$this->siteName."_currentLog.txt";
        $this->htmlDoc    = new DomDocument();
		$this->url        = "http://www2.illinois.gov/idoc/Offender/Pages/InmateSearch.aspx";
		$this->img		  = "http://www.idoc.state.il.us/subsections/search/";
		$this->State	  = "IL";
		$this->db 		  = $this->crawler->development_database();
	}
	function index(){
	
		$this->unlinkfiles();
		if(file_exists($this->proxyFile))
		{
			$this->proxyStatus=$this->psslib->checkProxy();
		}
		$table='cron_status';
		$condition='WHERE status="Running" and state="IL"';
		$res=$this->crawler->cron_exists($table,$condition);
		$status=$res[0]['status'];
		if($status=="Running")
		{
		 echo "Already Running.";
		}
		else
		{
		$table='cron_status';
		$condition='WHERE state="IL" and date=CURDATE()';
		$res=$this->crawler->cron_exists($table,$condition);
		$count=count($res);
		if($count==0)
		{
			$date = date('y-m-d');
			$values=array('status'=>'Running',
			'state'=>$this->State,'date'=>$date);
			$this->db->insert('cron_status',$values);
			$this->searchPagebyname();
		}
		else
		{
			echo "Already run on today";
		}
		$this->update();
		}
	}
	function searchPagebyname()
	{
		$url=$this->url;
		$this->psslib->updateLog(" Going to search page by name	...");
		$resultFile=$this->psslib->getandcheckPage($url,"");
		if(preg_match('/<form.*?id\W+aspnetForm.*?>(.*?)<\/form>/is',$resultFile,$match))
		{
			$result=$match[1];
			$formData=$this->psslib->parseFormData($result);
			$formData['idoc']="a";
			$formData['selectlist1']="Last";
			$formData['submit']="Inmate Search";
			$contents=$formData=$this->psslib->getContent($formData);
			$url2="http://www.idoc.state.il.us/subsections/search/ISListInmates2.asp";
			$nextResult=$this->psslib->getandcheckPage($url2,$contents,'');
			$i=0;
			while(preg_match('/<OPTION\s*SELECTED/is',$nextResult,$match))
			{
				$nextResult=$this->psslib->after($match[0],$nextResult);
				if(preg_match('/>(.*?)<\/option>/is',$nextResult,$match))
				{
					$selectonebyone=$match[1];
					$selectonebyone=preg_replace('/<.*?>/is','',$selectonebyone);
					$selectonebyone=preg_replace('/\s+/is',' ',$selectonebyone);
					$selectonebyone=preg_replace('/\s+$/is','',$selectonebyone);
					$this->getilliFinalpage($selectonebyone,$i);
				}
				$i++;
			}
		}
	}
	function getilliFinalpage($selectonebyone,$i)
	{
		$this->psslib->updateLog(" Going to final page get the final data for $selectonebyone...");
		$formData2['idoc']=$selectonebyone;
		$posturl	= "http://www.idoc.state.il.us/subsections/search/ISinms2.asp";
		$contents	= $this->psslib->getContent($formData2);
		$FinalResultFile = $this->psslib->getandcheckPage($posturl,$contents,'');
		$FinalResultFile = preg_replace('/&nbsp;/is','',$FinalResultFile);
		$FinalResultFile = preg_replace('/&amp;/is','&',$FinalResultFile);
		//$FinalResultFile=file_get_contents("application/models/finalsite10.html");
		//$this->writeToFile("finalsite$i.html",$FinalResultFile);
		@$this->htmlDoc->loadHTML($FinalResultFile);
		$this->xpath = new DOMXPath($this->htmlDoc);
		$xpathVal='//table[@class="tmpl_brdrclr"]//table[2]//td/img/@src';
		$nodelist = $this->xpath->query($xpathVal);
		$finalArray=array();

		############################ ImageUrl information ###############################
		$ImageUrl='';
		$errorFlag=0;
		$imageArray=array();
		foreach($nodelist as $n)
		{
			$errorFlag=1;
			$ImageUrl=$n->nodeValue;
			if(!preg_match('/^http/is',$ImageUrl))
			{
				$ImageUrl=$this->img.$ImageUrl;
				$ImageUrl=$this->psslib->htmlEntityToHtml($ImageUrl);
			}
			$imageArray[]=$ImageUrl;
		}
		if($errorFlag==0)
		{
			$this->psslib->appendFile($this->psslib->errorLog,"table 'tmpl_brdrclr' not found on website");
		}
		$finalimageUrl=$imageArray[0];
		$Imagesource=$this->psslib->getimagesource($finalimageUrl);
		############################ parent information ###############################
		$ParentInstitution='';
		if(preg_match('/>\s*Parent\s*Institution.*?font>(.*?)<\/font>/is',$FinalResultFile,$match))
		{
			$ParentInstitution=trim($match[1]);
			$ParentInstitution=preg_replace('/<.*?>/is','',$ParentInstitution);
			$ParentInstitution=$this->psslib->htmlEntityToHtml($ParentInstitution);
			$finalArray['ParentInstitution']=$ParentInstitution;
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," ParentInstitution not found on website");
		}
		$OffenderStatus='';
		if(preg_match('/>\s*Offender\s*Status.*?font>(.*?)<\/font>/is',$FinalResultFile,$match))
		{
			$OffenderStatus=trim($match[1]);
			$OffenderStatus=preg_replace('/<.*?>/is','',$OffenderStatus);
			$OffenderStatus=$this->psslib->htmlEntityToHtml($OffenderStatus);
			$finalArray['OffenderStatus']=$OffenderStatus;
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," OffenderStatus not found on website");
		}
		$Location='';
		if(preg_match('/>\s*Location\s*\: .*?font>(.*?)<\/font>/is',$FinalResultFile,$match))
		{
			$Location=trim($match[1]);
			$Location=preg_replace('/<.*?>/is','',$Location);
			$Location=$this->psslib->htmlEntityToHtml($Location);
			$finalArray['location']=$Location;
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," Location not found on website");
		}
		$name='';
		$DCNumber='';
		$FirstName='';
		$LastName='';
		$MiddleName='';
		$NameSuffix='';
		if(preg_match('/<div\s*align\W+center\"\s*>\s*<font.*?face\W+Arial\s*\,\s*Helvetica\s*\,\s*sans\W+serif.*?>(.*?)<\/font>/is',$FinalResultFile,$match))
		{
			$tempname=$match[1];
			$tempname=preg_replace('/<.*?>/is','',$tempname);
			if(preg_match('/(.*?)\-(.*)/is',$tempname,$match))
			{
				$DCNumber=trim($match[1]);
				$name=trim($match[2]);
				$name=preg_replace('/\W+/is',' ',$name);
				$name=preg_replace('/\s+$/is','',$name);
				$name=$this->psslib->htmlEntityToHtml($name);
				$name=explode(' ',$name);
				if(count($name)==1)
				{
					$FirstName=$name[0];
					$FirstName=$this->psslib->htmlEntityToHtml($FirstName);
				}
				if(count($name)==2)
				{
					$FirstName=$name[0];
					$FirstName=$this->psslib->htmlEntityToHtml($FirstName);
					$LastName =$name[1];
					$LastName=$this->psslib->htmlEntityToHtml($LastName);
				}
				if(count($name)==3)
				{
					$FirstName = $name[0];
					$FirstName=$this->psslib->htmlEntityToHtml($FirstName);
					$MiddleName= $name[1];
					$MiddleName=$this->psslib->htmlEntityToHtml($MiddleName);
					$LastName  = $name[2];
					$LastName=$this->psslib->htmlEntityToHtml($LastName);
					if(strlen($LastName)<=2)
					{
						$NameSuffix=$LastName;
						$LastName = $MiddleName;
						$MiddleName="";
					}
				}
				if(count($name)==4)
				{
					$FirstName = $name[0];
					$FirstName=$this->psslib->htmlEntityToHtml($FirstName);
					$MiddleName= $name[1];
					$MiddleName=$this->psslib->htmlEntityToHtml($MiddleName);
					$LastName  = $name[2];
					$LastName=$this->psslib->htmlEntityToHtml($LastName);
					$NameSuffix= $name[3];
					$NameSuffix=$this->psslib->htmlEntityToHtml($NameSuffix);
				}

			}
			else{
				$this->psslib->appendFile($this->psslib->errorLog," name and DCNumber not found on website");
			}

		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog,"face Arial Helvetica not found for NAME and DCNumber ");
		}
		$finalArray['FirstName']	=trim($FirstName);
		$finalArray['LastName']		=trim($LastName);
		$finalArray['MiddleName']	=trim($MiddleName);
		$finalArray['NameSuffix']	=trim($NameSuffix);
		$finalArray['DCNumber']		=trim($DCNumber);

		################### physical profile #####################
		$this->xpath = new DOMXPath($this->htmlDoc);
		$xpathVal7='//table[@class="tmpl_brdrclr"]//table[5]//tr';
		$nodelist7 = $this->xpath->query($xpathVal7);
		foreach($nodelist7 as $n)
		{
			$xpathVal6='.//td//b';
			$nodelist6 = $this->xpath->query($xpathVal6,$n);
			foreach($nodelist6 as $k)
			{
				$key1=trim($k->nodeValue);
				$key1=preg_replace('/\:/is','',$key1);
				$key1=preg_replace('/<.*?>/is','',$key1);
				$key1=preg_replace('/\s+/is',' ',$key1);
				$key1=preg_replace('/\s+$/is','',$key1);
				$key1=$this->psslib->htmlEntityToHtml($key1);
			}
			$xpathVal3='.//td[2]';
			$nodelist3 = $this->xpath->query($xpathVal3,$n);
			foreach($nodelist3 as $kk)
			{
				$value1=trim($kk->nodeValue);
				$value1=preg_replace('/<.*?>/is','',$value1);
				$value1=preg_replace('/\s+/is',' ',$value1);
				$value1=$this->psslib->htmlEntityToHtml($value1);
			}
			$finalArray[$key1]=$value1;
		}

		################### MARKS, SCARS, & TATTOOS #####################
		$tatooArray=array();
		if(preg_match('/>\s*MARKS\,\s*SCARS\,\s*\W+\s*TATTOOS\s*</is',$FinalResultFile))
		{

			$this->xpath = new DOMXPath($this->htmlDoc);
			$xpathVal78='//table[@class="tmpl_brdrclr"]//table[6]//tr//font';
			$nodelist78 = $this->xpath->query($xpathVal78);
			foreach($nodelist78 as $n)
			{
				$marks=$n->nodeValue;
				$marks=preg_replace('/\s+$/is','',$marks);
				$marks=$this->psslib->htmlEntityToHtml($marks);
				if(preg_match('/MARKS\,\s*SCARS\,\s*\W+\s*TATTOOS/is',$marks))
				{
					continue;
				}
				$tatooArray[]=$marks;
			}

		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," MARKS SCARS TATTOOS not found on website");
		}


		################### ADMISSION RELEASE DISCHARGE INFO #####################

		if(preg_match('/>\s*ADMISSION\s*\/\s*RELEASE\s*\/\s*DISCHARGE\s*INFO\s*</is',$FinalResultFile))
		{
			$this->xpath = new DOMXPath($this->htmlDoc);
			$xpathVal82='//table[@class="tmpl_brdrclr"]//table[8]//tr';
			$nodelist82 = $this->xpath->query($xpathVal82);
			foreach($nodelist82 as $n)
			{
				$xpathVal88='.//td[1]//b';
				$nodelist88 = $this->xpath->query($xpathVal88,$n);
				foreach($nodelist88 as $u)
				{
					$key2=trim($u->nodeValue);
					$key2=preg_replace('/\:/is','',$key2);
					$key2=preg_replace('/<.*?>/is','',$key2);
					$key2=preg_replace('/\s+/is',' ',$key2);
					$key2=preg_replace('/\s+$/is','',$key2);
					$key2=preg_replace('/^\s+/is','',$key2);
					$key2=$this->psslib->htmlEntityToHtml($key2);
				}
				$xpathVal88='.//td[2]//font';
				$nodelist88 = $this->xpath->query($xpathVal88,$n);
				foreach($nodelist88 as $u)
				{
					$value2=trim($u->nodeValue);
					$value2=preg_replace('/<.*?>/is','',$value2);
					$value2=preg_replace('/\s+/is',' ',$value2);
					$value2=$this->psslib->htmlEntityToHtml($value2);
				}
				$finalArray[$key2]=$value2;
			}
		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," ADMISSION RELEASE DISCHARGE INFO not found on website");
		}

		################### SENTENCING INFORMATION ###################
		if(preg_match('/>\s*SENTENCING\s*INFORMATION.*?table>(.*?)<\/table></is',$FinalResultFile,$match))
		{
			$sentArray=array();
			$tempfile=$match[1];
			@$this->htmlDoc->loadHTML($tempfile);
			$this->xpath = new DOMXPath($this->htmlDoc);
			$xpathVal='//table//tr';
			$nodelist = $this->xpath->query($xpathVal);
			foreach($nodelist as $n)
			{
				$xpathVal9='./td[1]';
				$nodelist9 = $this->xpath->query($xpathVal9,$n);
				foreach($nodelist9 as $tt)
				{
					$key3=trim($tt->nodeValue);
					$key3=preg_replace('/\:/is','',$key3);
					$key3=preg_replace('/<.*?>/is','',$key3);
					$key3=preg_replace('/\s+/is','',$key3);
					$key3=$this->psslib->htmlEntityToHtml($key3);
				}
				$xpathVal98='./td[2]';
				$nodelist98 = $this->xpath->query($xpathVal98,$n);
				foreach($nodelist98 as $kk)
				{
					$value3=trim($kk->nodeValue);
					$value3=preg_replace('/<.*?>/is','',$value3);
					$value3=preg_replace('/\s+/is','',$value3);
					$value3=$this->psslib->htmlEntityToHtml($value3);

				}
				if(trim($key3)==null)
				{
					$offenseArray[]=$sentArray;
					$sentArray=array();
				}else{
					$sentArray["$key3"]=$value3;
				}
			}

		}
		else
		{
			$this->psslib->appendFile($this->psslib->errorLog," SENTENCING INFORMATION not found on website");
		}

		//--------------------for inmate_active_scars table -----------------
		$newarr=array();
		$type='';
		$tattootype='';
		$location='';
		$description='';
		for($i=0;$i<count($tatooArray);$i++)
		{
			$newarr=$tatooArray[$i];
			if(preg_match('/(.*)\s*\-(.*)/is',$newarr,$match))
			{
				$type = trim($match[1]);
				$description = trim($match[2]);
				$description = preg_replace('/\s+$/is','',$description);
				$description = preg_replace('/,/is',' ',$description);
				$description = preg_replace('/\W+/is',' ',$description);
				$description = $this->psslib->htmlEntityToHtml($description);
				$type = preg_replace('/\W+/is',' ',$type);
				$type = preg_replace('/\s+$/is','',$type);
				$type=$this->psslib->htmlEntityToHtml($type);
				$type = explode(' ',$type);
				if(count($type)==1)
				{
					$tattootype=trim($type[0]);
					$tattootype=$this->psslib->htmlEntityToHtml($tattootype);
				}
				if(count($type)==2)
				{
					$tattootype=trim($type[0]);
					$tattootype=$this->psslib->htmlEntityToHtml($tattootype);
					$location =trim($type[1]);
					$location=$this->psslib->htmlEntityToHtml($location);
				}
				if(count($type)==3)
				{
					$tattootype=trim($type[0]);
					$tattootype=$this->psslib->htmlEntityToHtml($tattootype);
					$location =$type[1]." ".$type[2];
					$location=$this->psslib->htmlEntityToHtml($location);
				}
				if(count($type)==4)
				{
					$tattootype = trim($type[0]);
					$tattootype = $this->psslib->htmlEntityToHtml($tattootype);
					$location	= $type[1]." ".$type[2]." ".$type[3];
					$location	= $this->psslib->htmlEntityToHtml($location);
				}
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," tatootype, tatoolocation and desciption not found on website");
			}
			$IA_scarsmarksarray=array();
			$IA_scarsmarksarray['DCNumber']		= trim($finalArray['DCNumber']);
			$IA_scarsmarksarray['Type']			= trim($tattootype);
			$IA_scarsmarksarray['Location']		= trim($location);
			$IA_scarsmarksarray['Description']	= trim($description);
			if($IA_scarsmarksarray['DCNumber']!='')
			{
				if($IA_scarsmarksarray['Type']!='' && $IA_scarsmarksarray['Location']!='' && $IA_scarsmarksarray['Description']!='')
				{
					$IA_scarsmarksTable = "inmate_active_scars";
					$IA_scarsmarksCondition = " WHERE DCNumber='".$IA_scarsmarksarray['DCNumber']."' and type ='".$IA_scarsmarksarray['Type']."' and Location= '".$IA_scarsmarksarray['Location']."' and Description ='".$IA_scarsmarksarray['Description']."'";
					$IA_scarmarksResult =$this->crawler->array_exists($IA_scarsmarksTable,$IA_scarsmarksCondition);

					if(count($IA_scarmarksResult)==0)
					{
						$this->psslib->updateLog(" Going to insert records in $IA_scarsmarksTable table for $selectonebyone");
						$this->crawler->insert_array($IA_scarsmarksTable,$IA_scarsmarksarray);
					}
					else
					{
						$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_scarsmarksTable table for $selectonebyone ");
					}
				}
			}
		}
	
		//------------------ for inmate_active_offenses_cps table -----------------
		foreach($offenseArray as $key)
		{
			//print_R($key);
			if(array_key_exists("MITTIMUS",$key))
			{
				$mitimus=$key['MITTIMUS'];
				$mitimus=$this->psslib->htmlEntityToHtml($mitimus);
			}
			if(array_key_exists("COUNT",$key))
			{
				 $count=$key['COUNT'];
				 $count=$this->psslib->htmlEntityToHtml($count);
			}
			if(array_key_exists("OFFENSE",$key))
			{
				$offense=$key['OFFENSE'];
				$offense=$this->psslib->htmlEntityToHtml($offense);
			}
			if(array_key_exists("COUNTY",$key))
			{
				$County=$key['COUNTY'];
				$County=$this->psslib->htmlEntityToHtml($County);
			}
			if(array_key_exists("SENTENCE",$key))
			{
				$sentence=$key['SENTENCE'];
				$sentence=$this->psslib->htmlEntityToHtml($sentence);
			}
			$Sequence	= trim($count);
			$CaseNumber	= trim($mitimus);
			$prisonterm	= trim($sentence);
			$ParoleTerm	= $finalArray['Projected Discharge Date'];
			$AdjudicationCharge = trim($offense);
			$IA_offenseArray	= array();
			$IA_offenseArray['DCNumber'] = $finalArray['DCNumber'];
			$IA_offenseArray['Sequence'] = $Sequence;
			$IA_offenseArray['AdjudicationCharge'] = $AdjudicationCharge;
			$IA_offenseArray['CaseNumber']	= $CaseNumber;
			$IA_offenseArray['prisonterm']	= $prisonterm;
			$IA_offenseArray['ParoleTerm']	= trim($ParoleTerm);
			$IA_offenseArray['County']		= trim($County);
			$IA_offense_Tablename="inmate_active_offenses_cps";
			if($IA_offenseArray['DCNumber']!='')
			{
				$IA_offense_condition = "where `DCNumber`='".$IA_offenseArray['DCNumber']."'and CaseNumber='".$IA_offenseArray['CaseNumber']."' and AdjudicationCharge='".$IA_offenseArray['AdjudicationCharge']."'";
				$IA_offense_result=$this->crawler->array_exists($IA_offense_Tablename,$IA_offense_condition);
				if(count($IA_offense_result)==0)
				{
					$this->psslib->updateLog(" Going to insert records in $IA_offense_Tablename table for $selectonebyone");
					$this->crawler->insert_array($IA_offense_Tablename,$IA_offenseArray);
				}
				else
				{
					$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_offense_Tablename table for $selectonebyone ");
				}
			}

		}
		//-------------insert data in inmate_active_root table --------------
		$DOB='';
		$ReceiptDate='';
		$PrisonReleaseDate='';
		$DOB =$finalArray['Date of Birth'];
		$DOB  = date('Y-m-d', strtotime($DOB)); // date format for enter in DataBase
		$ReceiptDate = $finalArray['Admission Date'];
		if($ReceiptDate!='')
		{
			$ReceiptDate = date('Y-m-d', strtotime($ReceiptDate));
		}
		if(array_key_exists("Last Paroled Date",$finalArray))
		{
			$PrisonReleaseDate = trim($finalArray['Last Paroled Date']);
		}
		if(array_key_exists("Parole Date",$finalArray))
		{
			$PrisonReleaseDate = trim($finalArray['Parole Date']);
		}
		$PrisonReleaseDate=$this->psslib->htmlEntityToHtml($PrisonReleaseDate);
		if($PrisonReleaseDate!='')
		{
			$PrisonReleaseDate = date('Y-m-d', strtotime($PrisonReleaseDate));
		}
		$ReleaseDateFlag= $finalArray['OffenderStatus'];
		if($ReleaseDateFlag!=null)
		{
			$ReleaseDateFlag="Y";
		}
		$IA_Active_RootArray=array();
		$IA_Active_RootArray['FirstName']	= $finalArray['FirstName'];
		$IA_Active_RootArray['LastName']	= $finalArray['LastName'];
		$IA_Active_RootArray['MiddleName']	= $finalArray['MiddleName'];
		$IA_Active_RootArray['NameSuffix']	= $finalArray['NameSuffix'];
		$IA_Active_RootArray['DCNumber']	= $finalArray['DCNumber'];
		$IA_Active_RootArray['Race']		= $finalArray['Race'];
		$IA_Active_RootArray['Sex']			= $finalArray['Sex'];
		$IA_Active_RootArray['EyeColor']	= $finalArray['Eyes'];
		$IA_Active_RootArray['HairColor']	= $finalArray['Hair'];
		$IA_Active_RootArray['BirthDate']	= $DOB;
		$IA_Active_RootArray['Height']		= $finalArray['Height'];
		$IA_Active_RootArray['Height']		= preg_replace('/ft|in./is','',$IA_Active_RootArray['Height']);
		$IA_Active_RootArray['Height']		= preg_replace('/\s+/is','',$IA_Active_RootArray['Height']);
		$IA_Active_RootArray['Weight']		= $finalArray['Weight'];
		$IA_Active_RootArray['Weight']		= preg_replace('/lbs.|lbs/is','',$IA_Active_RootArray['Weight']);
		$IA_Active_RootArray['Facility']	= $finalArray['ParentInstitution'];
		$IA_Active_RootArray['ReleaseDateFlag']		= $ReleaseDateFlag;
		$IA_Active_RootArray['FACILITY_description']= $finalArray['location'];
		$IA_Active_RootArray['PrisonReleaseDate']	= $PrisonReleaseDate;
		$IA_Active_RootArray['ReceiptDate']			= $ReceiptDate;
		$IA_Active_RootTablename="inmate_active_root";
		if($IA_Active_RootArray['DCNumber']!='')
		{
			$IA_offense_condition	= "where `DCNumber`='".$IA_Active_RootArray['DCNumber']."'and FirstName='".$IA_Active_RootArray['FirstName']."' and LastName='".$IA_Active_RootArray['LastName']."' and BirthDate like '%".$IA_Active_RootArray['BirthDate']."%' and ReceiptDate like '%".$IA_Active_RootArray['ReceiptDate']."%'";
			$IA_offense_result=$this->crawler->array_exists($IA_Active_RootTablename,$IA_offense_condition);
			if(count($IA_offense_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $IA_Active_RootTablename table for $selectonebyone ");
				$this->crawler->insert_array($IA_Active_RootTablename,$IA_Active_RootArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_Active_RootTablename table for $selectonebyone ");
			}
		}


		//------------------------inmate_active_aliases -----------------
		$IA_aliasArray = array();
		$IA_aliasArray['DCNumber']	 = $finalArray['DCNumber'];
		$IA_aliasArray['FirstName']	 = $finalArray['FirstName'];
		$IA_aliasArray['MiddleName'] = $finalArray['MiddleName'];
		$IA_aliasArray['LastName']   = $finalArray['LastName'];
		$IA_aliasArray['NameSuffix'] = $finalArray['NameSuffix'];
		$IA_aliasTablename	="inmate_active_aliases";
		if($IA_aliasArray['DCNumber']!='')
		{
			$IA_alias_condition	= "where `DCNumber`='".$IA_Active_RootArray['DCNumber']."'and FirstName='".$IA_Active_RootArray['FirstName']."' and LastName='".$IA_Active_RootArray['LastName']."'";
			$IA_alias_result=$this->crawler->array_exists($IA_aliasTablename,$IA_alias_condition);
			if(count($IA_alias_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $IA_aliasTablename table for $selectonebyone ");
				$this->crawler->insert_array($IA_aliasTablename,$IA_aliasArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_aliasTablename table for $selectonebyone ");
			}
		}


		//------------------------inmate_active_incarhist -----------------
		$IA_incarhistArray = array();
		$IA_incarhistArray['DCNumber']		= $finalArray['DCNumber'];
		$IA_incarhistArray['ReceiptDate']	= $ReceiptDate;
		$IA_incarhistArray['ReleaseDate']	= $PrisonReleaseDate;
		if($IA_incarhistArray['DCNumber']!='')
		{
			if($IA_incarhistArray['ReceiptDate']!='' && $IA_incarhistArray['ReleaseDate']!='')
			{
				$IA_incarhistTablename	="inmate_active_incarhist";
				$IA_incarhistCondition	= "where `DCNumber`='".$IA_incarhistArray['DCNumber']."'and ReceiptDate like'%".$IA_incarhistArray['ReceiptDate']."%' and ReleaseDate like '%".$IA_incarhistArray['ReleaseDate']."%'";
				$IA_incarhistResult=$this->crawler->array_exists($IA_incarhistTablename,$IA_incarhistCondition);
				if(count($IA_incarhistResult)==0)
				{
					$this->psslib->updateLog(" Going to insert records in $IA_incarhistTablename table for $selectonebyone ");
					$this->crawler->insert_array($IA_incarhistTablename,$IA_incarhistArray);
				}
				else
				{
					$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $IA_incarhistTablename table for $selectonebyone ");
				}
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
				$this->psslib->updateLog(" Going to insert records in $stateTablename table for $selectonebyone");
				$this->crawler->insert_array($stateTablename,$stateArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $stateTablename table for $selectonebyone ");
			}
		}
		################# for inmate_images table ###########################
		$inmate_imagesArray=array();
		$inmate_imagesArray['DCNumber']	= trim($DCNumber);
		$inmate_imagesArray['haveImage']= $Imagesource;
		$inmate_imagesArray['updated']	= date('y-m-d');
		$inmate_imagesTable="inmate_images";
		if($inmate_imagesArray['DCNumber']!='')
		{
			$inmate_imagesCondition="where DCNumber='".$inmate_imagesArray['DCNumber']."' and haveImage='".$inmate_imagesArray['haveImage']."'";
			$inmate_images_result=$this->crawler->array_exists($inmate_imagesTable,$inmate_imagesCondition);
			if(count($inmate_images_result)==0)
			{
				$this->psslib->updateLog(" Going to insert records in $inmate_imagesTable table for $selectonebyone ");
				$this->crawler->insert_array($inmate_imagesTable,$inmate_imagesArray);
			}
			else
			{
				$this->psslib->appendFile($this->psslib->errorLog," duplicate records are already exists in $inmate_imagesTable table for $selectonebyone ");
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
        $this->db->update('cron_status', $status); 
	}
	
	
	
}
?>