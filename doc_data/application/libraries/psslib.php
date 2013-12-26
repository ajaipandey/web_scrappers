<?php

class psslib
{
	var $proxyPort;
	var $proxyDoneArr = array();
	var $proxyMaxHits = 50;
	var $proxyStatus = 0;
	var $proxyArr;
	var $proxyCount = 0;
	var $proxyDetail = '';
	var $websiteCurrHits = 0;
	var $proxyFile='proxy.txt';
	var $cookFile = "logs/pss_Cook.txt";
	var $errorURL = "";

	function pssLIB()
	{
		parent::__construct($this->cookFile);
	}

	function before ($inthis, $inthat)
	{
		return substr($inthat, 0, strpos($inthat, $inthis));
	}

	function after ($inthis, $inthat)
	{
		if (!is_bool(strpos($inthat, $inthis)))
		return substr($inthat, strpos($inthat,$inthis)+strlen($inthis));
	}

	function writeToFile($fileName,$data)
	{
		$fh = fopen($fileName,'w');
		fwrite($fh, $data);
		fclose($fh);
	}

	function appendFile($fileName,$message)
	{
		$message = $this->getCurrTime().' ## '.$message."\n";
		$myFile = $fileName;
		$fh = fopen($myFile,'a');
		fwrite($fh, $message);
		fclose($fh);
	}

	function updateLog($message)
	{
		$message = $this->getCurrTime().' ## '.$message."\n";
		$this->appendFile($this->logFile,$message);
		$this->writeToFile($this->currentLog,$message);
	}

	function errorLog($message)
	{
		$message = $this->getCurrTime().' ## '.$message." ## $this->errorURL \n";
		$this->appendFile($this->errorLog,$message);
	}

	function htmlEntityToHtml($content)
	{
		$content = html_entity_decode($content, ENT_QUOTES, "UTF-8");
		$content = htmlspecialchars_decode($content, ENT_QUOTES);
		return $content;
	}

	function getCurrTime()
	{
		date_default_timezone_set("Asia/Calcutta"); // Deafault Indian Time
		$dt	 = date( "d");
		$mt	 = date( "m");
		$yr  = date( "Y");
		$hr  = date( "h");
		$min = date( "i");
		$sec = date( "s");
		$currentTime = $yr.'-'.$mt.'-'.$dt.' '.$hr.':'.$min.':'.$sec;
		return $currentTime;
	}
	function getimagesource($finalimageUrl)
	{
		$getResult=$this->get2($finalimageUrl,'');
		$getResult=base64_encode($getResult);
		return $getResult;

	}
	function parseFormData($resultFile)
	{
		$formData = array();
		$pattern='/<(input|select)(.*?)>/is';
		while(preg_match($pattern,$resultFile,$matcher)) {
			$pattern_i='/<input/is';
			$pattern_s='/<select/is';
			if(preg_match($pattern_i,$matcher[0],$m)) {
				$resultFile=$this->after($matcher[0],$resultFile);
				$data=$matcher[2];
				$pattern_1='/type\W+(hidden|text|password|input)\W+/is';
				$pattern_2='/type\W+(checkbox|radio)\W+/is';
				if(preg_match($pattern_1,$data,$matcher_1)) {
					if(preg_match('/\s+name=[\'"]*(.*?)[\'"\s]/is',$data,$matcher_1)) {
						$name=$matcher_1[1];
						if($name!='') {
							$value="";
							if(preg_match('/\s+value\s*=\s*"(.*?)"/',$data,$matcher_1)) {
								$value=$matcher_1[1];
							}elseif(preg_match('/\s+value\s*=\s*\'(.*?)\'/',$data,$matcher_1)) {
								$value=$matcher_1[1];
							}
							if(isset($formData[$name])) {
								$formData[$name]=$formData[$name]."&".$name."=".$value;
								$name="";
							}else {
								$formData[$name]=$value;
							}
						}
					}
				}elseif(preg_match($pattern_2,$data)) {
					if(preg_match('/\s+name=[\'"]*(.*?)[\'"\s]/is',$data,$matcher_2)) {
						$name = $matcher_2[1];
						if($name!='') {
							if(preg_match('/checked/is',$data))	{
								$formData[$name]="on";
							}
						}
						$name='';
					}
				}
			}elseif(preg_match($pattern_s,$matcher[0],$m)) {
				$pattern_select='/<select.*?>(.*?)<\/select/is';
				if(preg_match($pattern_select,$resultFile,$matcher)) {
					$resultFile=$this->after($matcher[0],$resultFile);
					$data=$matcher[0];
					if(preg_match('/\s+name="(.*?)"/is',$data,$matcher_1)) {
						$name=$matcher_1[1];
						if($name!='') {
							$value="";
							$pattern_1='/.*(<option.*selected.*?>)/is';
							if(preg_match($pattern_1,$data,$matcher_1)) {
								$temp=$matcher_1[1];
								if(preg_match('/\s+value\s*=\s*"(.*?)"/',$temp,$matcher_1)) {
									$value=$matcher_1[1];
								}elseif(preg_match('/\s+value\s*=\s*\'(.*?)\'/',$temp,$matcher_1)) {
									$value=$matcher_1[1];
								}
							}elseif(preg_match('/\s+value\s*=\s*"(.*?)"/',$data,$matcher_1)) {
								$value=$matcher_1[1];
							}elseif(preg_match('/\s+value=\'(.*?)\'/',$data,$matcher_1)) {
								$value=$matcher_1[1];
							}
							if(isset($formData[$name])) {
								$formData[$name]=$formData[$name]."&".$name."=".$value;
								$name="";
							}else{
								$formData[$name]=$value;
							}
						}
					}
				}
			}
		}
		return $formData;
	}

	function getContent($formData)
	{
		$content = '';
		foreach($formData as $key=>$value) {
			$content.="&".$key."=".$value;
		}
		$content = preg_replace('/^&/','',$content);
		$content = $this->replace_content_hex($content);
		return $content;
	}

	function replace_content_hex($content)
	{
		$content=preg_replace('/^\&/is',"",$content);
		$content=preg_replace('/\+/is',"%2B",$content);
		$content=preg_replace('/\s/is',"+",$content);
		$content=preg_replace('/:/is',"%3A",$content);
		$content=preg_replace('/\$/is',"%24",$content);
		$content=preg_replace('/\//is',"%2F",$content);
		$content=preg_replace('/\(/is',"%28",$content);
		$content=preg_replace('/\)/is',"%29",$content);
		$content=preg_replace('/\[/is',"%5B",$content);
		$content=preg_replace('/\]/is',"%5D",$content);
		$content=preg_replace('/\'/is',"%27",$content);
		$content=preg_replace('/\,/is',"%2C",$content);
		$content=preg_replace('/\{/is',"%7B",$content);
		$content=preg_replace('/\}/is',"%7D",$content);
		$content=preg_replace('/\|/is',"%7C",$content);
		return ($content);
	}


	//-------------------------------proxyCode-----------------------
	function getPage($url,$content1,$referer='')
	{
		$this->errorURL=$url;
		$resultHtmlPage='';
		if($this->proxyStatus != 0)
		{
			$this->configureProxy();
		}
		if($content1 == '')
		{
			$resultHtmlPage=$this->get($url,'');
		}
		else
		{
			$resultHtmlPage=$this->post($url,$content1,'');
		}
		return $resultHtmlPage;
	}

	function checkBlock($resultFile)
	{
		if(preg_match('/You\s+don\'t\s+have\s+permission\s+to\s+access\s+this\s+server/is',$resultFile)){
			$this->proxyCount=0;
			return 1;
		}
		elseif(preg_match('/>\s*407\s+Proxy\s+Authentication\s+Required\s*</is',$resultFile)){
			$this->proxyCount=0;
			return 1;
		}
		elseif(preg_match('/>\s*502\s+Bad\s+Gateway\s*</is',$resultFile)){
			$this->proxyCount=0;
			return 1;
		}
		elseif (preg_match('/>\s*403\s*Forbidden\s*</is',$resultFile)) {
			$this->proxyCount=0;
			return 1;
		}
		elseif( (strlen(trim($resultFile))==0) or (strlen(trim($resultFile))==1)){
			$this->appendFile($this->proxyLog,"Zero response.");
			$this->proxyCount=0;
			return 1;
		}
		else
		{
			return 0;
		}
	}

	function retryPage($url,$content,$referer='')
	{
		$tt=rand(10,20);
		sleep($tt);
		$i=0;
		while($i<=5){
			$resultFile=$this->getPage($url,$content,$referer);
			 $pageStatus=$this->checkBlock($resultFile);
			 if($pageStatus==0){
				 return $resultFile;
			 }
			 $i++;
		}
		$pageStatus=$this->checkBlock($resultFile);
		if($pageStatus==0){
			 return $resultFile;
		}
		else
		{
			$this->appendFile($this->proxyLog,"Server has blocked the script.");
		}
	}

	function getandcheckPage($url,$content,$referer='')
	{
		$resultFile=$this->getPage($url,$content,$referer);
		$retry=$this->checkBlock($resultFile);
		$resultFile1='';
		if($retry==1){
			$resultFile1=$this->retryPage($url,$content,$referer);
		}
		else{
			$resultFile1=$resultFile;
		}
		return $resultFile1;
	}

	function checkProxy()
	{
		$checkStatus='';
		if (file_exists($this->proxyFile)){
			$checkStatus=$this->readFile1($this->proxyFile);
			$checkStatus=trim($checkStatus, "\n");
		}
		if($checkStatus == ''){
			return 0;
		}
		else{
			$this->proxyArr = explode("\n",$checkStatus);
			$length=count($this->proxyArr);
			if($length>1){
				return 1;
			}
		}
	}

	function configureProxy()
	{
		$proxyIP='';
		$proxyPort='';
		$proxyUser='';
		$proxyPwd='';
		$length=count($this->proxyArr);
		if($length>=1){
			if($this->proxyCount == 0){
				$this->websiteCurrHits=0;
				//$i=rand(1,$length);
				//if error that undefined offset then use
				$i=rand(1,$length-1);
				$this->proxyDetail=$this->proxyArr[$i];
				while(array_key_exists($this->proxyDetail,$this->proxyDoneArr))
				{
					//$i=rand(1,$length);
					//if error that undefined offset then use
					$i=rand(1,$length-1);
					$this->proxyDetail=$this->proxyArr[$i];
					$this->proxyDetail = trim($this->proxyDetail);
				}
				$proxyInfo = explode(',',$this->proxyDetail);
				$length1=count($proxyInfo);
				if($length1>=2){
					$proxyInfo[0] = preg_replace('/^\s+|\s+$/s','',$proxyInfo[0]);
					$proxyInfo[1] = preg_replace('/^\s+|\s+$/s','',$proxyInfo[1]);
					$proxyInfo[2] = preg_replace('/^\s+|\s+$/s','',$proxyInfo[2]);
					$proxyInfo[3] = preg_replace('/^\s+|\s+$/s','',$proxyInfo[3]);
					if(preg_match('/(\d+\.\d+\.\d+\.\d+)/is',$proxyInfo[0],$arr)){
						$proxyIP=$arr[1];
						if(preg_match('/(\d+)/is',$proxyInfo[1],$arr)){
							$proxyPort=$arr[1];
						}
						if($proxyInfo[2] != ''){
							$proxyUser=$proxyInfo[2];
						}
						if($proxyInfo[3] != ''){
							$proxyPwd=$proxyInfo[3];
						}
						if(($proxyIP!='') && ($proxyPort!='') && ($proxyUser!='') && ($proxyPwd!='')){
							$this->curl->proxyIP=$proxyIP;
							$this->curl->proxyPort=$proxyPort;
							$this->curl->proxyType='HTTP';
							$this->curl->proxyUserPwd=$proxyUser.':'.$proxyPwd;
							$this->setCurlProxy('http',$proxyIP,$proxyPort, $proxyUser, $proxyPwd );
						}
						elseif(($proxyPort!='') && ($proxyIP!='')){
							$this->curl->proxyIP=$proxyIP;
							$this->curl->proxyPort=$proxyPort;
							$this->curl->proxyType='HTTP';
							$this->setCurlProxy('http',$proxyIP,$proxyPort, '', '' );
						}
						$tmp = $proxyIP.','.$proxyPort.','.$proxyUser.','.$proxyPwd;
						$this->proxyDoneArr[$tmp]='Done';
						$length1 = count($this->proxyArr);
						$length2 = count($this->proxyDoneArr);
						if($length1==$length2){
							foreach($this->proxyDoneArr as $ky=>$val){
								unset($this->proxyDoneArr[$ky]);
							}
						}
					}
				}
				$this->proxyCount++;
				return 1;
			}
			elseif($this->proxyCount>=$this->proxyMaxHits)
			{
				$this->proxyCount=0;
				$this->websiteCurrHits=0;
			}
			else
			{
				$this->proxyCount++;
				$this->websiteCurrHits++;
			}
		}
	}

	function readFile1($myFile)
	{
		$fh = fopen($myFile, 'r');
		$theData = fread($fh, filesize($myFile));
		fclose($fh);
		return  $theData;
	}

	function setCurlProxy($type='http', $proxyIP=null, $proxyPort=80, $user=null, $pwd=null )
	{
		if(!empty($proxyIP)) {
			$this->curl->proxyIP=$proxyIP;
		} else {
			return 0;
		}
		$this->curl->proxyPort=$proxyPort;
		if(strtolower(trim($type))=='http') { $this->curl->proxyType='HTTP'; }
		elseif(strtolower(trim($type))=='socks4') { $this->curl->proxyType='SOCKS4'; }
		elseif(strtolower(trim($type))=='socks5') { $this->curl->proxyType='SOCKS5'; }
		if(!empty($user) && !empty($pwd)) { $this->curl->proxyUserPwd = "$user:$pwd"; }
	}
	//-----------------------endProxyCode--------------------------------------------

}
?>