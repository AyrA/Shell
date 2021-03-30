<?php
	/*
		Functions for showing the terminal
	*/

	//Shows the terminal to the user
	function showTerminal(){
		$path=realpath(av($_GET,'path',__DIR__));
		$config=getConfig();
		$cmd=av($_POST,'cmd');
		if(file_exists($path) && is_dir($path)){
			chdir($path);
		}
		else{
			$path=getcwd();
		}
		if($cmd){
			if(stripos($cmd,'cd ')===0 && strpbrk($cmd,'|&<>')===FALSE){
				$real=realpath(substr($cmd,3));
				if($real){
					exit(redir(selfurl() . '?action=terminal&path=' . urlencode($real)));
				}
			}
			elseif(preg_match('#^\w+#',$cmd,$matches)){
				$alias=av(av($config,'aliases'),strtolower($matches[0]));
				if($alias){
					$cmd=$alias . substr($cmd,strlen($matches[0]));
				}
			}
		}
		$buffer='<form method="get"><input type="hidden" name="action" value="terminal" />'.
				'<input type="text" name="path" value="'.he($path).'" size="80" required />' .
				'<input type="submit" value="Go" />
				<a href="' . selfurl() . '?action=shell&amp;path=' . urlencode($path) . '" class="btn">Shell</a>
				</form><form method="post">';
		if($cmd){
			$cmd=trim($cmd);
			if(stripos($cmd,'php:')===0){
				$result=array('stdout'=>eval(substr($cmd,4)),'stderr'=>'');
			}
			else{
				$result=run($cmd);
			}
			if(is_array($result)){
				$buffer.='<p>' . he($cmd) .
				' <a href="#" id="cmdcopylink" data-command="' . he($cmd) . '">re-use</a>' .
				'</p>';
				if(strlen($result['stdout'])>0 && strlen($result['stderr'])>0){
					$buffer.='<div class="cmd">
					<p>STDOUT</p>
					<textarea rows="20" readonly>' . he($result['stdout']) . '</textarea>
					</div>
					<div class="cmd">
					<p>STDERR</p>
					<textarea rows="20" readonly>' . he($result['stderr']) . '</textarea>
					</div>';
				}
				elseif(strlen($result['stdout'])>0){
					$buffer.='<p>This command wrote to STDOUT only</p><div class="fullcmd"><textarea rows="20" readonly>' . he($result['stdout']) . '</textarea></div>';
				}
				elseif(strlen($result['stderr'])>0){
					$buffer.='<p>This command wrote to STDERR only</p><div class="fullcmd"><textarea rows="20" readonly>' . he($result['stderr']) . '</textarea></div>';
				}
				else{
					$buffer.='<p class="err">There was no output from this command.
					It either has none or it silently failed.</p>';
				}
			}
			else{
				$buffer.='<div class="err">Unable to run the command ' . he($cmd) . '</div>';
			}
		}
		else{
			$buffer.='<div>
			<p>
				Use this shell to execute commands.
				They run in a terminal, so using terminal internals like "echo" will work.<br />
				<u>All commands run independently</u>.
				Doing things like setting variables has no effect on later commands.
				If you need that, use "&amp;&amp;" to chain commands together
				or use the shell itself to write a script.
			</p>
			<p>
				Comfort features:
			</p>
			<ul>
				<li>
					In the settings you can define aliases for your commonly used commands.
					They will be displayed as a drop down list to the command input field too.
				</li>
				<li>
					If you just type "<code>cd&nbsp;&lt;dir&gt;</code>" without any redirection or command chaining,
					Secure Shell will change the actual directory itself rather than letting the terminal do it.
					Don\'t use quotes around the directory name if you use this feature.
					You can always use the text box at the top or the shell itself to change to directories with difficult names.
				</li>
				<li>
					If your command starts with "<code>php:</code>", it will be run in an eval() of this PHP script.
					You can use this to execute entire PHP files using <code>php:require("filename.php");</code>
				</li>
				<li>PHP doesn\'t provides a combined output of commands in the way a real terminal does.
				The output for stdout and stderr are shown in seperate text boxes.
				Only the required text boxes are shown.
				This means you will see all output but the content of the two boxes
				will only be in order of the respective output stream and not in respect to the combined output.
				An easy way to observe this is in Windows by using <code>type *.php</code> for example.
				</li>
			</ul>
			</div>';
		}
		if(av($config,'stdoutonly')){
			$info='<small>Your current settings will cause stderr to be ignored</small>';
		}
		else{
			$info='';
		}
		$buffer.='
		<input type="text" name="cmd" required size="80" autofocus placeholder="Awaiting command" list="aliases" />
		<input type="submit" value="Run" />' . $info . '</form><datalist id="aliases"';
		$aliases=av($config,'aliases',array());
		if(is_array($aliases) && count($aliases)>0){
			$buffer.='<datalist id="aliases">';
			foreach($aliases as $k=>$v){
				$buffer.='<option value="' . he($k) . '">' . he("$k: $v") . '</option>"';
			}
			$buffer.='</datalist>';
		}
		exit(html(homeForm() . $buffer));
	}
