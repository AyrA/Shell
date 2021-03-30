<?php
	/*
		Provides cryptographic functions
	*/
	//Algorithms matching this are stripped from the output.
	//They are unsafe or not practical to use
	define('CRYPTO_UNSAFE',array(
		//Outdated or impractical ciphers
		'#^(id|rc\d|seed|desx?)(-.+)?$#i',
		//ECB and XTS modes are not good methods for file encryption
		'#-(ecb|xts)$#i'
		));
	define('CRYPTO_DEFAULT','aes-256-gcm');
	define('CRYPTO_HEADER','CRYPT');
	define('CRYPTO_ROUNDS',50000);

	//Read big endian integer from file
	function enc_read_int($fp,$size){
		$ret=0;
		$val=fread($fp,$size);
		while(strlen($val)){
			$ret=$ret<<8;
			$ret+=ord($val);
			$val=substr($val,1);
		}
		return $ret;
	}

	//Write big endian integer to file
	function enc_write_int($fp,$num,$size){
		for($i=$size-1;$i>=0;$i--){
			fwrite($fp,chr(($num>>$i*8)&0xFF));
		}
	}

	//Get empty header
	function enc_get_header(){
		return array(
			'magic'=>FALSE,
			'mode'=>NULL,
			'iv'=>NULL,
			'salt'=>NULL,
			'rounds'=>NULL,
			'datalen'=>NULL,
			'tag'=>NULL
		);
	}

	//Writes length prefixed byte to a file stream
	function enc_write_prefixed_bytes($fp,$data,$size){
		$len=strlen($data);
		if($len>0){
			enc_write_int($fp,$len,$size);
			fwrite($fp,$data);
		}
		else{
			enc_write_int($fp,0,$size);
		}
	}

	//Reads length prefixed byte to a file stream
	function enc_read_prefixed_bytes($fp,$size){
		$len=enc_read_int($fp,$size);
		if($len>0){
			return fread($fp,$len);
		}
		return '';
	}

	//Writes header to a file
	function enc_write_header($path,$header,$data){
		if($fp=@fopen($path,'wb')){
			fwrite($fp,CRYPTO_HEADER);
			enc_write_prefixed_bytes($fp,$header['mode'],2);
			enc_write_prefixed_bytes($fp,$header['iv'],2);
			enc_write_prefixed_bytes($fp,$header['salt'],2);
			enc_write_int($fp,$header['rounds'],4);
			enc_write_prefixed_bytes($fp,$data,8);
			enc_write_prefixed_bytes($fp,$header['tag'],2);
			fclose($fp);
			return TRUE;
		}
		return 'Unable to open ' . $path;
	}

	//Get header of encrypted file
	function enc_get_info($path,$keep_data=FALSE){
		$header=enc_get_header();
		if(!is_file($path)){
			return FALSE;
		}
		if($fp=@fopen($path,'rb')){
			$hdr=fread($fp,strlen(CRYPTO_HEADER));
			if($header['magic']=CRYPTO_HEADER===$hdr){
				$header['mode']=enc_read_prefixed_bytes($fp,2);
				$header['iv']=enc_read_prefixed_bytes($fp,2);
				$header['salt']=enc_read_prefixed_bytes($fp,2);
				$header['rounds']=enc_read_int($fp,4);
				$header['datalen']=enc_read_int($fp,8);
				if($header['datalen']>0){
					if($keep_data){
						$header['data']=fread($fp,$header['datalen']);
					}
					else{
						fseek($fp,$header['datalen'],SEEK_CUR);
					}
				}
				$header['tag']=enc_read_prefixed_bytes($fp,2);
			}
			fclose($fp);
		}
		return $header['magic']?$header:FALSE;
	}

	//Renders the basic HTML page
	function enc_html(){
		$path=av($_GET,'path');
		if(!$path || !is_file($path)){
			die(html(homeForm() . '<h1 class="err">' . he($path?$path:'<unknown>') . ' not found or not a file</h1>' . backlink()));
		}
		$path=realpath($path);
		$err=NULL;

		$info=enc_get_info($path);
		$ciphers=enc_get_ciphers();

		$post=array();
		foreach(array('password2','password1','output','algo','mode') as $m){
			$post[$m]=av($_POST,$m);
		}

		//encrypt post request
		if($post['mode']==='encrypt'){
			if(strlen($post['password1'])===0 || $post['password1']!==$post['password2']){
				$err='Passwords do not match';
			}
			if(!in_array($post['algo'],$ciphers['all'],TRUE)){
				$err='Unknown cipher';
			}
			if(strlen($post['output'])===0){
				$err='No output file specified';
			}
			$out=dirname($path) . DIRECTORY_SEPARATOR . $post['output'];
			if(is_dir($out)){
				$err='Output must be a file';
			}
			if(!$err){
				$hdr=enc_file($post['password1'],$path,$out,$post['algo']);
				if(!$hdr){
					$err='Failed to encrypt your file';
				}
				elseif(is_string($hdr)){
					$err=$hdr;
				}
				else{
					redir(selfurl() . '?action=open&path=' . urlencode(realpath($out)));
					die(0);
				}
			}
		}

		//decrypt post request
		if($post['mode']==='decrypt'){
			if(strlen($post['password1'])===0){
				$err='Password is required';
			}
			if(!in_array($post['algo'],$ciphers['all'],TRUE)){
				$err='Unknown cipher';
			}
			if(strlen($post['output'])===0){
				$err='No output file specified';
			}
			$out=dirname($path) . DIRECTORY_SEPARATOR . $post['output'];
			if(is_dir($out)){
				$err='Output must be a file';
			}
			if(!$err){
				$result=dec_file($post['password1'],$path,$out);
				if($result===FALSE){
					$err='Failed to decrypt your file. No reason given';
				}
				elseif(is_string($result)){
					$err=$result;
				}
				elseif($result===TRUE){
					redir(selfurl() . '?action=open&path=' . urlencode(realpath($out)));
					die(0);
				}
			}
		}

		$encrypted=$info!==FALSE;
		$algo=isset($info['mode'])?$info['mode']:CRYPTO_DEFAULT;
		if($encrypted){
			$select=$info['mode'] .
				'<input type="hidden" name="algo" value="' . he($info['mode']) . '" />';
		}
		else{
		$select='<select name="algo" required><option value="">-- Please select --</option>' .
			'<option value="' . he(CRYPTO_DEFAULT) .  '">Recommended (' . he(CRYPTO_DEFAULT) .  ')</option>';
			//Don't show the optgroup for safe ciphers if no unsafe ciphers are available at all
			if(count($ciphers['safe'])>0 && count($ciphers['unsafe'])>0){
				$select.='<optgroup label="Safe ciphers">';
				foreach($ciphers['safe'] as $c){
					if($c===$algo){
						$select.='<option selected>' . he($c) . '</option>';
					}
					else{
						$select.='<option>' . he($c) . '</option>';
					}
				}
				$select.='</optgroup>';
			}
			//Unsafe ciphers are always below the safe ones
			if(count($ciphers['unsafe'])>0){
				$select.='<optgroup label="Unsafe ciphers">';
				foreach($ciphers['unsafe'] as $c){
					if($c===$algo){
						$select.='<option selected value="' . he($c) . '">' . he($c) . ' (unsafe)</option>';
					}
					else{
						$select.='<option value="' . he($c) . '">' . he($c) . ' (unsafe)</option>';
					}
				}
				$select.='</optgroup>';
			}
			$select.='</select>';
		}
		$header_tbl='';
		if($info){
			$header_tbl.='<h2>Encrypted file details</h2>';
			$header_tbl.='<table><tr><th>Mode</th><td>' . he($info['mode']) . '</td></tr>';
			$header_tbl.='<tr><th>iv</th><td>' . he(base64_encode($info['iv'])) . '</td></tr>';
			$header_tbl.='<tr><th>tag</th><td>' . he(base64_encode($info['tag'])) . '</td></tr>';
			$header_tbl.='<tr><th>salt</th><td>' . he(base64_encode($info['salt'])) . '</td></tr>';
			$header_tbl.='<tr><th>rounds</th><td>' . he($info['rounds']) . '</td></tr>';
			$header_tbl.='</table>';
		}
		else{
			$header_tbl.='<h2>File details</h2>';
			$header_tbl.='<table><tr><th>Path</th><td>' . he($path) . '</td></tr>';
			$header_tbl.='<tr><th>Size</th><td>' . formatSize(filesize($path)) . '</td></tr>';
			$header_tbl.='</table>';
		}


		$buffer=
			homeForm() .
			($err?'<h1 class="err">' . he($err) . '</h1>':'') .
			'<h1>Encrypt / Decrypt file</h1>' .
			'<p><a href="' . selfurl() . '?action=shell&amp;path=' . he(dirname($path)) . '">&lt;&lt; Go Back</a></p>' .
			$header_tbl .
			'<form method="post">' .
			'<input type="hidden" name="mode" value="' . he($encrypted?'decrypt':'encrypt') . '" />' .
			'' .
			'<table><tr><td>Algorithm</td><td>' .
			$select . '</td></tr><tr><td>Password</td><td>' .
			'<input type="password" name="password1" placeholder="Password" required /></td></tr>' .
			($encrypted?'':'<tr><td>Password</td><td><input type="password" name="password2" required placeholder="Password" /></td></tr>') .
			'<tr><td>Output</td><td><input required name="output" value="' . he(basename($path)) . '" /></td></tr>' .
			'<tr><td>&nbsp;</td><td><input type="submit" value="' . he(($encrypted?'decrypt':'encrypt')) . '" /></td></tr>' .
			'</table>' .
			'<i>If you don\'t change the output file name it will overwrite the input</i>' .
			'' .
			'</form>';
		die(html($buffer));
	}

	//Gets all ciphers that are considered secure
	function enc_get_ciphers(){
		$enc=openssl_get_cipher_methods();
		$algo=array('safe'=>array(),'unsafe'=>array());
		$allow_unsafe=av(getConfig(),'unsafe-crypto',FALSE)===TRUE;
		$c=count($enc);
		for($i=0;$i<$c;$i++){
			for($j=0;$j<count(CRYPTO_UNSAFE);$j++){
				if(preg_match(CRYPTO_UNSAFE[$j],$enc[$i])){
					if($allow_unsafe){
						$algo['unsafe'][]=$enc[$i];
					}
					else{
						unset($enc[$i]);
						break;
					}
				}
			}
		}
		//Add all unprocessed algorithms to the safe list
		foreach($enc as $alg){
			if(!in_array($alg,$algo['unsafe'])){
				$algo['safe'][]=$alg;
			}
		}
		$algo['all']=array_merge($algo['safe'],$algo['unsafe']);
		sort($algo['all']);
		sort($algo['safe']);
		sort($algo['unsafe']);
		return $algo;
	}

	//Gets the key size from an algorithm
	function enc_get_keysize($algo){
		if(preg_match('#^\w+-(\d+)#',$algo,$m)){
			return $m[1]/8;
		}
		return 128/8;
	}

	//Encrypts a file and returns the file header
	function enc_file($password,$in,$out,$mode=CRYPTO_DEFAULT){
		$header=enc_get_header();
		$header['mode']=$mode;
		$header['salt']=enc_random(32);
		$header['rounds']=CRYPTO_ROUNDS;
		$header['iv']=enc_random(openssl_cipher_iv_length($mode));
		$header['key']=enc_kdf($password,$header['salt'],enc_get_keysize($mode),$header['rounds']);
		$data=openssl_encrypt(file_get_contents($in),$mode,$header['key'],OPENSSL_RAW_DATA,$header['iv'],$header['tag']);
		if($data===FALSE){
			return openssl_error_string();
		}
		$header['datalen']=strlen($data);
		$ret=enc_write_header($out,$header,$data);
		return is_string($ret)?$ret:$header;
	}

	//Decrypts a file and returns the file header
	function dec_file($password,$in,$out){
		$header=enc_get_info($in,TRUE);
		if(!is_array($header)){
			return 'File does not seems to be encrypted';
		}
		$header['key']=enc_kdf($password,$header['salt'],enc_get_keysize($header['mode']),$header['rounds']);
		$data=openssl_decrypt(
			$header['data'],
			$header['mode'],
			$header['key'],
			OPENSSL_RAW_DATA,
			$header['iv'],
			$header['tag']);
		if($data===FALSE){
			$str=openssl_error_string();
			return
				is_string($str) && strlen($str)>0?
				$str:
				'Openssl failed without giving a reason. Likely wrong password.';
		}
		$count=@file_put_contents($out,$data);
		return $count>0?TRUE:'Unable to write to ' . $out;
	}

	function enc_random($count){
		return openssl_random_pseudo_bytes($count);
	}

	function enc_kdf($password,$salt,$length,$rounds=CRYPTO_ROUNDS){
		return openssl_pbkdf2($password,$salt,$length,$rounds,'sha1');
	}
