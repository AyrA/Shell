<?php
	/*
		SQLite handling
	*/
	define('DB3_ROWCOUNT',1000);
	define('MIME_DB3','application/x-sqlite3');

	function db3_getFields($sql){
		$ret=array();
		if(preg_match('#\((.+)\)#',$sql,$m)){
			$sql=array_map('trim',explode(',',$m[1]));
			foreach($sql as $field){
				if(preg_match('#^([^\s]+)\s+(.+)$#',$field,$m)){
					$ret[]=array('name'=>$m[1],'type'=>$m[2]);
				}
				else{
					$ret[]=$field;
				}
			}
			return $ret;
		}
		return FALSE;
	}

	function db3_getTables($db){
		$ret=array('tables'=>array(),'fields'=>array());
		$q=$db->query("SELECT name,sql FROM sqlite_master WHERE type='table'");
		while($row=$q->fetchArray(SQLITE3_ASSOC)){
			$ret['tables'][]=$row['name'];
			$ret['fields'][$row['name']]=db3_getFields($row['sql']);
		}
		$q->finalize();
		return $ret;
	}

	function db3_dumpTable($db,$tbl,$max=DB3_ROWCOUNT){
		$i=1;
		$list=db3_getTables($db);
		$enc=function($e){
			return htmlspecialchars($e,ENT_SUBSTITUTE);
		};
		$buffer='';
		if(in_array($tbl,$list['tables'])){
			$q=$db->query("SELECT * FROM $tbl LIMIT $max");

			$header=array('Index');
			foreach($list['fields'][$tbl] as $col){
				$header[]=$col['name'];
			}
			$buffer.='<h3 id="db3_table_' . he($tbl) . '">' . he($tbl) . '</h3><table><thead><tr>';
			foreach($header as $e){
				$buffer.='<th>' . he($e) . '</th>';
			}
			$buffer.='</tr></thead><tbody>';
			while($row=$q->fetchArray(SQLITE3_ASSOC)){
				$buffer.=
					'<tr><td>' . $i++ .'</td>' .
					'<td>' . implode('</td><td>',array_map($enc,$row)) .
					'</td></tr>';
			}
			$buffer.='</tbody></table>';
			$q->finalize();
		}
		else{
			$buffer='<p><b class="red">Unknown table: ' . he($tbl) . '</b></p>';
		}
		return $buffer;
	}

	function db3_dump($file,$maxEntries=DB3_ROWCOUNT){
		$db=new SQLite3($file,SQLITE3_OPEN_READONLY);
		$tables=db3_getTables($db)['tables'];
		$buffer='<h2>Tables:</h2>';
		//Table list for easy navigation
		foreach($tables as $tbl){
			$buffer.='<a href="#db3_table_' . he($tbl) . '">' . he($tbl) . '</a><br />';
		}
		//List contents
		foreach($tables as $tbl){
			$buffer.=db3_dumpTable($db,$tbl,$maxEntries);
		}
		$db->close();
		return $buffer;
	}