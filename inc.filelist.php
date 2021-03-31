<?php
	/*
		Contains functions to show directory contents
	*/
	
	function file_getlinks($path,$open=TRUE){
		$buffer='';
		$dir=is_dir($path);
		//Add the links we always want to have
		if($open){
			$buffer.=
				'<a href="' . selfurl() . '?action=' . ($dir?'shell':'open') . '&amp;path=' . he(urlencode($path)) . '" title="Open for viewing, editing and properties">[OPEN]</a> ';
		}
		$buffer.=
			'<a href="' . selfurl() . '?action=rename&amp;path=' . he(urlencode($path)) . '" title="Rename, move or copy this file">[R/M/C]</a> ' .
			'<a href="' . selfurl() . '?action=delete&amp;path=' . he(urlencode($path)) . '" title="Delete this file">[DEL]</a> ' .
			'<a href="' . selfurl() . '?action=zip&amp;mode=zip&amp;dest=.&amp;file=' . he(urlencode($path)) . '" title="Compress this directory">[ZIP]</a> ' .
			'<a href="' . selfurl() . '?action=encrypt&amp;path=' . he(urlencode($path)) . '" title="Encrypt or decrypt">[ENC/DEC]</a> ';
		
		//Add specific links
		if(is_dir($path)){
			$buffer.=
				'<a href="' . selfurl() . '?action=size&amp;path=' . he(urlencode($path)) . '" title="Calculate the total size of this directory">[SIZE]</a> ';
		}
		return trim($buffer);
	}
	
	//Lists files in thumbnail view
	function file_thumbs($path,$list){
		$rows=array('file'=>array(),'dir'=>array(NULL),'invalid'=>array());
		foreach($list as $entry){
			$full=realpath($path . DIRECTORY_SEPARATOR . $entry);
			if($entry!=='.'){
				$isFile=@is_file($full);
				$isDir=@is_dir($full);
				$isLink=@is_link($full);
				$isValid=$isFile||$isDir||$isLink;
				if($isValid){
					if($isFile){
						$mediaType=getMediaType($full);
						$openstart='<a href="' . selfurl() . '?action=open&amp;path=' . he(urlencode($full)) . '" title="' . he($entry) . '">';
						switch($mediaType){
							case 'image':
								$media_segment=$openstart .
									'<img loading="lazy" src="' . selfurl() . '?action=thumb&amp;path=' . he(urlencode($full)) .
									'" alt="' . he($entry) . '" title="' . he($entry) . '" /></a>';
								break;
							case 'video':
								$media_segment='<video loop preload="none" controls src="'.selfurl().'?action=download&amp;path=' .
									he(urlencode($full)) . '"></video>' . $openstart .
									he(formatName($entry)) . '</a><br />';
								break;
							case 'audio':
								$media_segment='<audio loop preload="none" controls src="'.selfurl().'?action=download&amp;path=' .
									he(urlencode($full)) . '"></audio>' . $openstart .
									he(formatName($entry)) . '</a><br />';
								break;
							default:
								$media_segment=$openstart . he($entry) . '</a><br />';
								break;
						}
						$rows['file'][]='<div>' . $media_segment . file_getlinks($full,FALSE) . '</div>';
					}
					else{
						if($isDir){
							$opt=TRUE;
							if($entry==='..'){
								$elements='<parent>';
								$opt=FALSE;
							}
							else{
								$elements=@scandir($full);
								if(is_array($elements)){
									$elements=count($elements)-2;
									if($elements<1){
										$elements='<empty>';
									}
									else{
										$elements.=' element' . ($elements===1?'':'s');
									}
								}
								else{
									$elements=NULL;
									$opt=FALSE;
								}
							}
						}
						if($elements===NULL){
							$rows['invalid'][]='<div> Unable to enumerate directory contents (Access denied?)</div>';
						}
						else{
							$links=file_getlinks($full,FALSE);
							$row='<div><a class="filename" href="' . selfurl() . '?action=shell&amp;view=thumbs&amp;path=' . he(urlencode($full)) . '" title="' . he($entry) . '">' . he(formatName($entry,FALSE)) . '</a>' .
									($isDir?he($elements):'LINK') . '<br />' .
									he($entry==='..'?'':userDate(filemtime($full))) . '<br />' .
									($opt?$links:'') . '</div>';
							//Ensure that the .. entry is always first
							if($entry==='..'){
								$rows['dir'][0]=$row;
							}
							else{
								$rows['dir'][]=$row;
							}
						}
					}
				}
				else{
					$rows['invalid'][]='<div>' . he($entry) . '<br />
					Unable to determine element type (probably a locked file) (Access denied?)</div>';
				}
			}
		}
		return '<div class="thumb">' . implode(PHP_EOL,$rows['dir']) . implode(PHP_EOL,$rows['file']) . implode(PHP_EOL,$rows['invalid']) . '</div>';
	}

	function file_table($path,$list){
		$config=getConfig();
		$buffer='';
		//These two lines allow the shell to "cut corners" if it takes a long time
		$cutoff=time()+15;
		$skipcount=av($config,'skipcount')===TRUE;

		$shortcut=FALSE;
		$rows=array('file'=>array(),'dir'=>array(NULL),'invalid'=>array());
		foreach($list as $entry){
			$shortcut|=time()>=$cutoff;
			$full=realpath($path . DIRECTORY_SEPARATOR . $entry);
			if($entry!=='.'){
				$isFile=@is_file($full);
				$isDir=@is_dir($full);
				$isLink=@is_link($full);
				$isValid=$isFile||$isDir||$isLink;

				if($isValid){
					if($isFile){
						if($shortcut || $skipcount){
							$rows['file'][]='<tr class="table-file"><td><a class="filename" href="' . selfurl() . '?action=open&amp;path=' . he(urlencode($full)) . '" title="' . he($entry) . '">' . he(formatName($entry)) . '</a></td>' .
								'<td><span title="Unknown size">' . he($skipcount?'<skipped>':'<timeout>') . '</span></td>' .
								'<td>' . he($skipcount?'<skipped>':'<timeout>') . '</td><td>' .
								file_getlinks($full) . '</td></tr>';
						}
						else{
							$size=filesize($full);
							$rows['file'][]='<tr class="table-file"><td><a class="filename" href="' . selfurl() . '?action=open&amp;path=' . he(urlencode($full)) . '" title="' . he($entry) . '">' . he(formatName($entry)) . '</a></td>' .
								'<td><span title="' . $size . ' bytes">' . he(formatSize($size)) . '</span></td>' .
								'<td>' . he(userDate(filemtime($full))) . '</td><td>' .
								file_getlinks($full) . '</td></tr>';
						}
					}
					else{
						if($isDir){
							$opt=TRUE;
							if($entry==='..'){
								$elements='<parent>';
								$opt=FALSE;
							}
							else{
								if($skipcount){
									$elements='<skipped>';
								}
								elseif($shortcut){
									$elements='<timeout>';
								}
								else{
									$elements=@scandir($full);
									if(is_array($elements)){
										$elements=count($elements)-2;
										if($elements<1){
											$elements='<empty>';
										}
										else{
											$elements.=' element' . ($elements===1?'':'s');
										}
									}
									else{
										$elements=NULL;
										$opt=FALSE;
									}
								}
							}
						}
						if($elements===NULL){
							$rows['invalid'][]='<tr class="table-invalid"><td title="' . he($entry) . '">' . he(formatName($entry,FALSE)) . '</td><td title="Unable to enumerate directory contents">? (Access denied)</td><td>' . userDate(filemtime($full)) . '</td><td>&nbsp;</td></tr>';
						}
						else{
							$links=
								'<a href="' . selfurl() . '?action=size&amp;path='.he(urlencode($full)).'" title="Calculate the total size of this directory">[SIZE]</a> '.
								'<a href="' . selfurl() . '?action=rename&amp;path='.he(urlencode($full)).'" title="Rename, move or copy this directory">[R/M/C]</a> '.
								'<a href="' . selfurl() . '?action=delete&amp;path='.he(urlencode($full)).'" title="Delete this directory and all contents">[DEL]</a> ' .
								'<a href="' . selfurl() . '?action=zip&amp;mode=zip&amp;dest=.&amp;file='.he(urlencode($full)).'" title="Compress this directory">[ZIP]</a>';
							$row='<tr class="table-dir"><td><a class="filename" href="' . selfurl() . '?action=shell&amp;path=' . he(urlencode($full)) . '" title="' . he($entry) . '">' . he(formatName($entry,FALSE)) . '</a></td>' .
									'<td>' . ($isDir?he($elements):'LINK') . '</td>' .
									'<td>' . he($entry==='..'?'':userDate(filemtime($full))) . '</td><td>' .
									($opt?$links:'') . '</td></tr>';
							//Ensure that the .. entry is always first
							if($entry==='..'){
								$rows['dir'][0]=$row;
							}
							else{
								$rows['dir'][]=$row;
							}
						}
					}
				}
				else{
					$rows['invalid'][]='<tr class="table-invalid"><td>' . he($entry) . '</td><td title="Unable to determine element type (probably a locked file)">? (Access denied)</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
				}
			}
		}

		if($shortcut){
			$buffer.='<p class="err">Enumeration took a long time so some details were skipped to avoid a timeout (list is still complete)</p>';
		}

		//Write lines in order (directories first)
		$buffer.='<table><tr><th>Name</th><th>Info</th><th>Last Modified</th><th>Action</th></tr>' . PHP_EOL;
		$buffer.=implode(PHP_EOL,$rows['dir']) . implode(PHP_EOL,$rows['file']) . implode(PHP_EOL,$rows['invalid']);
		$buffer.='</table>' . PHP_EOL;
		return $buffer;
	}
