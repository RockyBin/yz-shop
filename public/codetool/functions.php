<?
function emptydir($path)
{
	if(is_dir($path))
	{
		if ($dir = opendir($path)) {
			while (($file = readdir($dir)) !== false) {
				if($file != '.' && $file != '..'){
					if(is_dir("$path/$file")){
						emptydir("$path/$file");
						$del_dir = rmdir("$path/$file");
						//if($del_dir) echo "delete Directory $path/$file <font color='green'>OK</font><br>";
						//else echo "delete Directory $path/$file <font color='red'>Fail</font><br>";
					} else {
						$del = unlink("$path/$file");
						//if($del)echo "delete File $path/$file <font color='green'>OK</font><br>";
						//else echo "delete File $path/$file <font color='red'>Fail</font><br>";
					}
				}
			}
			closedir($dir);
		}
	}
}

function deletedir($path){
	if(file_exists($path)){
	   emptydir($path);
	   rmdir($path);
	}
}

function mkdirex($path){
	$path = preg_replace('@[\/]+@',DIRECTORY_SEPARATOR,$path);
	$arr = explode(DIRECTORY_SEPARATOR,$path);
	for($i = 0;$i < count($arr);$i++){
		$arrtmp = array_slice($arr, 0, $i + 1);
		$dir = implode(DIRECTORY_SEPARATOR,$arrtmp);
		if(!is_dir($dir)){
			if(!is_dir($dir)) mkdir($dir);
		}
	}
	return true;
}
?>