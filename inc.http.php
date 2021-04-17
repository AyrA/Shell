<?php
	/*
		HTTP related functions
	*/

	//File with all known CA certificates
	define('CAFILE',__DIR__ . DIRECTORY_SEPARATOR . 'ca.pem');
	//Maximum age of the CA file before a new one is obtained.
	//CA do not change often. Defaults to 30 days.
	define('CACACHE',86400*30);

	//Set a header
	function setHeader($h,$v){
		header("$h: $v");
	}

	//Redirects the user and stops the script
	function redir($url){
		setHeader('Location',$url);
		exit(0);
	}

	//Downloads a file
	function downloadFile(){
		$path=realpath(av($_GET,'path','/'));
		if($path && is_file($path)){
			$type=mime_content_type($path);
			if(!is_string($type)){
				$type='application/octet-stream';
			}
			$filedate=filemtime($path);
			setHeader('Content-Type',$type);
			handleCache($filedate);
			setHeader('Content-Disposition','attachment; filename="' . basename($path) . '"');
			sendRange($path);
		}
	}

	//Handles caching with the modification date
	function handleCache($ts){
		setHeader('Last-Modified',gmdate(HTTP_DATE,$ts));
		$t=av($_SERVER,'HTTP_IF_MODIFIED_SINCE');
		if($t){
			$t=strtotime($t);
		}
		else{
			$t=FALSE;
		}
		if($t!==FALSE && $t>=$ts){
			header('HTTP/1.1 304 Not Modified',TRUE,304);
			die(0);
		}
		return $ts;
	}

	//Sends a partial HTTP response for a file (https://gist.github.com/codler/3906826)
	//$filename(string): File name to send as partial
	//$exit(bool=TRUE):  exit after sending file
	function sendRange($filename,$exit=TRUE){
		$size  =filesize($filename);// File size
		$length=$size;              // Content length
		$start =0;                  // Start byte
		$end   =$size-1;            // End byte

		setHeader('Accept-Ranges',"0-$length");

		if (isset($_SERVER['HTTP_RANGE'])){
			$c_start=$start;
			$c_end  =$end;
			list(,$range)=explode('=',$_SERVER['HTTP_RANGE'],2);
			//Invalid range
			if(strpos($range, ',')!==FALSE){
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				setHeader('Content-Range',"bytes $start-$end/$size");
				exit(1);
			}
			if ($range==='-'){
				//Get open ended range
				$c_start=$size-substr($range,1);
			}else{
				$range  =explode('-',$range);
				$c_start=$range[0];
				$c_end  =(isset($range[1]) && is_numeric($range[1]))?$range[1]:$size;
			}
			$c_end=($c_end>$end)?$end:$c_end;
			if ($c_start>$c_end || $c_start>$size-1 || $c_end>=$size) {
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				setHeader('Content-Range',"bytes $start-$end/$size");
				exit(1);
			}
			$start =$c_start;
			$end   =$c_end;
			$length=$end-$start+1;
			$fp=fopen($filename,'rb');
			if(!$fp){
				throw new Exception('Unable to open file for partial HTTP answer');
			}
			fseek($fp, $start);
			header('HTTP/1.1 206 Partial Content');
		}
		else{
			$fp=fopen($filename,'rb');
		}
		setHeader('Content-Range',"bytes $start-$end/$size");
		setHeader('Content-Length',$length);
		$buffer = 1024 * 8;
		while(!feof($fp) && ($p=ftell($fp))<=$end) {
			if ($p+$buffer>$end) {
				$buffer=$end-$p+1;
			}
			set_time_limit(5);
			echo fread($fp, $buffer);
			flush();
		}
		fclose($fp);
		if($exit){
			exit(0);
		}
	}

	//Gets a curl handle with default options already set
	function http_curl($url){
		$ch=curl_init($url);
		//Fail on HTTP error status codes (400 and greater)
		curl_setopt($ch,CURLOPT_FAILONERROR,TRUE);
		//Return server response instead of writing to stdout
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
		//Follow redirect attempts (at most 5)
		curl_setopt($ch,CURLOPT_FOLLOWLOCATION,TRUE);
		curl_setopt($ch,CURLOPT_MAXREDIRS,5);
		//Maximum 5 second TCP timeout and 5 second response timeout
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
		curl_setopt($ch,CURLOPT_TIMEOUT,5);
		//Custom user agent
		curl_setopt($ch,CURLOPT_USERAGENT,'AyrA-Shell/' . SHELL_VERSION . ' +https://github.com/AyrA/Shell');
		//Do certificate validation if the CA file exists
		if(is_file(CAFILE)){
			curl_setopt($ch,CURLOPT_CAINFO,CAFILE);
		}
		else{
			curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,0);
		}
		return $ch;
	}

	//Tries to obtain a new CA file if it's missing or stale
	function http_getca(){
		if(!is_file(CAFILE) || time()-filemtime(CAFILE)>CACACHE)
		{
			$ch=http_curl('https://cable.ayra.ch/ca/CA.pem');
			$ret=curl_exec($ch);
			//Don't write the file if there was an error or if the response was empty
			if(curl_errno($ch)===0 && $ret && strlen($ret)>0){
				file_put_contents(CAFILE,$ret);
			}
			curl_close($ch);
		}
		return is_file(CAFILE);
	}

	//Gets a HTTP resource and sends optional headers to it
	function http_get($url,$headers=NULL){
		$ret=array('success'=>FALSE,'url'=>$url,'headers'=>$headers);

		$ch=http_curl($url);
		//Try to obtain a CA list if the URL asks for encryption
		if(preg_match('#^(http|ftp)s:#i',$url)){
			$ret['ca']=http_getca();
		}

		if(is_array($headers)){
			$parsed_headers=array();
			foreach($headers as $k=>$v){
				$parsed_headers[]="$k: $v";
			}
			curl_setopt($ch,CURLOPT_HTTPHEADER,$parsed_headers);
		}
		$ret['response']=curl_exec($ch);
		$ret['errno']=curl_errno($ch);
		$ret['error']=curl_error($ch);
		$ret['success']=$ret['errno']===0;
		curl_close($ch);
		return $ret;
	}
