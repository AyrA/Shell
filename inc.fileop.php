<?php
	/*
		Contains functions for file/dir operations
	*/

	//Deletes a file or directory
	function deleteFileOrDir(){
		$path=realpath(av($_GET,'path','/'));
		if(file_exists($path)){
			$config=getConfig();
			if(isset($_POST['yes']) || isset($_POST['no'])){
				if(isset($_POST['yes'])){
					if(is_file($path)){
						@unlink($path);
					}
					else{
						if(av($config,'norecursion')){
							@rmdir($path);
						}
						else{
							@rdrec($path);
						}
					}
				}
				exit(redir(selfurl() . '?action=shell&path=' . urlencode(dirname($path))));
			}
		}
		if(av($config,'norecursion') && is_dir($path) && !emptydir($path)){
			$buffer='<h1 class="err">You disabled recursive operations but the directory is not empty</h1>
			<p>You can change this in your settings</p>';
		}
		else{
			$buffer='<h1>Delete file or folder</h1><p>Really delete "' . he($path) . '"?</p>';
			if(is_dir($path)){
				$buffer.='<p>This will delete the directory and all contents</p>';
			}
			else{
				$buffer.='<p>This will delete the file without further confirmation</p>';
			}
			$buffer.='<form method="post">
				<input type="submit" name="yes" value="Delete" />
				<input type="submit" name="no" value="Cancel" />
			</form>';
		}
		exit(html($buffer));
	}

	//Calcualte directory size
	function calcSize(){
		$buffer='';
		$path=realpath(av($_GET,'path','/'));
		if(!is_dir($path)){
			exit(html('<h1 class="err">This feature is only for directories</h1>'));
		}
		$size=dirsize($path);
		$buffer='<h1>Size of ' . he(basename($path)) . '</h1>';
		$buffer.='<p>Total size: <span title="' . he($size) . ' bytes">' . formatSize($size) . '</span></p>';
		$buffer.='<p><a href="#" class="backlink">&lt;&lt;Go Back</a></p>';
		exit(html($buffer));
	}

	//Zip a file or directory
	function zipFile(){
		$file=av($_GET,'file');
		$mode=av($_GET,'mode');
		$dest=av($_GET,'dest');
		if($file && $dest && file_exists($file)){
			set_time_limit(300);
			if(!$mode){
				$mode=is_file($file)?'gz':'zip';
			}
			if($dest==='.'){
				$dest=realpath(dirname($file));
			}
			if(is_file($file)){
				if(in_array(mime_content_type($file),array(MIME_ZIP,MIME_GZ))){
					exit(html(
						'<b>' . he($file) . ' is already compressed</b><br />' .
						'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
					return FALSE;
				}
			}

			if($mode==='gz'){
				if(is_dir($file)){
					exit(html(
						'<b>gz only supports single files</b><br />' .
						'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
					return FALSE;
				}
				if(is_dir($dest)){
					$dest=freeName($dest . DIRECTORY_SEPARATOR . basename($file) . '.gz');
				}
				if($in=fopen($file,'rb')){
					if($out=gzopen($dest,'wb9')){
						stream_copy_to_stream($in,$out);
						fclose($out);
						fclose($in);
						touch($dest,filemtime($file));
						redir(selfurl() . '?action=shell&path=' . urlencode(dirname($dest)));
						return TRUE;
					}
					fclose($in);
					exit(html(
						'<b>Unable to create ' . he($dest) . '</b><br />' .
						'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
				}
				exit(html(
					'<b>Unable to read ' . he($file) . '</b><br />' .
					'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
			}
			elseif($mode==='zip'){
				if(is_dir($dest)){
					$zipname=$dest . DIRECTORY_SEPARATOR . basename($file) . '.zip';
				}
				else{
					$zipname=$dest;
				}
				$zipname=freeName($zipname);
				$zip=new ZipArchive();
				if($zip->open($zipname,ZipArchive::CREATE)){
					//Zip with single file
					if(is_file($file)){
						$zip->addFile($file,'/' . basename($file));
					}
					else{
						$file=realpath($file);
						//Zip always uses forward slashes
						$zip_base=str_replace(DIRECTORY_SEPARATOR,'/',$file);
						$stack=array($file);
						while(count($stack)>0){
							$current=array_shift($stack);
							//Build name for zip file entry
							$zip_current=substr($current,strlen($file));
							$zip_current=str_replace(DIRECTORY_SEPARATOR,'/',$zip_current);
							$zip->addEmptyDir($zip_current);
							$entries=scandir($current);
							foreach($entries as $e){
								if($e==='.' || $e==='..'){
									continue;
								}
								$full=realpath($current . DIRECTORY_SEPARATOR . $e);
								if(is_dir($full)){
									$stack[]=$full;
								}
								else{
									$full=str_replace(DIRECTORY_SEPARATOR,'/',$full);
									$zip->addFile($full,substr($full,strlen($zip_base)));
								}
							}
						}
					}
					$zip->close();
					touch($zipname,filemtime($file));
					redir(selfurl() . '?action=shell&path=' . urlencode(dirname($zipname)));
					return TRUE;
				}
				exit(html(
					'<b>Unable to create zip file</b><br />' .
					'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
			}
			exit(html(
			'<b>Unknown mode. Only "zip" and "gz" are supported</b><br />' .
			'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));

		}
		exit(html(
			'<b>Missing parameters</b><br />' .
			'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
		return FALSE;
	}

	//Extract zip or gz archive
	function unzipFile(){
		$file=av($_GET,'file');
		$dest=av($_GET,'dest');
		if($file && $dest && is_file($file)){
			if($dest==='.'){
				$dest=dirname($file);
			}
			$mime=mime_content_type($file);
			if($mime===MIME_ZIP){
				$zip=new ZipArchive();
				if($zip->open($file)){
					set_time_limit(300);
					$ok=$zip->extractTo($dest);
					$zip->close();
					if($ok){
						redir(selfurl() . '?action=shell&path=' . urlencode($dest));
						return TRUE;
					}
					else{
						exit(html(
							'<b>Unable to extract to ' . he(basename($dest)) . '</b><br />' .
							'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
					}
				}
				exit(html(
					'<b>Unable to open ' . he(basename($file)) . ' as zip</b><br />' .
					'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
			}
			elseif($mime===MIME_GZ){
				$base=basename($file);
				if(preg_match('#(.+)\.gz$#i',$base,$m)){
					$base=$m[1];
				}
				else{
					$base='file.bin';
				}
				$target=freeName($dest . DIRECTORY_SEPARATOR . $base);

				if($in=gzopen($file,'rb')){
					if($out=fopen($target,'wb')){
						stream_copy_to_stream($in,$out);
						fclose($out);
						fclose($in);
						touch($target,filemtime($file));
						redir(selfurl() . '?action=shell&path=' . urlencode($dest));
					}
					fclose($in);
					exit(html(
						'<b>Unable to open ' . he(basename($target)) . ' for writing</b><br />' .
						'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
				}
			}
			else{
				exit(html(
					'<b>Unknown archive type for ' . he(basename($file)) . '</b><br />' .
					'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
			}
		}
		else{
			exit(html(
				'<b>Invalid unzip command</b><br />' .
				'<a href="#" class="backlink">&lt;&lt;Go Back</a>'));
		}
		return FALSE;
	}

	//Rename a file or directory
	function renameFile(){
		$path=realpath(av($_GET,'path','/'));
		$err=NULL;
		if(file_exists($path)){
			$config=getConfig();
			chdir(dirname($path));
			if(($newname=av($_POST,'filename')) && ($mode=av($_POST,'mode'))){
				set_time_limit(0);
				switch($mode){
					case 'rename':
						if(@rename($path,$newname)){
							exit(redir(selfurl() . '?action=shell&path=' . urlencode(getcwd())));
						}
						else{
							$err='Unable to rename/move this object';
						}
						break;
					case 'copy':
						if(is_file($path)){
							if(@copy($path,$newname)){
								exit(redir(selfurl() . '?action=shell&path=' . urlencode(getcwd())));
							}
							else{
								$err='Unable to copy this file';
							}
						}
						else{
							if(av($config,'norecursion')){
								$err='Recursive operations have been disabled in your settings';
							}
							elseif(dircopy($path,$newname)){
								exit(redir(selfurl() . '?action=shell&path=' . urlencode(getcwd())));
							}
							else{
								$err='Error copying the directory';
							}
						}
						break;
					default:
						$err='Invalid file operation';
						break;
				}
			}

			$buffer='<h1>Perform filesystem action</h1>';
			if($err){
				$buffer.='<div class="err">' . he($err) . '</div>';
			}
			$buffer.='<p>Object: ' . he($path) . '</p><form method="post">
				<input type="text" name="filename" value="' . he($path) . '" required size="60" />
				<br />
				<select name="mode">
					<option value="rename">Rename or move (local)</option>
					<option value="copy">Copy (local or FTP)</option>
				</select>
				<input type="submit" value="OK" />
			</form>
			<h2>Local actions</h2>
			<p>
				Rename and move are the same action from a file system perspective.
				In fact, you can move a file to a new destination and rename it at the same time.
				The new name is interpreted relative to the old name but we recommend to use full paths.<br />
				<b class="err">Warning! The destination is overwritten if it already exists</b><br />
				Copying directories can be very slow as we have to do it manually,
				PHP doesn\'t knows how on its own.
			</p>
			<h2>FTP actions</h2>
			<p>
				You can speficy an FTP url in the format <code>ftp://host/dir</code>
				or <code>ftp://user:pass@host/dir</code>.
				FTP actions only support copying of elements, not moving them.
				To move a file or directory, copy it instead, then delete it.<br />
				<b class="err">Warning! The destination is overwritten if it already exists</b><br />
				FTP actions can be very slow, please be patient.
				Use the terminal to put your files into compressed archives to speed up the process.
			</p>';
		}
		else{
			$buffer='<h1 class="err">The specified file/directory no longer exists</h1>';
		}
		exit(html($buffer . '<p><a href="' . selfurl() . '?action=shell&amp;path=' . he(dirname($path)) . '">&lt;&lt;Go Back</a></p>'));
	}
