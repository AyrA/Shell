<?php
	/*
		Contains various utility functions and constants
	*/

	//Date format for HTTP headers
	define('HTTP_DATE','D, d M Y H:i:s T');
	//The date format for whenever a date is being displayed
	define('USER_DATE','Y-m-d H:i:s T');
	//Maximum file size to be edited. The default is 100 kb
	define('MAX_FILE_EDIT',1e5);
	//Maximum file size to calculate sha1 of. The default is 100 mb
	define('MAX_HASH_SIZE',1e8);

	//MIME type for a GZ file
	define('MIME_GZ','application/gzip');
	define('MIME_ZIP','application/zip');
	
	//User agents to be blocked.
	//By default, the shell will not allow access if the user agent matches one of these.
	//The values need to be a valid regex.
	//It will be used in the format (a|b|c|d|e|f|...) by joining with '|'
	//It's treated case insensitive
	define('ROBOT_UA',array(
		'petalbot','barkrowler','intelx\.io','gdnplus',
		'screaming\ frog','alphabot','netestate',
		'adsbot','scanner','surdotlybot','datanyze','netpeakchecker',
		'ahrefsbot','MJ12bot','mediatoolkitbot','semrushbot','spbot','aspiegel',
		'facebot','snapchat','discord','google','bingbot',
		'msnbot','yandex','qwantify','seznam','facebook',
		'ltx71','megaindex','netcraftsurvey','awariosmartbot',
		'censysinspect','zgrab','rogerbot','dotbot','blexbot','ezooms','sistrix',
		'twitter','curl','wget','seo','node\-fetch',
		'python\-requests','Python\-urllib',
		'go\-http\-client'));

	$resources=array();
	//Config cache
	$_config=NULL;
	//Theme cache
	$_themes=NULL;

	//Dumps an object to the page
	function dump($obj,&$dumped=NULL){
		if(!is_array($dumped)){
			$dumped=array();
		}
		if($obj===NULL){
			return he('<null>');
		}
		elseif(is_string($obj)){
			return he($obj);
		}
		elseif(is_float($obj) || is_int($obj)){
			return he(number_format($obj,is_int($obj)?0:4,'.','\''));
		}
		elseif(is_bool($obj)){
			return he($obj?'true':'false');
		}
		elseif(is_resource($obj)){
			return he('<resource>');
		}
		elseif(is_array($obj)){
			if(count($obj)===0){
				return he('<empty array>');
			}
			elseif(in_array($obj,$dumped,TRUE)){
				return he('<recursive array entry>');
			}
			else{
				$buffer='<table><tr><th colspan="3">Array</th></tr>' .
					'<tr><th>Key</th><th>Type</th><th>Value</th></tr>';
				foreach($obj as $k=>$v){
					$buffer.='<tr><td>' . he($k) . '</td>' .
						'<td>' . he(gettype($v)) .
						(is_string($v)?'(' . strlen($v) . ')':'') .
						'</td>' .
						'<td>' . dump($v,$dumped) . '</td></tr>';
				}
				$buffer.='</table>';
				return $buffer;
			}
		}
		else{
			return he('<Unknown type: ' . gettype($obj) . '>');
		}
	}

	//Gets a value ($v) from an array ($a).
	//Returns $d instead if $v is not found or $a is not an array at all
	function av($a,$k,$d=NULL){
		if(is_array($a) && isset($a[$k])){
			return $a[$k];
		}
		return $d;
	}

	//Converts a few string expressions (like yes/no, on/off, true/false) into boolean
	function asBool($x){
		if(is_string($x)){
			$x=trim(strtolower($x));
			switch($x){
				case 'no':
				case 'off':
				case 'false':
				case '':
				case '0':
					return FALSE;
				default:
					return TRUE;
			}
		}
		return !!$x;
	}

	//Checks if the page was loaded over HTTPS
	//Might report FALSE even if HTTPS is being used when a reverse proxy is in use
	function isHTTPS(){
		return strtolower(av($_SERVER,'HTTPS',av($_SERVER,'https','off')))==='on';
	}

	//Detects common bots and tools
	function isRobot(){
		$ua=av($_SERVER,'HTTP_USER_AGENT','');
		return $ua==='' || preg_match('#(' . implode('|',ROBOT_UA) . ')#i',$ua);
	}

	//Checks if the given directory is empty
	function emptydir($dir){
		if(is_dir($dir)){
			$contents=@scandir($dir);
			if(is_array($contents)){
				if(count($contents)>2){
					//No need to scan if too many entries found
					return FALSE;
				}
				foreach($contents as $e){
					if($e!=='.' && $e!=='..'){
						//Found meaningful entry
						return FALSE;
					}
				}
				//Directory empty
				return TRUE;
			}
			//Can't scan, assume not empty
			return FALSE;
		}
		//Not a directory
		return NULL;
	}

	//Recursively removes a directory
	function rdrec($path){
		$contents=@scandir($path);
		if(is_array($contents)){
			foreach($contents as $entry){
				if($entry!=='..' && $entry!=='.'){
					$e=realpath($path . DIRECTORY_SEPARATOR . $entry);
					if(is_file($e) || is_link($e)){
						@unlink($e);
					}
					elseif(is_dir($e)){
						rdrec($e);
					}
				}
			}
			@rmdir($path);
		}
	}

	//Recursively calculate directory size
	function dirsize($path){
		$size=0;
		$contents=@scandir($path);
		if(is_array($contents)){
			foreach($contents as $entry){
				if($entry!=='..' && $entry!=='.'){
					$e=realpath($path . DIRECTORY_SEPARATOR . $entry);
					if(is_file($e)){
						$size+=@filesize($e);
					}
					elseif(is_dir($e)){
						$size+=dirsize($e);
					}
				}
			}
		}
		return $size;
	}

	//recursively copy a directory
	function dircopy($src,$dst) {
		$dirsep=strpos($dst,'://')===FALSE?DIRECTORY_SEPARATOR:'/';
		$ret=TRUE;
		$dir=@opendir($src);
		if($dir){
			$ret&=(@file_exists($dst) && @is_dir($dst)) || @mkdir($dst);
			if($ret){
				while(FALSE!==($file=readdir($dir))){
					if(($file!=='.') && ($file!=='..')){
						if(is_dir($src . $dirsep . $file)){
							$ret&=dircopy($src . $dirsep . $file,$dst . $dirsep . $file);
						}
						else{
							$ret&=@copy($src . $dirsep . $file,$dst . $dirsep . $file);
						}
					}
				}
			}
			closedir($dir);
		}
		return $ret;
	}

	//Gets a name that doesn't collides with existing names
	function freeName($name){
		if(!file_exists($name)){
			return $name;
		}
		$count=0;
		$base=$name;
		$name.='.' . $count;
		do{
			$name=$base . '.' . ++$count;
		}while(file_exists($name));
		return $name;
	}

	//Formats a date for user display
	function userDate($x){
		if($x===FALSE){
			return '?';
		}
		return gmdate(USER_DATE,$x);
	}

	//Computes a hmac
	function hmac($value){
		$config=getConfig();
		return hash_hmac('sha1',$value,av($config,'password',sha1(__FILE__)));
	}

	//Checks if the authentication cookie is set and has the correct value (fancy saying of "Checking for valid session")
	function isAuth(){
		$config=getConfig();
		$hash=av($config,'password');
		$value=av($_COOKIE,'C-' . av($config,'salt',''));
		if(!$value || !$hash){
			return FALSE;
		}
		return hmac($hash)===$value;
	}

	//Sets the authentication cookie (fancy saying of "Login")
	function setAuth(){
		$config=getConfig();
		$value=av($config,'password');
		$salt=av($config,'salt');
		if(!$value || !$salt){
			exit('Tried to set session cookie before configuration was present');
		}
		//Update superglobal to use the authentication state in the current request.
		$_COOKIE["C-$salt"]=hmac($value);
		return setcookie("C-$salt",hmac($value),0,'','',isHTTPS(),TRUE);
	}

	//Clears the authentication cookie (fancy saying for "Logout")
	function clearAuth(){
		$config=getConfig();
		$salt=av($config,'salt');
		if(!$salt){
			return TRUE;
		}
		//Clear all site data for this page
		setHeader('Clear-Site-Data','*');
		//Update superglobal to reflect cleared session
		unset($_COOKIE["C-$salt"]);
		//Make cookie expire in the year 2000
		return setcookie("C-$salt",'CLEAR',946684800,'','',isHTTPS(),TRUE);
	}

	//Compute and send CSP header
	function csp(){
		$css=base64_encode(hash('sha256',file_get_contents('res/css.css'),TRUE));
		$js=base64_encode(hash('sha256',file_get_contents('res/js.js'),TRUE));
		//Setting a strict CSP header prevents the server from injecting stuff unless the header is modified by it.
		setHeader('Content-Security-Policy',"default-src 'none'; img-src 'self'; media-src 'self'; style-src 'sha256-$css';script-src 'sha256-$js';");
	}

	//Formats sizes with units
	//$x(int):               Size in bytes
	//$SI(bool=FALSE):       Use SI units (1000) instead of binary (1024)
	//$FixUnits(bool=FALSE): If $SI is false, use proper units (KiB) instead of SI units (KB)
	function formatSize($x,$SI=FALSE,$FixUnits=TRUE){
		if (+$x != +$x) {
			return null;
		}
		//1024: What software does
		//1000: What HDD manufacturers do
		$factor=$SI?1000:1024;
		$FixUnits=!$SI && $FixUnits;
		//Supported units. Binary factors should use 'i' but it's common to not do that. It's off by default here too
		$units=explode(',',$FixUnits?'B,KiB,MiB,GiB,TiB,EiB,YiB':'B,KB,MB,GB,TB,EB,YB');
		$index=0;
		//Reduce size by the given factor
		while(abs($x)>=$factor && $index<count($units)-1){
			$x/=$factor;
			++$index;
		}
		return implode(' ',array(round($x,2),$units[$index]));
	}

	//Shortens a file name if needed but tries to preserve the extension unless it's too long
	function formatName($name,$ext=TRUE,$length=40){
		$len=strlen($name);
		if($len<=$length){
			return $name;
		}
		$dot=$ext?strrpos($name,'.'):FALSE;
		if($dot===FALSE || $len-$dot>$length-10){
			return substr($name,0,$length) . '(...)';
		}
		$ext=substr($name,$dot);
		return substr($name,0,min($length,$dot-strlen($ext))) . '(...)' . $ext;
	}

	//Formats data as a hex dump of the given length
	function formatHex($data,$size=16){
		$ret=array();
		$data=str_split($data,$size);
		$offset=0;
		foreach($data as $k=>$v){
			$row='0x' . strtoupper(substr('00000000' . dechex($offset),-8)) . ":\t";
			$hex=array();
			$text='';
			foreach(str_split($v) as $c){
				$id=ord($c);
				$hex[]=substr('0' . dechex($id),-2);
				//0xF0 and 0xFF have special meaning in CP850
				$text.=$id>0x1F && $id!==0xF0 && $id!==0xFF?$c:'.';
			}
			while(count($hex)<$size){$hex[]='  ';}
			$row.=implode(' ',$hex) . "\t" . mb_convert_encoding($text,'UTF-8','CP850');
			$ret[]=$row;
			$offset+=strlen($v);
		}
		return implode(PHP_EOL,$ret);
	}

	//Detects the encoding (UTF-16 and UTF-8 only)
	function detectEncoding($data){
		if(strlen($data)>1){
			//UTF-16
			if(
				(ord($data)===0xFF && ord(substr($data,1,1))===0xFE) ||
				(ord($data)===0xFE && ord(substr($data,1,1))===0xFF)){
				return 'UTF-16';
			}
			//UTF-8
			if(strlen($data)>2 &&
				ord(substr($data,0,1))===0xEF &&
				ord(substr($data,1,1))===0xBB &&
				ord(substr($data,2,1))===0xBF){
				return 'UTF-8';
			}
		}
		$enc=strlen($data)===0?'UTF-8':mb_detect_encoding($data);
		return is_string($enc)?$enc:'Unknown or binary';
	}

	//Converts text into UTF-8
	//Note: Don't use unless you believe the content is actually text
	function decodeText($data){
		$enc=detectEncoding($data);
		if($enc==='UTF-8'){
			return $data;
		}
		if($enc==='UTF-16'){
			return mb_convert_encoding($data,'UTF-8','UTF-16');
		}
		//Guess source type
		return mb_convert_encoding($data,'UTF-8');
	}

	//Checks if the OS is Windows
	function isWindows(){
		return PHP_OS==='WINNT';
	}

	//Runs the given command and returns stderr and stdout
	function run($cmd,$dir=NULL,$stdin=NULL){
		set_time_limit(300);
		$config=getConfig();
		//"easy" mode enabled
		if(av($config,'stdoutonly')){
			return array('stdout'=>shell_exec($cmd),'stderr'=>'');
		}

		//0=STDIN,1=STDOUT,2=STDERR
		$io=array(
			0=>array('pipe','r'),
			1=>array('pipe','w'),
			2=>array('pipe','w')
		);
		if(is_resource($proc=@proc_open($cmd,$io,$pipes,$dir))){
			if($stdin){
				fwrite($pipes[0],"$stdin\r\n");
			}
			fclose($pipes[0]);
			$out=trim(stream_get_contents($pipes[1]));
			$err=trim(stream_get_contents($pipes[2]));
			fclose($pipes[1]);
			fclose($pipes[2]);
			proc_close($proc);
			return array('stdout'=>$out,'stderr'=>$err);
		}
		return NULL;
	}

	//Reads the current configuration
	function getConfig(){
		global $_config;
		if($_config){
			return $_config;
		}
		if($fp=@fopen(CONFIG_FILE,'r')){
			$line=fgets($fp);
			$line=json_decode(substr(strstr($line,'//'),2),TRUE);
			fclose($fp);
			if(is_array($line)){
				return $_config=$line;
			}
		}
		return array('password'=>NULL,'salt'=>bin2hex(openssl_random_pseudo_bytes(20)));
	}

	//Saves the given configuration
	function setConfig($config){
		global $_config;
		$_config=NULL;
		$config=json_encode($config);
		$lines=@file(CONFIG_FILE,FILE_IGNORE_NEW_LINES);
		if(!is_array($lines)){
			$lines=array();
		}
		$lines[0]="<?php //$config";
		@file_put_contents(CONFIG_FILE,implode(PHP_EOL,$lines));
		return $config;
	}

	//Resets the configuration to the initial state
	function resetConfig(){
		return setConfig(NULL);
	}
	
	//Gets all themes
	function getThemes(){
		global $_themes;
		if(is_array($_themes)){
			return $_themes;
		}
		$themes=array(''=>array(
			//This is the name of the theme as set in the header
			'name'=>'None',
			//This is the description of the theme as set in the header
			'desc'=>'no theme. Very basic look',
			//Indicates whether this theme has a supporting JS file or not.
			'js'=>FALSE
		));
		foreach(glob('res/theme.*.css') as $file){
			preg_match('#theme\.(.+)\.css$#i',$file,$m);
			$filename=$m[1];
			if(preg_match('#/\\*(.+?)\\*/#s',file_get_contents($file),$m)){
				$data=json_decode(trim($m[1]),TRUE);
				if(is_array($data) && av($data,'name') && av($data,'desc')){
					$themes[$filename]=array(
						'file'=>$filename,
						'name'=>$data['name'],
						'desc'=>$data['desc'],
						'js'=>is_file("res/theme.$filename.js")
					);
				}
			}
			else{
				die('Theme' . $filename . ' does not contains a header');
			}
		}
		return $_themes=$themes;
	}

	//Obtains the script URL (without host name and protocol)
	function selfurl(){
		return av($_SERVER,'PHP_SELF',av($_SERVER,'SCRIPT_NAME'));
	}

	//Attempts to detect the root directory.
	function webroot(){
		$current=dirname(__FILE__);
		$url=dirname(selfurl());
		$count=0;
		//Cur off segments from both paths until the URL points to the root directory
		while($count<0xFF && $url!==DIRECTORY_SEPARATOR){
			$url=dirname($url);
			$current=dirname($current);
			++$count;
		}
		return $count===0xFF?NULL:$current;
	}

	//Tries to find the HTTP url for the given resource
	function weburl($p){
		$ssl=strtolower(av($_SERVER,'HTTPS','off'))==='on';
		$wr=webroot();
		$p=realpath($p);
		if($wr===NULL){
			return NULL;
		}
		if(is_dir($p)){
			$p.=DIRECTORY_SEPARATOR;
		}
		if(strpos($p,$wr . DIRECTORY_SEPARATOR)===0){
			//Split path into segments
			$p=explode('/',str_replace('\\','/',substr($p,strlen($wr))));
			//URL encode segments and combine back into path
			$p=implode('/',array_map('urlencode',$p));
			return 'http' . ($ssl?'s':'') . '://' . av($_SERVER,'HTTP_HOST','localhost') . $p;
		}
		return NULL;
	}

	//Waits for the given amount of time. The id functions as a queue to essentially serialize parallel calls.
	//Expects the temp directory to be writable
	function delay($id,$timeout){
		$name=sys_get_temp_dir() . DIRECTORY_SEPARATOR . hmac($id);
		$fp=fopen($name,'wb');
		if($fp){
			flock($fp,LOCK_EX);
			//Write something to the file
			fwrite($fp,sha1(hmac($id),TRUE));
			sleep($timeout);
			flock($fp,LOCK_UN);
			fclose($fp);
			//Delete the temporary file
			@unlink($name);
		}
		else{
			sleep($timeout);
		}
	}

	//Gets the user name the PHP process runs under
	function username(){
		//Make host prtion of windows user name uppercase
		if(preg_match('#^([^\\\\]+)\\\\(.+)$#i',$username=trim(`whoami`),$m)){
			$username=strtoupper($m[1]) . '\\' . $m[2];
		}
		return $username;
	}

	if(!defined('CONFIG_FILE')){
		setHeader('Content-Type','text/plain');
		fail('CONFIG_FILE is not set');
	}