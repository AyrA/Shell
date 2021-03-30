<?php
	/*
		HTTP related functions
	*/

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
