<?php
	/*
		Provides functions for updating the shell
	*/

	//Tests if automated update checks are possible
	function update_cancheck(){
		return function_exists('curl_init');
	}

	//Checks for new versions
	function update_check(){
		$config=getConfig();
		if(is_array(av($config,'version-cache')) && $config['version-cache']['time']>time()-86400){
			return $config['version-cache']['data'];
		}
		$data=http_get('https://api.github.com/repos/AyrA/Shell/releases',array('Accept'=>'application/vnd.github.v3+json'));
		if($data['success']){
			$tags=json_decode($data['response'],TRUE);
			if(!is_array($tags)){
				return 'Github response was not of the expected type. Expected array, got ' . gettype($tags);
			}
			$ret=array();
			$vmax=NULL;
			foreach($tags as $tag){
				$version=substr(av($tag,'tag_name'),1);
				if($vmax){
					$vmax=version_compare($vmax,$version)<0?$version:$vmax;
				}
				else{
					$vmax=$version;
				}
				//Ignore drafts
				if(!av($tag,'draft')){
					$ret[$version]=array(
						'desc'=>av($tag,'body'),
						'download'=>av($tag,'zipball_url'),
						'title'=>av($tag,'name'),
						'date'=>strtotime(av($tag,'published_at')),
					);
				}
			}
			$config['version-cache']=array('time'=>time(),'data'=>array('max'=>$vmax,'versions'=>$ret));
			setConfig($config);
			return $config['version-cache']['data'];
		}
		return FALSE;
	}

	//Prepares for an update
	function update_prepare(){
		//$temp=freeName(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shell');
		//mkdir($temp);
		return FALSE;
	}

	//Applies an update
	function update_apply(){
		//TODO
		return FALSE;
	}

	//Shows update HTML
	function showUpdate(){
		//TODO
		$buffer='<h1>Install update</h1><p>Not implemented</p>';
		exit(html($buffer));
	}
