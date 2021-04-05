<?php
	/*
		Provides zip and unzip ability
	*/

	//Converts a path into a path as expected by the zip component
	function zip_name($name){
		if(!is_string($name)){
			return FALSE;
		}
		if(strlen($name===0)){
			return '/';
		}
		
		//Replace directory separator
		$name=str_replace(DIRECTORY_SEPARATOR,'/',$name);
		//Replace consecutive slashes with one
		$name=preg_replace('#/+#','/',$name);
		//Make sure the path starts with a slash
		if(strpos($name,'/')!==0){
			$name="/$name";
		}
		return $name;
	}
	//Compresses a file or directory into a zip file
	function zip_compress($source,$dest){
		$zip=new ZipArchive();
		if(!is_dir($source) && !is_file($source)){
			//Neither file nor directory.
			//Probably doesn't exists.
			return FALSE;
		}
		if($zip->open($dest,ZipArchive::CREATE)){
			//Zip with single file
			if(is_file($source)){
				$zip->addFile($source,'/' . basename($source));
			}
			else{
				$source=realpath($source);
				//Zip always uses forward slashes
				$zip_base=zip_name($source);
				//This stack will only contain pending directories.
				$stack=array($source);
				while(count($stack)>0){
					//Pretend we're using a queue.
					//We could also use "array_pop" but the directory structure looks "weird".
					//Recursive directory processing works more like a FIFO queue rather than a LIFO stack.
					$current=array_shift($stack);
					//Build name for zip file entry by cutting off the common base
					//Zip entries start with a slash so it's correct that we don't cut that one off.
					$zip_current=substr($current,strlen($source));
					//In case we're on a system that doesn't uses a "/" as separator,
					//for example Windows and IMAP.
					$zip_current=zip_name($zip_current);
					$zip->addEmptyDir($zip_current);
					$entries=scandir($current);
					foreach($entries as $e){
						if($e==='.' || $e==='..'){
							continue;
						}
						$full=realpath($current . DIRECTORY_SEPARATOR . $e);
						if(is_dir($full)){
							//Put subdirectory on the queue for later scan,
							//but do not substitute path separators yet.
							$stack[]=$full;
						}
						else{
							//Files are added immediately.
							$full=zip_name($full);
							$zip->addFile($full,substr($full,strlen($zip_base)));
						}
					}
				}
			}
			$zip->close();
			//Since we're technically not modifying the file data,
			//we apply the modification date of the source to the zip
			@touch($zipname,filemtime($source));
		}
		return FALSE;
	}

	//Decompresses a zip file into the given directory
	function zip_decompress($zipfile,$dest){
		$ok=FALSE;
		//Check if the destination is a directory and also attempt to create it.
		$created=FALSE;
		if(!is_dir($dest) && !($created=@mkdir($dest))){
			return FALSE;
		}
		$zip=new ZipArchive();
		if($zip->open($zipfile)){
			set_time_limit(0);
			$ok=$zip->extractTo($dest);
			set_time_limit(30);
			$zip->close();
			//Fix all timestamps of extracted files and directories
			$data=zip_enum($zipfile);
			foreach($data['entries'] as $e){
				if($fullname=realpath($dest . DIRECTORY_SEPARATOR . $e['name'])){
					if(!@touch($fullname,$e['last-modified'])){
						throw new  Exception('touch("' . $fullname . '",' . $e['last-modified'] . ') failed');
					}
				}
				else{
					throw new  Exception('realpath(' . $dest . $e['name'] . ') failed');
				}
			}
			//Don't mess with the timestamp of the base directory if we did not create it.
			//Don't bother if it fails, some systems don't allow changing the mtime of a directory anyways.
			if($created){
				@touch($dest,filemtime($zipfile));
			}
		}
		return $ok;
	}

	//Enumerates zip file contents
	function zip_enum($zipfile){
		$flags=defined('ZipArchive::RDONLY')?ZipArchive::RDONLY:0;
		$zip=new ZipArchive();
		if($zip->open($zipfile,$flags)){
			$ret=array('comment'=>NULL,'entries'=>array(),'size'=>array('real'=>0,'compressed'=>0));
			if($comment=$zip->getArchiveComment()){
				$ret['comment']=$comment;
			}
			$entries=$zip->count();
			if($entries>0){
				for($i=0;$i<$entries;$i++){
					$zipentry=$zip->statIndex($i);
					$ret['entries'][]=array(
						//Directory entries have a trailing slash
						'isdir'=>strrpos($zipentry['name'],'/')===strlen($zipentry['name'])-1,
						//Remove leading and trailing slashes
						//A leading slash is not always there so we can't just cut it off using substr()
						'name'=>trim($zipentry['name'],'/'),
						'size'=>array('real'=>$zipentry['size'],'compressed'=>$zipentry['comp_size']),
						'last-modified'=>$zipentry['mtime'],
						'crc'=>sprintf('%08X',$zipentry['crc'])
					);
					$ret['size']['real']+=$zipentry['size'];
					$ret['size']['compressed']+=$zipentry['comp_size'];
				}
			}
			$zip->close();
			return $ret;
		}
		return FALSE;
	}