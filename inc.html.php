<?php
	/*
		Generic functions for rendering HTML pages.
	*/
	//HTML encoding in PHP has a stupidly long function name
	function he($x){
		$res=htmlspecialchars($x);
		//If the source string is not empty, but the return value is, the conversion failed
		//We then try to convert the text to UTF-8 and try again.
		//We also allow PHP to convert invalid code point sequences to the Unicode substitution character
		if(strlen($x)>0 && $res===''){
			//Encode using HTML5 standard if this mode is available
			$mode=defined('ENT_HTML5')?constant('ENT_HTML5'):ENT_HTML401;
			$res=htmlspecialchars(decodeText($x),ENT_COMPAT|ENT_SUBSTITUTE|ENT_HTML401|$mode);
		}
		return $res;
	}

	//Removes newlines from a string
	function stripnl($x){
		return preg_replace('#[\r\n]+#',' ',$x);
	}

	//Renders HTML output
	function html($x){
		$footer=array(
			'PHP'=>phpversion() . ' ' . (PHP_INT_SIZE*8) . ' bit',
			'API'=>php_sapi_name(),
			'OS'=>PHP_OS . ' [' . php_uname() . ']',
			'User'=>username()
		);

		foreach($footer as $k=>$v){
			$footerline[]="$k: $v";
		}

		$action=av($_GET,'action','main');
		if(!preg_match('#^\w+$#',$action)){
			$action='main';
		}

		echo '<!DOCTYPE html>
<html lang="en">
	<head>
		<link rel="stylesheet" type="text/css" href="res/css.css" />
		<title>Secure Shell</title>
		<meta name="robots" content="noindex, nofollow" />
	</head>
	<body><div class="action-' . he($action) . '">' . $x . '<div><p>' . he(implode(' | ',$footerline)) . '</p></div>
	<script src="res/js.js"></script></body>
</html>';
		return 0;
	}

	//Renders Tabs in HTML
	function showTabs($tabs,$before=NULL,$after=NULL){
		$buffer='<div class="tab-header">';
		$content='<div class="tab-content">';
		foreach(array_keys($tabs) as $i=>$v){
			$buffer.='<div id="tab-header-' . $i . '">' . he($v) . '</div>';
			$content.='<div id="tab-content-' . $i . '">' . $tabs[$v] . '</div>';
		}
		exit(html("$before $buffer</div>$content</div>$after"));
	}

	//Shows PHP information in tab form
	function showInfo(){
		$show=array(
			'General'=>INFO_GENERAL,
			'Configuration'=>INFO_CONFIGURATION,
			'Modules'=>INFO_MODULES,
			'Environment'=>INFO_ENVIRONMENT,
			'Variables'=>INFO_VARIABLES);
		$tabs=array();
		foreach($show as $k=>$type){
			ob_start();
			phpinfo($type);
			$raw=ob_get_contents();
			$in=stripos($raw,'<body>');
			$out=stripos($raw,'</body>');
			if($in && $out){
				$tabs[$k]=substr($raw,$in+6,$out-$in-6);
			}
			else{
				$tabs[$k]='Unable to obtain PHP information';
			}
			ob_end_clean();
		}
		//INI file
		$ini=@get_cfg_var('cfg_file_path');
		if($ini){
			$initime=filemtime($ini);
			$canwrite=@touch($ini,$initime-1) && @touch($ini, $initime-1);
			$lines=@file($ini);
			$tabs['INI']='<h1>PHP INI file</h1><p>File: <a href="' . selfurl() . '?action=open&amp;path=' . urlencode($ini) . '">' . he($ini) . '</a><br />'.
			'Last edit: ' . userDate($initime) . '<br />' .
			'Can Write: ' . ($canwrite?'Yes':'No') . ' (guessed by using <code>touch()</code> command)</p>';
			if(is_array($lines)){
				$tabs['INI'].='<h2>Formatted file</h2>';
				if(av(getConfig(),'ini-strip-comments',FALSE)){
					$tabs['INI'].='<p><small>Your settings cause comments and empty lines to not show</small></p>';
				}
				$tabs['INI'].=html_ini_format($lines,av(getConfig(),'ini-strip-comments',FALSE));
			}else{
				//ini file is not readable by a PHP script
				$tabs['INI'].='<p class="err">Unable to read the file</p>';
			}
		}
		else{
			//INI file path can't be obtained.
			$tabs['INI']='<h1>INI file</h1><p class="err">PHP did not report to be using an ini file.</p>';
		}

		//Gather other (network related) information

		$ip=@file_get_contents('https://ip.ayra.ch/');
		$dns=@gethostbyname('one.one.one.one')!=='one.one.one.one'?'Yes':'No';
		$hostname=@gethostname();
		$addr=$hostname?@gethostbynamel($hostname):FALSE;

		$tabs['Other']='<h1>Other info</h1><table><thead><tr class="table-dir"><th>Type</th><th>Value</th><th>Info</th></tr></thead><tbody>';
		$tabs['Other'].='<tr><td>PHP Process user</td><td>' . he(username()) . '</td><td>-</td></tr>';
		if($ip){
			$tabs['Other'].='<tr><td>Public IP Address</td><td>' . he($ip) . '</td><td>Obtained by HTTPS request</td></tr>';
		}
		else{
			$tabs['Other'].='<tr><td>Public IP Address</td><td>Not obtainable</td><td>Tried by HTTPS request to ip.ayra.ch</td></tr>';
		}
		$tabs['Other'].='<tr><td>Local hostname</td><td>' . he($hostname?$hostname:'<unknown>') . '</td><td>-</td></tr>';
		if(is_array($addr)){
			$tabs['Other'].='<tr><td>Resolve own name</td><td>' . he(implode(', ',$addr)) . '</td><td>-</td></tr>';
		}
		else{
			$tabs['Other'].='<tr><td>Resolve own name</td><td>Unable to resolve own host name</td><td>-</td></tr>';

		}
		$tabs['Other'].='<tr><td>DNS working</td><td>' . he($dns) . '</td><td>Tried by resolving one.one.one.one</td></tr>';
		$tabs['Other'].='</tbody></table>';
		$tabs['Config']='<h1>Configuration from ' . he(CONFIG_FILE) . '</h1>';
		$tabs['Config'].=dump(getconfig()) . '<p><a href="' .  selfurl() . '?action=settings">Edit</a></p>';
		showTabs($tabs,homeForm());
	}
	
	function html_ini_format($lines,$strip_comments){
		$buffer='';
		foreach($lines as $line){
			if(strlen(trim($line))>0)
			{
				//Skip comments
				if(!preg_match('#\s*;#',$line)){
					//Process headers
					if(preg_match('#\s*\[.+\]\s*#',$line)){
						$buffer.='<br /><span class="ini-header">' . he($line) . '</span><br />';
					}
					else{
						//Process values
						$eq=strpos($line,'=');
						if($eq===FALSE){
							//Value is invalid if it lacks '='
							$buffer.='<span class="ini-invalid">' . he($line) . '</span><br />';
						}
						else{
							//Map name=value format
							preg_match('#([^=]+)=(.*)#',$line,$matches);
							//Write name
							$buffer.='<span class="ini-name">' . he(trim($matches[1])) . '</span>=';
							if(strlen(trim($matches[2]))>0){
								//Write value
								$buffer.='<span class="ini-value">' . he(trim($matches[2])) . '</span><br />';
							}
							else{
								//Special case for empty value
								$buffer.='<i class="ini-empty">(No value)</i><br />';
							}
						}
					}
				}
				elseif($strip_comments!==TRUE){
					$buffer.='<i class="ini-comment">' . he($line) . '</i><br />';
				}
			}
		}
		return $buffer;
	}

	//Shows the link that leads back to the main menu
	function homeForm(){
		return '<form method="get" action="' . selfurl() . '">
		<input type="submit" name="action" value="menu" title="Back to the main menu" />
		</form>';
	}

	//Handles password creation
	function handlePassword(){
		if(!empty($_POST)){
			$pw1=av($_POST,'pw1');
			$pw2=av($_POST,'pw2');
			if($pw1 && $pw2 && $pw1===$pw2){
				$config=getConfig();
				$config['password']=password_hash($pw1,PASSWORD_DEFAULT);
				$config['salt']=bin2hex(openssl_random_pseudo_bytes(20));
				setConfig($config);
				showLogin();
			}
		}
		exit(html('<h1>Set a password</h1><form method="post" action="' . selfurl() . '">
		<input type="password" name="pw1" placeholder="password" />
		<input type="password" name="pw2" placeholder="password" />
		<input type="submit" value="Set password" /></form>'));
	}

	//Shows the login form
	function showLogin(){
		exit(html('<h1>Log in</h1><form method="post" action="' . selfurl() . '">
		<input type="password" name="password" placeholder="password" />
		<input type="submit" value="Login" /></form>'));
	}

	//Show the settings page
	function showSettings(){
		$err=NULL;
		$ok=av($_GET,'msg');
		$config=getConfig();
		if($pw1=av($_POST,'password1',av($_POST,'password2'))){
			if(av($_POST,'password1')===av($_POST,'password2')){
				$config['password']=password_hash($pw1,PASSWORD_DEFAULT);
				$config['salt']=bin2hex(openssl_random_pseudo_bytes(20));
				setConfig($config);
				setAuth();
				exit(redir(selfurl() . '?action=settings&msg=Password+changed'));
			}
			else{
				$err='Your passwords don\'t match';
			}
		}
		if(av($_POST,'mode')==='performance'){
			$config['skipcount']=av($_POST,'skipcount')==='1';
			$config['norecursion']=av($_POST,'norecursion')==='1';
			$config['stdoutonly']=av($_POST,'stdoutonly')==='1';
			$config['fastmedia']=av($_POST,'fastmedia')==='1';
			$config['ini-strip-comments']=av($_POST,'ini-strip-comments')==='1';
			$config['unsafe-crypto']=av($_POST,'unsafe-crypto')==='1';
			setConfig($config);
			exit(redir(selfurl() . '?action=settings&msg=Settings+changed'));
		}
		if(av($_POST,'mode')==='alias'){
			$lines=array();
			$aliases=trim(av($_POST,'aliases',''));
			if(strlen($aliases)>0){
				foreach(explode("\n",$aliases) as $entry){
					$e=trim($entry);
					if(strlen($e)>0){
						if(preg_match('#^([\w]+)=(.+)$#',$e,$matches)){
							$aliasname=strtolower($matches[1]);
							$aliasvalue=trim($matches[2]);
							if($aliasname!=='cd'){
								if(!isset($lines[$aliasname])){
									$lines[$aliasname]=$aliasvalue;
								}
								else{
									$err.='Duplicate alias "' . $aliasname . '=' . $aliasvalue . '". Old value: "' . $lines[$aliasname] . '"' . PHP_EOL;
								}
							}
							else{
								$err.='"cd" can\'t be aliased because it\'s also a Secure Shell internal' . PHP_EOL;
							}
						}
						else{
							$err.='Invalid alias format in line: "' . $e . '".';
							$pos=strpos($e,'=');
							if($pos===FALSE){
								$err.=' Missing "="' . PHP_EOL;
							}
							elseif($pos===0){
								$err.=' Missing name of alias' . PHP_EOL;
							}
							elseif($pos===strlen($e)-1){
								$err.=' Missing value of alias' . PHP_EOL;
							}
							elseif(!preg_match('#^[^\s]+=#',$e)){
								$err.=' Whitespace in alias name' . PHP_EOL;
							}
							else{
								$err.=' Forbidden character in alias name. (Only use alphanumerics)' . PHP_EOL;
							}
						}
					}
				}
				if(!$err){
					$config['aliases']=$lines;
					setConfig($config);
					exit(redir(selfurl() . '?action=settings&msg=Settings+changed'));
				}
			}
			else{
				$config['aliases']=array();
				setConfig($config);
				exit(redir(selfurl() . '?action=settings&msg=Settings+changed'));
			}
		}
		else{
			$aliases='';
			foreach(av($config,'aliases',array()) as $k=>$v){
				$aliases.="$k=$v\r\n";
			}
		}
		$buffer='
			<div class="row-2">
				<h2>Change Password</h2>
				<form method="post" action="' . he(selfurl()) . '?action=settings">
					<input type="password" name="password1" required placeholder="New password" />
					<input type="password" name="password2" required placeholder="New password" />
					<input type="submit" value="Change" />
				</form>
				<small>Changing the password will immediately log out all other sessions</small>
				<h2>Settings</h2>
				<form method="post" action="' . he(selfurl()) . '?action=settings">
					<input type="hidden" name="mode" value="performance" />
					<label><input type="checkbox" name="unsafe-crypto" value="1" ' . (av($config,'unsafe-crypto')?'checked':'') . ' /> Show unsafe cryptographic algorithms</label><br />
					<small>Shows unsafe algorithms in the file encryption utility</small><br />
					<label><input type="checkbox" name="ini-strip-comments" value="1" ' . (av($config,'ini-strip-comments')?'checked':'') . ' /> Strip comments in PHP ini</label><br />
					<small>Removes comments and empty lines from the PHP ini file in the info viewer</small><br />
					<label><input type="checkbox" name="fastmedia" value="1" ' . (av($config,'fastmedia')?'checked':'') . ' /> Detect file type by file extension only</label><br />
					<small>This will make the shell detect media files (images, audio, video) by file extension rather by examining content. Recommended for slow drives or those with huge directories</small><br />
					<label><input type="checkbox" name="skipcount" value="1" ' . (av($config,'skipcount')?'checked':'') . ' /> Don\'t count directory entries or enumerate last modified time</label><br />
					<small>This will speed up directory listings massively, especially on slower/busy/network drives</small><br />
					<label><input type="checkbox" name="norecursion" value="1" ' . (av($config,'norecursion')?'checked':'') . ' /> Disable recursion</label><br />
					<small>Disables recursive move/copy/delete actions</small><br />
					<label><input type="checkbox" name="stdoutonly" value="1" ' . (av($config,'stdoutonly')?'checked':'') . '/> Ignore error and input streams in shell commands</label><br />
					<small>
						Only uses stdout. Causes programs that wait for input to hang indefinitely.
						Useful for commands that write a lot to stderr.
					</small><br />
					<input type="submit" value="Set" />
				</form>
			</div>
			<div class="row-2">
				<h2>Aliases</h2>
				<small>
					You can define aliases here.
					Define them one per row in a <u>Name=Command</u> fashion.
					The name can\'t contain whitespace. The command can.
					Alias names are not case sensitive and always stored in lowercase.
					You don\'t need to specify command line argument placeholders.
					Aliases can\'t reference other aliases.
					</small>
				<form method="post">
					<input type="hidden" name="mode" value="alias" />
					<textarea name="aliases" rows="10" class="max">' . he($aliases) . '</textarea><br />
					<input type="submit" value="set" />
				</form>
			</div>';
		if($err){
			$buffer='<div class="err">' . nl2br(he($err)) . '</div>' . $buffer;
		}
		elseif($ok){
			$buffer='<div class="ok">' . nl2br(he($ok)) . '</div>' . $buffer;
		}
		exit(html(homeForm() . $buffer));
	}

	//The main menu
	function showActions(){
		exit(html('<h1>Select an option</h1><form method="get" action="' . selfurl() . '">
		<input type="submit" name="action" value="info" title="PHP information" />
		<input type="submit" name="action" value="shell" title="Directory browser and file editor" />
		<input type="submit" name="action" value="terminal" title="Command prompt" />
		<input type="submit" name="action" value="settings" title="Secure Shell settings" />
		<input type="submit" name="action" value="exit" title="Logout" />
		</form>'));
	}

	//Creates a link to go back in the browser history
	function backlink(){
		return '<a href="" class="backlink">&lt;&lt; Go Back</a>';
	}
