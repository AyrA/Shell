<?php
	/*
		Primary shell entry point
	*/
	error_reporting(E_ALL);
	//Debug mode
	define('DEBUG',is_file(__DIR__ . DIRECTORY_SEPARATOR . '.debug'));

	//Configuration file
	//Always use an absolute path because some pages may change the working directory
	//Note: This line will be reset to the default when you update.
	define('CONFIG_FILE',__DIR__ . DIRECTORY_SEPARATOR . 'inc.config.php');

	foreach(glob('inc.*.php') as $f){
		require($f);
	}
	webroot();
	$config=getConfig();

	if(isRobot() && av($config,'allowbots',FALSE)!==TRUE){
		header('HTTP/1.1 404 Not Found');
		exit(0);
	}

	//Allow access from other resources
	//Usually you don't want this, but we may add some form of API at some point
	//setHeader('Access-Control-Allow-Origin','*');
	csp();

	$auth=isAuth();
	//Show password configuration form if no configuration is present
	if(!$auth && av($config,'password')===NULL){
		handlePassword();
	}
	//Check if it's a login attempt
	if(!$auth && av($_POST,'password'))
	{
		//Ensure a single IP can't brute-force the password
		//If the IP can't be obtained, time() allows one attempt from anywhere each second.
		//Each attempt still has to wait the full time, and attempts are queued as would be if the IP was available,
		//but time() creates a new queue each second.
		delay(av($_SERVER,'REMOTE_ADDR',time()),3);
		if(password_verify(av($_POST,'password'),av($config,'password'))){
			if(!setAuth()){
				exit('Failed to set session cookie');
			}
			$auth=TRUE;
		}
	}
	if($auth){
		//User logged in at this point
		switch(av($_GET,'action')){
			case 'info':
				//PHP information
				showInfo();
				break;
			case 'shell':
				//Directory browser and file editor
				showShell();
				break;
			case 'encrypt':
				//Directory browser and file editor
				enc_html();
				break;
			case 'exit':
				//Logout
				clearAuth();
				showLogin();
				break;
			case 'download';
				//File downloader
				exit(downloadFile());
				break;
			case 'delete';
				//Delete a file
				exit(deleteFileOrDir());
			case 'size';
				//Calculate directory size
				exit(calcSize());
			case 'preview';
				//Preview image
				exit(previewImage());
				break;
			case 'thumb';
				//Thumbnail
				exit(thumbImage());
				break;
			case 'open':
				openFile();
				break;
			case 'rename':
				renameFile();
				break;
			case 'unzip':
				unzipFile();
				break;
			case 'zip':
				zipFile();
				break;
			case 'terminal':
				showTerminal();
				break;
			case 'settings':
				showSettings();
				break;
			case 'update':
				showUpdate();
				break;
			default:
				//Main menu
				showMainMenu();
				break;
		}
	}
	else{
		showLogin();
	}

	//We should never reach the line below because we write using exit((html(...)))
	//Reaching this line would mean we forgot the exit() call somewhere.
	exit('Unexpected end of file');