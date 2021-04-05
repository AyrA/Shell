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

	//Generates an external HTML link
	function extLink($url,$text=NULL){
		if($text===NULL){
			$text=$url;
		}
		//External links are constructed in a way to not leak the current referer or allow page interaction
		return
			'<a href="' . he($url) . '" title="' . he($url) .
			'" target="_blank" rel="noreferer noopener nofollow">' . he($text) . '</a>';
	}

	//Renders HTML output
	function html($x){
		$footer=array(
			'PHP'=>phpversion() . ' ' . (PHP_INT_SIZE*8) . ' bit',
			'API'=>php_sapi_name(),
			'OS'=>PHP_OS . ' [' . php_uname() . ']',
			'User'=>username()
		);
		$theme=av(getConfig(),'theme');
		$themes=getThemes();
		if(!is_array(av($themes,$theme))){
			$theme=NULL;
		}

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
		<link rel="stylesheet" type="text/css" href="res/css.css" />';
		if($theme){
			echo '<link rel="stylesheet" type="text/css" id="themeCSS" href="res/theme.' . urlencode($theme) . '.css" />';
		};
		echo '<title>Secure Shell</title>
		<meta name="robots" content="noindex, nofollow" />
	</head>
	<body><div class="action-' . he($action) . '">' . $x . '<div><p>' . he(implode(' | ',$footerline)) . '</p></div>
	<script src="res/js.js"></script>';
	if($theme && $themes[$theme]['js']){
		echo '<script src="res/theme.' . urlencode($theme) . '.js"></script>';
	};
	echo '</body>
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
				$tabs['INI'].=
					'<pre class="ini">' .
					html_ini_format($lines,av(getConfig(),'ini-strip-comments',FALSE)) .
					'</pre>';
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
		$tabs['License']='<pre class="license">' . he(file_get_contents('LICENSE')) . '</pre>';
		$tabs['Config']='<h1>Configuration from ' . he(CONFIG_FILE) . '</h1>';
		$tabs['Config'].=dump(getconfig()) . '<p><a href="' .  selfurl() . '?action=settings">Edit</a></p>';
		showTabs($tabs,homeForm());
	}
	
	function html_ini_format($lines,$strip_comments){
		$buffer='';
		foreach($lines as $line){
			if(strlen($line=trim($line))>0)
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
								$buffer.='<span class="ini-empty">(No value)</span><br />';
							}
						}
					}
				}
				elseif($strip_comments!==TRUE){
					$buffer.='<span class="ini-comment">' . he($line) . '</span><br />';
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
			$lic=av($_POST,'license')==='1';
			if($lic && $pw1 && $pw2 && $pw1===$pw2){
				$config=getConfig();
				$config['password']=password_hash($pw1,PASSWORD_DEFAULT);
				$config['salt']=bin2hex(openssl_random_pseudo_bytes(20));
				setConfig($config);
				showLogin();
			}
		}
		exit(html('<h1>Set a password</h1><form method="post" action="' . selfurl() . '">
		Password: <input type="password" name="pw1" placeholder="Password" required minlength="8" /><br />
		Password: <input type="password" name="pw2" placeholder="Password (repeat)" required minlength="8" /><br />
		<pre class="license">' . he(file_get_contents('LICENSE')) . '</pre>
		<label>
			<input type="checkbox" name="license" value="1" required />
			Accept the MIT license
		</label><br />
		<input type="submit" value="Save" /></form>'));
	}

	//Shows the login form
	function showLogin(){
		exit(html('<h1>Log in</h1><form method="post" action="' . selfurl() . '">
		<input type="password" name="password" placeholder="password" />
		<input type="submit" value="Login" /></form>'));
	}

	//Shows the main menu
	function showMainMenu(){
		$buffer='<h1>Secure Shell</h1>';
		$buffer.=getActions();
		$buffer.='<p>Current version: ' . SHELL_VERSION . '</p>';
		$buffer.='<p>' . extLink('https://github.com/AyrA/Shell/blob/master/README.md','Documentation') . '</p>';

		if(TRUE===($ret=update_cancheck())){
			if(is_array($ret=update_check())){
				$latest=av($ret,'max');
				if(is_string($latest)){
					$release=av(av($ret,'versions'),$latest);
					if($release){
						$md=new Parsedown();
						$buffer.='<h2>A newer version is available.</h2><p>
							<a href="' . selfurl() . '?action=update">Install ' . he($latest) . '</a></p>
							<p>
							Version: ' . he($latest) . '<br />
							Title: ' . he($release['title']) .'<br />
							Details: <div class="update-desc">' . $md->text($release['desc']) .'</div>
							</p>';
					}
					else{
						$buffer.='<p class="err">Version data invalid. Try resetting the version cache in your settings.</p>';
					}
				}
				else{
					$buffer.='<p>No versions published yet.</p>';
				}
			}
			elseif(is_string($ret)){
				$buffer.='<p class="err">Version check failed.<br />Reason: ' . he($ret) . '</p>';
			}
			else{
				$buffer.='<p class="err">Version check failed.<br />Unknown reason</p>';
			}
		}
		else{
			$buffer.='<p class="err">Cannot check for updates.<br />Reason: ';
			$buffer.=he(is_string($ret)?$ret:'Unknown reason') . '</p>';
		}
		exit(html($buffer));
	}

	//The main menu
	function getActions(){
		return '<h2>Main menu</h2><form method="get" action="' . selfurl() . '">
		<input type="submit" name="action" value="info" title="PHP information" />
		<input type="submit" name="action" value="shell" title="Directory browser and file editor" />
		<input type="submit" name="action" value="terminal" title="Command prompt" />
		<input type="submit" name="action" value="settings" title="Secure Shell settings" />
		<input type="submit" name="action" value="exit" title="Logout" />
		</form>';
	}

	//Creates a link to go back in the browser history
	function backlink(){
		return '<a href="" class="backlink">&lt;&lt; Go Back</a>';
	}
