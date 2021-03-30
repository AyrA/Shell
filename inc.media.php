<?php
	/*
		Contains functions to deal with media types
	*/
	//Thumbnail quality
	define('THUMB_QUALITY',70);
	//In order of quality (least quality but faster rendering first):
	//IMG_NEAREST_NEIGHBOUR, IMG_BILINEAR_FIXED, IMG_BICUBIC, IMG_BICUBIC_FIXED
	define('THUMB_MODE',IMG_BICUBIC);
	
	//Mime types with media contents
	define('IMAGE_TYPES',array('image/png','image/jpeg','image/gif','image/x-ms-bmp'));
	define('AUDIO_TYPES',array('audio/mpeg','audio/ogg','audio/x-wav','audio/flac'));
	define('VIDEO_TYPES',array('video/mp4','video/webm'));

	//File extension regex mask for type detection
	define('IMAGE_TYPES_FAST','#\.(png|jpe?g|jps|mpo|bmp|gif)(\.(bak|tmp|~))?$#i');
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
			if($img=@imagecreatefromstring(file_get_contents($path))){
				$w=imagesx($img);
				$h=imagesy($img);
				$factor=min(180/$w,180/$h);
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
			setHeader('Content-Type',$type);
			handleCache(filemtime($path));
			if($img=@imagecreatefromstring(file_get_contents($path))){
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
				setHeader('Content-Disposition','inline; filename="' . basename($path) . '"');
				sendRange($path);
			}
		}
	}
?>