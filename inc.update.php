<?php
	/*
		Provides functions for updating the shell
	*/
	define('UPDATE_HEADERS',array('Accept'=>'application/vnd.github.v3+json'));

	//Tests if automated update checks are possible
	function update_cancheck(){
		return function_exists('curl_init');
	}

	//Checks for new versions
	function update_check(){
		if(!update_cancheck()){
			return FALSE;
		}
		$config=getConfig();
		if(is_array(av($config,'version-cache')) && $config['version-cache']['time']>time()-86400){
			return $config['version-cache']['data'];
		}
		$data=http_get('https://api.github.com/repos/AyrA/Shell/releases',UPDATE_HEADERS);
		if($data['success']){
			$tags=json_decode($data['response'],TRUE);
			if(!is_array($tags)){
				return 'Github response was not of the expected type. Expected array, got ' . gettype($tags);
			}
			$ret=array();
			$vmax=NULL;
			//Offer master branch as fake version to allow testing of the update system
			if(DEBUG){
				$vmax='99.99.99';
				$ret['99.99.99']=array(
					'desc'=>'This is the current state of the [github master branch](https://github.com/AyrA/Shell).',
					'download'=>'https://github.com/AyrA/Shell/archive/refs/heads/master.zip',
					'title'=>'Git Master',
					'date'=>time(),
					'beta'=>TRUE
				);
			}
			foreach($tags as $tag){
				//Our tags will be in the format "vX.Y.Z" so we cut off the "v" to get a raw version
				$version=substr(av($tag,'tag_name'),1);
				//Ignore drafts
				if(!av($tag,'draft')){
					$ret[$version]=array(
						'desc'=>av($tag,'body'),
						'download'=>av($tag,'zipball_url'),
						'title'=>av($tag,'name'),
						'date'=>strtotime(av($tag,'published_at')),
						'beta'=>av($tag,'prerelease')===TRUE,
					);
				}
			}
			$config['version-cache']=array('time'=>time(),'data'=>$ret);
			setConfig($config);
			return $ret;
		}
		return FALSE;
	}

	//Gets the latest installable version
	function update_getlatest(){
		$vmax='0.0.0';
		$config=getConfig();
		if(is_array($data=update_check())){
			foreach($data as $version=>$data){
				if(!$data['beta'] || av($config,'update-prerelease')){
					$vmax=version_compare($vmax,$version)<0?$version:$vmax;
				}
			}
		}
		return $vmax;
	}

	//Check if the latest version is newer than the currently used version
	function update_hasupdate(){
		return version_compare(SHELL_VERSION,update_getlatest())<0;
	}

	//Gets all versions that are newer than the current version
	function update_listupdates(){
		$ret=array();
		$data=update_check();
		if(!is_array($data) || count($data)===0){
			return FALSE;
		}
		foreach(array_keys($data) as $v){
			if(version_compare(SHELL_VERSION,$v)<0){
				$ret[]=$v;
			}
		}
		return $ret;
	}

	//Prepares for an update
	function update_perform(){
		//Create temporary folder
		$temp=freeName(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shell');
		if(!@mkdir($temp)){
			return 'Unable to create temporary directory: ' . $temp;
		}
		//Check for new versions
		if(!($update=update_check())){
			@rdrec($temp);
			return 'Unable to check for updates';
		}
		$vmax=update_getlatest();
		if($vmax===SHELL_VERSION){
			@rdrec($temp);
			return 'No new version available';
		}
		//Check if a version is present
		$latest=av($update,$vmax);
		if(!$latest){
			@rdrec($temp);
			return 'No releases in the shell repository.';
		}
		//Obtain latest version
		if(DEBUG){
			//Don't repeatedly download, but use a local copy in the temp directory
			debug_log('Update: Pretend to download');
			$result=array(
				'success'=>TRUE,
				'response'=>@file_get_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shell_debug.zip')
			);
		}
		else{
			$result=http_get($latest['download'],UPDATE_HEADERS);
		}
		if(av($result,'success')!==TRUE){
			@rdrec($temp);
			return 'Failed to download zip archive from ' . $latest['download'];
		}
		//Files for backup and update
		$zipfile=$temp . DIRECTORY_SEPARATOR . 'update.zip';
		$backup=$temp . DIRECTORY_SEPARATOR . 'backup.zip';
		//Try to write down the response
		if(!@file_put_contents($zipfile,$result['response'])){
			@rdrec($temp);
			return "Faled to store zip file as '$zipfile'. Disk full or write protected?";
		}
		//Check if file is detected as a zip
		if(($mime=mime_content_type($zipfile))!==MIME_ZIP){
			@rdrec($temp);
			return "File does not seems to be a zip file. Is reported as '$mime' instead.";
		}
		//Backup existing shell
		if(!zip_compress(__DIR__,$backup)){
			@rdrec($temp);
			return "Failed to create backup zip archive at '$backup'. Disk full?";
		}
		//Unpack new shell
		if(!zip_decompress($zipfile,$temp)){
			@rdrec($temp);
			return "Failed to extract update zip archive at '$zipfile'. Disk full?";
		}

		//Find the unpacked directory
		//The main directory is normally in the "repository-tag" format,
		//but using the API yields "user-repository-tag-id" format
		$shelldir=NULL;
		foreach(array_merge(glob($temp . '/Shell-*'),glob($temp . '/AyrA-Shell-*')) as $dir){
			//There should ever be only one directory, but just in case github changes the format,
			//We search for the directory that contains the shell.php file.
			if(is_dir($dir) && is_file($dir . DIRECTORY_SEPARATOR . 'shell.php')){
				$shelldir=basename($dir);
			}
		}
		if($shelldir===NULL){
			@rdrec($temp);
			return "'$zipfile' does not contains a 'Shell-*' main directory. File corrupt?";
		}
		debug_log("Update: New shell directory detected as '$shelldir'");
		$config=getConfig();
		//Delete existing shell
		update_delshell(__DIR__);

		//Copy new shell
		if(!DEBUG){
			if(!dircopy("$temp/$shelldir",__DIR__)){
				return "Cannot copy '$temp/$shelldir' to " . __DIR__ . ". Disk full?";
			}
		}
		//Delete the temporary copy
		@rdrec($temp);

		//Apply settings again
		setConfig($config);

		if(DEBUG){
			header('Content-Type: text/plain; charset=utf-8');
			echo debug_getLog();
		}
		return TRUE;
	}

	//Deletes a directory recursively but leaves dotfiles intact
	function update_delshell($base){
		//If this is TRUE, it will try to delete $base.
		//Set to FALSE whenever a sub-item can't be deleted.
		$delete_current=TRUE;
		$current=realpath($base);
		if(!$current){
			return FALSE;
		}
		debug_log("Update: Scanning $current");
		foreach(scandir($current) as $entry){
			$full=realpath($current . DIRECTORY_SEPARATOR . $entry);
			//Realpath will resolve symlinks.
			//If this happens and we land outside of the current directory,
			//we just delete the link but do not follow it.
			//If the link is inside of the current directory,
			//we treat is as a file and and just unlink it
			//regardless of whether it points to a file or directory.
			if(strpos($full,$current . DIRECTORY_SEPARATOR)!==0){
				debug_log("Update: Symlink '$entry' points to outside directory: '$full'");
				if(!DEBUG && !@unlink($full)){
					$delete_current=FALSE;
					debug_log("Update: Failed to delete link '$full'");
				}
				else{
					debug_log("Update: Removed link '$full'");
				}
			}
			elseif(strpos($entry,'.')!==0){
				if(is_file($full) || is_link($full)){
					if(!DEBUG && !@unlink($full)){
						$delete_current=FALSE;
						debug_log("Update: Failed to delete file '$full'");
					}
					else{
						debug_log("Update: Removed file '$full'");
					}
				}
				else{
					$delete_current=$delete_current && update_delshell($full);
				}
			}
			elseif($entry!=='.' && $entry!=='..'){
				$delete_current=FALSE;
				debug_log("Will not delete dotfile: '$full'");
			}
		}
		//Delete the current directory
		if($delete_current){
			if(!DEBUG && !@rmdir($full)){
				$delete_current=FALSE;
				debug_log("Update: Failed to delete directory '$full'");
			}
			else{
				debug_log("Update: Deleted directory '$full'");
			}
		}
		else{
			debug_log("Update: Will not delete '$full' because it's not empty");
		}
		return $delete_current;
	}


	//Shows update HTML
	function showUpdate(){
		if(!update_cancheck()){
			exit(html('<h1>Install update</h1><p class="red">Cannot check for updates. Is the curl extension not loaded?</p>' . backlink()));
		}
		if(!($update=update_check())){
			exit(html('<h1>Install update</h1><p class="red">Update check failed. Is <code>api.github.com</code> accessible?</p>' . backlink()));
		}
		if(!av($update,'max')){
			exit(html('<h1>Install update</h1><p class="red">Update check failed. No version has been published yet</p>' . backlink()));
		}

		if(av($_POST,'step')==='install'){
			die(update_perform());
		}


		$version=av($update,$update['max']);

		//Check prequisites
		$tempfile='shell-' . sha1(time() . '-' . json_encode($_SERVER));
		$prequisites=array(
			'shell-writable'=>@touch(__DIR__ . DIRECTORY_SEPARATOR . $tempfile),
			'shell-deletable'=>FALSE,
			'temp'=>sys_get_temp_dir(),
			'temp-exists'=>is_dir(sys_get_temp_dir()),
			'temp-writable'=>@touch(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempfile),
			'temp-deletable'=>FALSE
		);

		if($prequisites['shell-writable']){
			$prequisites['shell-deletable']=@unlink(__DIR__ . DIRECTORY_SEPARATOR . $tempfile);
		}

		if($prequisites['temp-writable']){
			$prequisites['temp-deletable']=@unlink(sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempfile);
		}
		$canupdate=TRUE;
		foreach($prequisites as $v){
			$canupdate=$canupdate && (is_string($v) || $v===TRUE);
		}

		$buffer='<h1>Install update</h1><p>
			You are about to update to the latest version.
			Please create a manual backup of the shell before proceeding.
			Updating will erase any custom theme you may have installed.
			</p>
			<h2>Prequisites:</h2>
			<p>Please ensure the following conditions are met</p>
			<ul>';

			if($prequisites['shell-writable']){
				$buffer.='<li>Can write to shell: <span class="ok">Yes</span></li>';
			}
			else{
				$buffer.='<li>Can write to shell: <span class="err">No</span></li>';
			}
			if($prequisites['shell-deletable']){
				$buffer.='<li>Can delete shell: <span class="ok">Yes</span></li>';
			}
			else{
				$buffer.='<li>Can delete shell: <span class="err">No</span></li>';
			}
			if($prequisites['temp-exists']){
				$buffer.='<li>Has temp directory: <span class="ok">Yes</span></li>';
			}
			else{
				$buffer.='<li>Has temp directory: <span class="err">No</span></li>';
			}
			if($prequisites['temp-writable']){
				$buffer.='<li>Can write to temp directory: <span class="ok">Yes</span></li>';
			}
			else{
				$buffer.='<li>Can write to temp directory: <span class="err">No</span></li>';
			}
			if($prequisites['temp-deletable']){
				$buffer.='<li>Can delete from temp directory: <span class="ok">Yes</span></li>';
			}
			else{
				$buffer.='<li>Can delete from temp directory: <span class="err">No</span></li>';
			}

			$buffer.='</ul>
			<h2>Performed actions:</h2>
			<p>
				The actions below are performed when you click on "Install update"
			</p>
			<ol>
				<li>Backing up <code>' . he(__DIR__) . '</code> to <code>' . he(sys_get_temp_dir()) . '</code> directory</li>
				<li>Downloading the latest version (<code>' . he($update['max']) . '</code>) to your temp directory</li>
				<li>Extracting the update</li>
				<li>Emptying the shell directory</li>
				<li>Moving the extracted contents into the shell directory</li>
				<li>Restoring your settings</li>
				<li>Deleting the backup copy</li>
			</ol>';
			if(!$canupdate){
				$buffer.='<p class="err">Cannot update. Prequisites not met</p>';
			}
			else{
				$buffer.='<form method="post"><p>
					<input type="hidden" name="step" value="install" />
					<input type="submit" value="Install update" />
					</p></form>';
			}
		exit(html($buffer));
	}
