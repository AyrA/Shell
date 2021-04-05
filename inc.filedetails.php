<?php
	/*
		Contains functions that are used to view individual files
	*/

	//Adds links to force a file type change
	function textlink($path, $allow_pretend){
		$types=array('audio','video','image');
		$ret='<p><a href="' . he(selfurl()) . '?action=open&amp;path=' . he(urlencode($path)) . '&amp;force=text">Edit as text</a></p>';
		if($allow_pretend){
			$ret.='<p>Pretend  file is: ';
			foreach($types as $force){
				$ret.='<a href="' . he(selfurl()) . '?action=open&amp;path=' . he(urlencode($path)) .
				'&amp;force=' . he(urlencode($force)) . '">' . he($force) . '</a> ';
			}
		}
		return $ret;
	}

	//Opens a file for editing and to view properties
	function openFile(){
		$config=getConfig();
		$audio_ext=array('mp3','ogg','wav','flac');
		$video_ext=array('mp4','webm');

		$path=realpath(av($_GET,'path','/'));
		if(is_file($path)){

			if(av($_POST,'content')){
				$time=time();
				if(file_exists($path)){
					$time=filemtime($path);
				}
				@file_put_contents($path,av($_POST,'content'));
				if(av($_POST,'keeptime')==='y'){
					@touch($path,$time);
				}
			}
			clearstatcache();

			$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
			$url=weburl($path);
			$size=filesize($path);
			$forced_type=av($_GET,'force');
			$guessed_type=mime_content_type($path);
			$encrypted=enc_get_info($path)!==FALSE;
			//JSON is stored as "application/json" sometimes
			if(preg_match('#/json$#i',$guessed_type)){
				$guessed_type='text/json';
			}

			if(!is_string($guessed_type)){
				//Use text type for empty files
				$guessed_type=$size===0?'text/plain':'application/octet-stream';
			}
			switch($forced_type){
				case 'text':
					$handled_type='text/plain';
					break;
				case 'image':
					$handled_type='image/jpeg';
					break;
				case 'audio':
					$handled_type='audio/mpeg';
					break;
				case 'video':
					$handled_type='video/mp4';
					break;
				default:
					$handled_type=$guessed_type;
					break;
			}
			$basetype=strtolower(substr($handled_type,0,strpos($handled_type,'/')));

			$buffer='<h1>File: ' . he(basename($path)) . '</h1><table>';

			$buffer.='<tr><th>Full path</th><td>' . he(realpath($path)) . '</td></tr>';
			$buffer.='<tr><th>Size</th><td title="' . $size . ' bytes">' . he(formatSize($size)) . '</td></tr>';
			if($size<MAX_HASH_SIZE){
				$buffer.='<tr><th>SHA1</th><td>' . he(sha1_file($path)) . '</td></tr>';
			}
			else{
				$buffer.='<tr><th>SHA1</th><td>File too large</td></tr>';
			}
			if($url){
				$buffer.='<tr><th>URL</th><td><a href="' . he($url) . '">' . he($url) . '</a></td></tr>';
			}
			else{
				$buffer.='<tr><th>URL</th><td>Unknown or not under web root</td></tr>';
			}
			$buffer.='<tr><th>Last edited</th><td>' . he(userDate(filemtime($path))) . '</td></tr>';
			$buffer.='<tr><th>Guessed type</th><td>' . he($guessed_type) . '</td></tr>';
			$buffer.='<tr><th>Actions</th><td>'.
				'<a href="' . selfurl() . '?action=download&amp;path=' . he(urlencode($path)) . '" title="Downlaod the file to your computer">[DOWNLOAD]</a> ' .
				'<a href="' . selfurl() . '?action=rename&amp;path=' . he(urlencode($path)) . '" title="Rename, move or copy this file">[R/M/C]</a> ' .
				'<a href="' . selfurl() . '?action=delete&amp;path=' . he(urlencode($path)) . '" title="Delete this file">[DELETE]</a> ' .
				($encrypted?'':'<a href="' . selfurl() . '?action=encrypt&amp;path=' . he(urlencode($path)) . '" title="Encrypt">[ENC]</a> ') .
				'<a href="' . selfurl() . '?action=zip&amp;mode=zip&amp;dest=.&amp;file=' . he(urlencode($path)) . '" title="Compress this file into a zip">[ZIP]</a> ' .
				'<a href="' . selfurl() . '?action=zip&amp;mode=gz&amp;dest=.&amp;file=' . he(urlencode($path)) . '" title="Compress this file into gzip">[GZ]</a></td></tr>';
			if($basetype==='text'){
				$buffer.='<tr><th>Encoding</th><td>' . he(detectEncoding(file_get_contents($path,FALSE,NULL,0,5))) . '</td></tr>';
			}
			if($encrypted){
				$buffer.='<tr><th>Encryption</th><td>Encrypted. ' .
				'<a href="' . selfurl() . '?action=encrypt&amp;path=' . he(urlencode($path)) . '" title="Encrypt or decrypt">Click here</a> ' .
				'to decrypt</td></tr>';
			}
			//$buffer.='<tr><th></th><td></td></tr>';
			$buffer.='</table>';
			$buffer.='<div class="backlink-container"><a href="' . selfurl() . '?action=shell&amp;path=' . urlencode(dirname($path)) . '">&lt;&lt;Go Back</a></div>';
			if($size>0 && $guessed_type!==$handled_type){
				if($basetype==='text'){
					$buffer.='<div class="alert alert-err">You forced a different type. File may become unusable if saved</div>';
				}
				else{
					$buffer.='<div class="alert alert-err">You forced a different type. File might not work</div>';
				}
			}
			switch($basetype){
				case 'inode':
				case 'text':
					$data=av($_POST,'content',file_get_contents($path,FALSE,NULL,0,min($size,MAX_FILE_EDIT+1)));
					if(strlen($data)>MAX_FILE_EDIT){
						$buffer.='<div class="alert alert-err">File too large to be edited. First '. MAX_FILE_EDIT . ' bytes are shown (misses '.($size-MAX_FILE_EDIT).' bytes)</div>';
						$buffer.='<div><textarea rows="25" class="max accept-tab" readonly>' . he(substr($data,0,MAX_FILE_EDIT)) . '</textarea><br />' .
						'</div>';
					}
					else{
						$buffer.='<div><form method="post"><textarea rows="25" class="max accept-tab" name="content">' . he($data) . '</textarea><br />' .
						'<input type="submit" value="Save Changes" /><input type="reset" value="Discard Changes" />' .
						'<label><input type="checkbox" name="keeptime" value="y" ' . (av($config,'keep-modified')===TRUE?'checked':'') . ' /> Try to keep modify timestamp</label></form></div>';
					}
					break;
				case 'image':
					$buffer.='<div><img
						src="' . selfurl() . '?action=preview&amp;path=' . urlencode($path) . '" alt="Preview image"
						data-original="' . selfurl() . '?action=download&amp;path=' . urlencode($path) . '" /></div>
						<p>Click to switch between preview and full size</p>';
					break;
				case 'audio':
					if(preg_match(AUDIO_TYPES_FAST,$path)){
						$buffer.='<div><audio loop src="' . selfurl() . '?action=download&amp;path=' . urlencode($path) . '" controls></audio></div>';
					}
					else{
						$buffer.='<h2 class="err">This audio file can\'t be played in a web browser directly.</h2>';
					}
					break;
				case 'video':
					if(preg_match(VIDEO_TYPES_FAST,$path)){
						$buffer.='<div><video loop src="' . selfurl() . '?action=download&amp;path=' . urlencode($path) . '" controls></video></div>';
					}
					else{
						$buffer.='<h2 class="err">This video file can\'t be played in a web browser directly.</h2>';
					}
					break;
				default:
					if($size===0){
						$buffer.='<h2 class="err">This File is empty</h2>' .
						textlink($path, FALSE);
					}
					elseif($ext==='zip'){
						$buffer.=showZip($path);
					}
					elseif($guessed_type===MIME_DB3){
						$buffer.='<p>This is an SQLite file. A maximum of 100 entries of every table are shown below</p>';
						$buffer.=db3_dump($path,100);
					}
					else{
						$buffer.='<h2 class="err">This file is not editable</h2>'.
						textlink($path, TRUE) .
						showHex($path,$size,$guessed_type===MIME_GZ);
					}
			}
			$buffer.='<hr /><div class="backlink-container"><a href="' . selfurl() . '?action=shell&amp;path=' . urlencode(dirname($path)) . '">&lt;&lt;Go Back</a></div>';
			exit(html($buffer));
		}
		else{
			exit(html(
				'<h1 class="err">The selected object is not a file</h2><p>' . he($path) . '</p>' .
				'<a href="#" class="backlink">&lt;&lt;Go Back</a>'
			));
		}
	}

	//Show zipped file
	function showZip($path){
		$buffer='';
		$name=basename($path);
		if(strpos($name,'.')>0){
			$name=substr($name,0,strpos($name,'.'));
		}
		$zipdata=zip_enum($path);
		if($zipdata){
			if($comment=av($zipdata,'comment')){
				$buffer.='<h2>Comment:</h2><pre>' . he($comment) . '</pre>';
			}
			if(count($zipdata['entries'])===0){
				$buffer.='<i>This archive is empty</i>';
			}
			else{
				$exturl=selfurl() . '?action=unzip&file=' . he($path) . '&dest=';
				$buffer.='<h2>Actions:</h2>';
				$buffer.=
					'<p><a href="' . $exturl . he(dirname($path)) . '">Extract here</a></p>' .
					'<p><a href="' . $exturl . he(dirname($path) . DIRECTORY_SEPARATOR . $name) . '">Extract to new subdirectory &quot;' . he($name) . '&quot;</a></p>';
				$buffer.='<p class="err">Existing objects will be overwritten!</p>';
				$buffer.='<h2>Contents:</h2>';
				$buffer.='<table><tr><th>Name</th><th>Size</th><th>Compressed</th><th>Date</th><th>CRC</th></tr>';
				foreach($zipdata['entries'] as $zipentry){
					//Render directories in a different color
					if($zipentry['isdir']){
						$buffer.='<tr><td colspan="3"><span class="zip-dir">' . he($zipentry['name']) . '</span></td>';
					}
					else{
						$buffer.='<tr><td><span class="zip-file">' . he($zipentry['name']) . '</span>
						<td>' . he(formatSize($zipentry['size']['real'])) . '</td>
						<td>' . he(formatSize($zipentry['size']['compressed'])) . '</td>';
					}
					$buffer.='</td><td>' . he(userDate($zipentry['last-modified'])) . '</td>
					<td>' . he($zipentry['isdir']?'<dir>':$zipentry['crc']) . '</td></tr>';
				}
				$buffer.='</table>';
				$diff=$zipdata['size']['real']-$zipdata['size']['compressed'];
				$buffer.='<p>
					Total compressed size: ' . he(formatSize($zipdata['size']['real'])) . '<br />
					Total decompressed size: ' . he(formatSize($zipdata['size']['compressed'])) . '<br />
					Compression ratio: ' .
					round($zipdata['size']['compressed']/$zipdata['size']['real']*100,2) . '%' .
					'; ';
					if($diff>=0){
						$buffer.='Reduced by ' . he(formatSize($diff));
					}
					else{
						$buffer.='Increased by ' . he(formatSize(abs($diff)));
					}
					$buffer.='.</p>';
			}
		}
		else{
			$buffer.='<h2 class="err">This zip file is damaged or not a zip</h2>'.
			textlink($path, TRUE).
			showHex($path,$size);
		}
		return $buffer;
	}

	//Dump binary file contents
	function showHex($path,$size,$decodeGZ=FALSE){
		$decoded=FALSE;
		if($decodeGZ && mime_content_type($path)===MIME_GZ){
			$fp=gzopen($path,'rb');
			$decoded=TRUE;
		}
		else{
			$fp=fopen($path,'rb');
		}
		if($fp){
			$extracturl=selfurl() . '?action=unzip&file=' . urlencode($path) . '&dest=.';
			$data=fread($fp,min($size,MAX_FILE_EDIT));
			fclose($fp);
			return
				($decoded?'<p><i>You are looking at the decoded gzip contents</i> ':'') .
				($decoded?'<a href="' . he($extracturl) . '">Extract file</a></p>':'') .
				'<p>The hex dump of at most ' . MAX_FILE_EDIT . ' bytes is shown</p>' .
				'<pre class="hex">' . he(formatHex($data)) . '</pre>';
		}
		return '<b class="err">Unable to read ' . he($path) . '</b>';
	}
