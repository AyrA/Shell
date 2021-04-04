<?php
	/*
		Handles shell settings
	*/

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
		if(av($_POST,'mode')==='settings'){
			$config['allowbots']=av($_POST,'allowbots')==='1';
			$config['skipcount']=av($_POST,'skipcount')==='1';
			$config['norecursion']=av($_POST,'norecursion')==='1';
			$config['stdoutonly']=av($_POST,'stdoutonly')==='1';
			$config['fastmedia']=av($_POST,'fastmedia')==='1';
			$config['ini-strip-comments']=av($_POST,'ini-strip-comments')==='1';
			$config['unsafe-crypto']=av($_POST,'unsafe-crypto')==='1';
			$config['keep-modified']=av($_POST,'keep-modified')==='1';
			$theme=av($_POST,'theme');
			if($theme && is_array(av(getThemes(),$theme))){
				$config['theme']=$theme;
			}
			else{
				unset($config['theme']);
			}
			setConfig($config);
			exit(redir(selfurl() . '?action=settings&msg=Settings+changed'));
		}
		if(av($_POST,'mode')==='version'){
			if(isset($config['version-cache'])){
				unset($config['version-cache']);
			}
			setConfig($config);
			exit(redir(selfurl() . '?action=settings&msg=Version+cache+cleared'));
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
		$version_time=av(av($config,'version-cache'),'time',0);
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
					<input type="hidden" name="mode" value="settings" />
					<label><input type="checkbox" name="keep-modified" value="1" ' . (av($config,'keep-modified')?'checked':'') . ' /> Keep modified timestamp of edited files by default</label><br />
					<small>Enabling this makes the shell check the checkbox for retaining modified time below the text editor by default.</small><br />
					<label><input type="checkbox" name="unsafe-crypto" value="1" ' . (av($config,'unsafe-crypto')?'checked':'') . ' /> Show unsafe/invalid cryptographic algorithms</label><br />
					<small>Shows unsafe and non-working algorithms in the file encryption utility</small><br />
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
						Useful for commands that write a lot to stderr
					</small><br />
					<label><input type="checkbox" name="allowbots" value="1" ' . (av($config,'allowbots')?'checked':'') . '/> Allow robot access</label><br />
					<small>Allows access by automated tools and known bots</small><br />
					Theme: <select name="theme" id="themebox">';
					foreach(getThemes() as $themename=>$theme){
						$buffer.='<option value="' . he($themename) .
							'" ' . ($themename===av($config,'theme')?'selected':'') . '>' .
							he($theme['name'] . ' - ' . $theme['desc']) . '</option>';
					}
					$buffer.='</select><br />
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
			if($version_time>time()-86400){
				$buffer.='<div>
					<h2>Version cache</h2>
					<p>
						You can clear the version cache to force a re-check of the current version.
						The cache was created at ' . he(gmdate(USER_DATE,$version_time)) . '.
					</p>
					<form method="post">
						<input type="hidden" name="mode" value="version" />
						<input type="submit" value="Clear version cache" />
					</form>
				</div>';
			}
			else{
				$buffer.='<div><h2>Version check</h2>
					<a href="' . selfurl() . '">Check for new version</a></div>';
			}
			$buffer.='<h2>License</h2><pre class="license">' . he(file_get_contents('LICENSE')) . '</pre>';
		if($err){
			$buffer='<div class="err">' . nl2br(he($err)) . '</div>' . $buffer;
		}
		elseif($ok){
			$buffer='<div class="ok">' . nl2br(he($ok)) . '</div>' . $buffer;
		}
		exit(html(homeForm() . $buffer));
	}
