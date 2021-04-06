<?php
	/*
		Contains functions to deal with media types
	*/
	//Thumbnail quality
	define('THUMB_QUALITY',70);
	//In order of quality (least quality but faster rendering first):
	//IMG_NEAREST_NEIGHBOUR, IMG_BILINEAR_FIXED, IMG_BICUBIC, IMG_BICUBIC_FIXED
	define('THUMB_MODE',IMG_BICUBIC);
	//Maximum image dimensions
	define('THUMB_SIZE',180);
	//Simple placeholder image
	define('IMG_PLACEHOLDER','iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAJ0lEQVR42m' .
		'OcOXMmAzZw9uxZrOKMoxpooiEtLQ2rhLGx8agG+mkAACpiL/lWCxuBAAAAAElFTkSuQmCC');

	//Mime types with media contents
	define('IMAGE_TYPES',array('image/png','image/jpeg','image/gif','image/x-ms-bmp','image/vnd.microsoft.icon'));
	define('AUDIO_TYPES',array('audio/mpeg','audio/ogg','audio/x-wav','audio/flac'));
	define('VIDEO_TYPES',array('video/mp4','video/webm'));

	//File extension regex mask for type detection
	define('IMAGE_TYPES_FAST','#\.(ico|png|jpe?g|jps|mpo|bmp|gif)(\.(bak|tmp|~))?$#i');
	define('AUDIO_TYPES_FAST','#\.(mp3|m4a|aac|ogg|wav|flac)(\.(bak|tmp|~))?$#i');
	define('VIDEO_TYPES_FAST','#\.(mp4|webm)(\.(bak|tmp|~))?$#i');

	//Detect media type of file
	function getMediaType($file){
		if(is_file($file)){
			$cheat=av(getConfig(),'fastmedia',FALSE);
			if($cheat){
				if(preg_match(IMAGE_TYPES_FAST,$file)){
					return 'image';
				}
				if(preg_match(AUDIO_TYPES_FAST,$file)){
					return 'audio';
				}
				if(preg_match(VIDEO_TYPES_FAST,$file)){
					return 'video';
				}
			}
			else{
				$types=array(
					'image'=>IMAGE_TYPES,
					'audio'=>AUDIO_TYPES,
					'video'=>VIDEO_TYPES
				);
				$mime=mime_content_type($file);
				foreach($types as $k=>$v){
					if(in_array($mime,$v)){
						return $k;
					}
				}
			}
		}
		return 'other';
	}

	//Generate a thumbnail of an image
	function thumbImage(){
		$path=realpath(av($_GET,'path','/'));
		if($path && is_file($path)){
			handleCache(filemtime($path));
			$type=mime_content_type($path);
			if(!is_string($type)){
				$type='application/octet-stream';
			}
			//Icons are supported in the browser but not by php
			if($type==='image/vnd.microsoft.icon'){
				sendRange($path);
				return TRUE;
			}
			if($img=@imagecreatefromstring(@file_get_contents($path))){
				$w=imagesx($img);
				$h=imagesy($img);
				$factor=min(THUMB_SIZE/$w,THUMB_SIZE/$h);
				if($factor<1.0){
					if($new=imagescale($img,$w*$factor|0,$h*$factor|0,THUMB_MODE)){
						if(!$new){
							die("Unable to scale image");
						}
						imagedestroy($img);
						$img=$new;
					}
				}
				setHeader('Content-Type','image/jpeg');
				setHeader('Content-Disposition','inline; filename="preview.jpg"');
				imagejpeg($img,NULL,THUMB_QUALITY);
				imagedestroy($img);
				return TRUE;
			}
			else{
				setHeader('Content-Type','image/png');
				setHeader('Content-Disposition','inline; filename="placeholder.png"');
				echo image_text('Failed to generate preview',20);
				return FALSE;
			}
		}
		return FALSE;
	}

	//Preview image
	function previewImage(){
		$path=realpath(av($_GET,'path','/'));
		if($path && is_file($path)){
			$type=mime_content_type($path);
			if(!is_string($type)){
				$type='application/octet-stream';
			}
			handleCache(filemtime($path));
			if(image_available() && $img=@imagecreatefromstring(file_get_contents($path))){
				setHeader('Content-Type','image/jpeg');
				$w=imagesx($img);
				if($w>500){
					if($new=imagescale($img,500,-1,THUMB_MODE)){
						imagedestroy($img);
						$img=$new;
					}
				}
				setHeader('Content-Disposition','inline; filename="preview.jpg"');
				imagejpeg($img,NULL,THUMB_QUALITY);
			}
			else{
				setHeader('Content-Type',$type);
				setHeader('Content-Disposition','inline; filename="' . basename($path) . '"');
				sendRange($path);
			}
		}
	}

	//Checks if the image functions are available
	function image_available(){
		return function_exists('imagecreate');
	}

	//Generate placeholder image with text
	function image_text($lines,$border){
		if(!image_available()){
			return base64_decode(IMG_PLACEHOLDER);
		}
		$font=5;
		$w=0;
		$h=0;

		$cw=imagefontwidth($font);
		$ch=imagefontheight($font);

		if(!is_array($lines)){
			$lines=array_map('rtrim',explode("\n","$lines"));
		}
		else{
			$lines=array_values($lines);
		}

		foreach($lines as $line){
			$h+=$ch;
			$w=max($w,$cw*strlen($line));
		}

		$img=imagecreate($w+$border,$h+$border);
		$bg=imagecolorallocate($img,mt_rand(0,0xFF),mt_rand(0,0xFF),mt_rand(0,0xFF));
		$shadow=imagecolorallocate($img,0,0,0);
		$text=imagecolorallocate($img,0xFF,0xFF,0xFF);

		foreach($lines as $index=>$line){
			$x=floor($border/2);
			$y=floor($index*$ch+$border/2);
			imagestring($img,$font,$x-1,$y-1,$line,$shadow);
			imagestring($img,$font,$x-1,$y+1,$line,$shadow);
			imagestring($img,$font,$x+1,$y-1,$line,$shadow);
			imagestring($img,$font,$x+1,$y+1,$line,$shadow);
			imagestring($img,$font,$x,$y,$line,$text);
		}

		ob_start();
		imagepng($img,NULL,9);
		$data=ob_get_clean();
		imagedestroy($img);
		return $data;
	}
?>