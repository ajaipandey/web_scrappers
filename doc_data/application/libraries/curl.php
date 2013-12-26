<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class curl
{
	var $headers;
	var $user_agent;
	var $compression;
	var $cookie_file;
	var $proxyIP;
	var $proxyPort;
	var $proxyType;
	var $proxyUserPwd;

	function __construct($cookie='selfCook.txt')
	{
		parent::__construct();
		$this->user_agent = "Mozilla/5.0 (Windows NT 6.1; rv:27.0) Gecko/20100101 Firefox/27.0";
		if(in_array(strtolower(PHP_OS), array("win32", "windows", "winnt"))) $cookie=getcwd().'\\'.$cookie;
		else $cookie=getcwd().'/'.$cookie;
		$this->setDefaultHeaders();
		$this->cookie_file=$cookie;
		$this->createCookie();
	}

	function createCookie()
	{
		if(!file_exists($this->cookie_file)) {
			$fp=fopen($this->cookie_file,'w') or $this->error("The cookie file could not be opened. Make sure this directory has the correct permissions");
			fclose($fp);
		}
	}

	function setDefaultHeaders()
	{
		$this->headers[] = "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8";
		$this->headers[] = "Accept-Language:	en-us,en;q=0.5";
		$this->headers[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
		$this->headers[] = "Connection: Keep-Alive";
	}

	function get($url,$refer=''){
		$cookie='';
		if(file_exists($this->cookie_file))
		{
			$cookie=$this->getCookieStr();
			$cookie=trim($cookie);
			if($cookie != '') $cookie.='; ';
		}
		$options = array(
			CURLOPT_RETURNTRANSFER => true,         		// return web page
			CURLOPT_HTTPHEADER     => $this->headers,       // customise header request
			CURLOPT_HEADER         => true,				    // don't return headers
			CURLINFO_HEADER_OUT    => true,					// return request headers
			CURLOPT_REFERER        => $refer,         		// follow redirects
			CURLOPT_USERAGENT      => $this->user_agent,    // who am i
			CURLOPT_COOKIE		   => $cookie,				// who am i
			CURLOPT_COOKIEFILE     => $this->cookie_file,   // who am i
			CURLOPT_COOKIEJAR      => $this->cookie_file,   // who am i
			CURLOPT_AUTOREFERER    => true,         		// set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,          		// timeout on connect
			CURLOPT_TIMEOUT        => 240,          		// timeout on response
			CURLOPT_MAXREDIRS      => 30,           		// stop after 10 redirects
			CURLOPT_SSL_VERIFYHOST => false,            	// don't verify ssl
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_VERBOSE        => 1
		);
		if (isset($this->proxyIP))
		{
			$options[CURLOPT_PROXY] = $this->proxyIP;
			if(strtolower($this->proxyType)=='socks4') { $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS4; };
			if(strtolower($this->proxyType)=='socks5') { $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5; };
		};
		if (isset($this->proxyIP) and isset($this->proxyPort)) { $options[CURLOPT_PROXYPORT] = $this->proxyPort; };
		//if ($this->proxyUserPwd) { $options[CURLOPT_PROXYUSERPWD] = $this->proxyUserPwd; };
		//-------------
		$ch = curl_init($url);
		curl_setopt_array($ch,$options);
		$content = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($http_code == 301 || $http_code == 302 || $http_code == 303){
			$last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
			curl_close($ch);
			if(preg_match("/Location:\s*(.*?)(\n|\s+)/is", $content, $match)){
				$path = trim($match[1]);
				if(!preg_match('/http:/is', $path)){
					$path = $last_url['scheme'].'://'.$last_url['host'].$path;
				}
				return $this->get($path);
			}else{
				return $content;
			}
		}
		else{
			curl_close($ch);
			return $content;
		}
	}
	function get2($url,$refer=''){
		$cookie='';
		if(file_exists($this->cookie_file))
		{
			$cookie=$this->getCookieStr();
			$cookie=trim($cookie);
			if($cookie != '') $cookie.='; ';
		}
		$options = array(
			CURLOPT_RETURNTRANSFER => true,         		// return web page
			CURLOPT_HTTPHEADER     => $this->headers,       // customise header request
			CURLOPT_HEADER         => false,				// don't return headers
			CURLINFO_HEADER_OUT    => true,					// return request headers
			CURLOPT_REFERER        => $refer,         		// follow redirects
			CURLOPT_USERAGENT      => $this->user_agent,    // who am i
			CURLOPT_COOKIE		   => $cookie,				// who am i
			CURLOPT_COOKIEFILE     => $this->cookie_file,   // who am i
			CURLOPT_COOKIEJAR      => $this->cookie_file,   // who am i
			CURLOPT_AUTOREFERER    => true,         		// set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,          		// timeout on connect
			CURLOPT_TIMEOUT        => 240,          		// timeout on response
			CURLOPT_MAXREDIRS      => 30,           		// stop after 10 redirects
			CURLOPT_SSL_VERIFYHOST => false,            	// don't verify ssl
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_VERBOSE        => 1
		);
		if (isset($this->proxyIP))
		{
			$options[CURLOPT_PROXY] = $this->proxyIP;
			if(strtolower($this->proxyType)=='socks4') { $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS4; };
			if(strtolower($this->proxyType)=='socks5') { $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5; };
		};
		if (isset($this->proxyIP) and isset($this->proxyPort)) { $options[CURLOPT_PROXYPORT] = $this->proxyPort; };
		//if ($this->proxyUserPwd) { $options[CURLOPT_PROXYUSERPWD] = $this->proxyUserPwd; };
		//-------------
		$ch = curl_init($url);
		curl_setopt_array($ch,$options);
		$content = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($http_code == 301 || $http_code == 302 || $http_code == 303){
			$last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
			curl_close($ch);
			if(preg_match("/Location:\s*(.*?)(\n|\s+)/is", $content, $match)){
				$path = trim($match[1]);
				if(!preg_match('/http:/is', $path)){
					$path = $last_url['scheme'].'://'.$last_url['host'].$path;
				}
				return $this->get($path);
			}else{
				return $content;
			}
		}
		else{
			curl_close($ch);
			return $content;
		}
	}

	function post($url,$data='',$refer='')
	{
		$cookie='';
		if(file_exists($this->cookie_file))
		{
			$cookie=$this->getCookieStr();
			$cookie=trim($cookie);
			if($cookie != '') $cookie.='; ';
		}

		$options = array(
			CURLOPT_RETURNTRANSFER => true,         // return web page
			CURLOPT_HTTPHEADER     => $this->headers,       // customise header request
			CURLOPT_HEADER         => true,       // don't return headers
			CURLINFO_HEADER_OUT         => true,       // return request headers
			CURLOPT_REFERER => $refer,         // follow redirects
			CURLOPT_USERAGENT      => $this->user_agent,     // who am i
			CURLOPT_COOKIE			=> $cookie,     // who am i
			CURLOPT_COOKIEFILE      => $this->cookie_file,     // who am i
			CURLOPT_COOKIEJAR      => $this->cookie_file,     // who am i
			CURLOPT_AUTOREFERER    => true,         // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,          // timeout on connect
			CURLOPT_TIMEOUT        => 360,          // timeout on response
			CURLOPT_MAXREDIRS      => 30,           // stop after 10 redirects
			CURLOPT_POST            => 1,            // i am sending post data
			CURLOPT_POSTFIELDS     => $data,    // this are my post vars
			CURLOPT_SSL_VERIFYHOST => false,            // don't verify ssl
			CURLOPT_SSL_VERIFYPEER => false,        //
			CURLOPT_VERBOSE        => 1                //
		);

		if (isset($this->proxyIP))
		{
			$options[CURLOPT_PROXY] = $this->proxyIP;
			if(strtolower($this->proxyType)=='socks4') { $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS4; };
			if(strtolower($this->proxyType)=='socks5') { $options[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5; };
		}
		if (isset($this->proxyIP) and isset($this->proxyPort)) { $options[CURLOPT_PROXYPORT] = $this->proxyPort; };
		//if ($this->proxyUserPwd) { $options[CURLOPT_PROXYUSERPWD] = $this->proxyUserPwd; };
		$ch = curl_init($url);
		curl_setopt_array($ch,$options);
		$content = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($http_code == 301 || $http_code == 302 || $http_code == 303){
			$last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
			curl_close($ch);
			if(preg_match("/Location:\s*(.*?)(\n|\s+)/is", $content, $match)){
				$path = trim($match[1]);
				if(!preg_match('/http:/is', $path)){
					$path = $last_url['scheme'].'://'.$last_url['host'].$path;
				}
				return $this->get($path);
			}else{
				return $content;
			}
		}
		else{
			curl_close($ch);
			return $content;
		}
	}


	function error($error) {
		echo "<center><div style='width:500px;border: 3px solid #FFEEFF; padding: 3px; background-color: #FFDDFF;font-family: verdana; font-size: 10px'><b>cURL Error</b><br>$error</div></center>";
		die;
	}

	function getCookieStr() {
		$cookies=$this->extractCookies(file_get_contents($this->cookie_file));
		$len=count($cookies);
		$str=array();
		if($len>0)
		{
			for ($i=0;$i<$len;$i++)
			{
				$str[]=$cookies[$i]['name'].'='.$cookies[$i]['value'];
			}
			$str = implode('; ', $str);
			return $str;
		}
	}

	function getCookies() {
		return $this->extractCookies(file_get_contents($this->cookie_file));
	}

	function extractCookies($string) {
		$cookies = array();
		$lines = explode("\n", $string);
		foreach ($lines as $line) {
			if (isset($line[0]) && substr_count($line, "\t") == 6) {
				$tokens = explode("\t", $line);
				$tokens = array_map('trim', $tokens);
				$cookie = array();
				$cookie['domain'] = $tokens[0];
				$cookie['flag'] = $tokens[1];
				$cookie['path'] = $tokens[2];
				$cookie['secure'] = $tokens[3];
				$cookie['expiration'] = date('Y-m-d h:i:s', $tokens[4]);
				$cookie['name'] = $tokens[5];
				$cookie['value'] = $tokens[6];
				$cookies[] = $cookie;
			}
		}
		return $cookies;
	}
}
?>