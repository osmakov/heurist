<?php
  //scan all code files in folder - php, inc, js, html, css, txt, sql
  // and repalce "\r\n" to "\n"
set_time_limit(0);

$fix = false;

if (!is_array($argv) || empty($argv)){
    $fix = (@$_GET['fix']==1);
    //print 'fix='.@$_GET['fix'];
    $iscmd = false;
}else{
    $iscmd = true;
    foreach ($argv as $arg){
        //echo "\n".$arg.';';
        if($arg=='fix=1'){
            $fix = true;
        }
    }
}

$lnbr = $iscmd?"\n":'<br>';


$allowed = array('php', 'inc', 'js', 'html', 'css', 'txt', 'sql');
$ext1 = DIRECTORY_SEPARATOR.'external'.DIRECTORY_SEPARATOR;
$ext2 = DIRECTORY_SEPARATOR.'ext'.DIRECTORY_SEPARATOR;
$ext3 = '.git'.DIRECTORY_SEPARATOR;
  //scan all folders

//$path = HEURIST_FILESTORE_DIR.'entity/'.$entity_name.'/';
if($fix){
print $lnbr.'Fix files';
}else{
print $lnbr.'Search files';
}

print $lnbr.'Check folder :'.realpath(dirname(__FILE__));

$directory = new \RecursiveDirectoryIterator(realpath(dirname(__FILE__)));
$iterator = new \RecursiveIteratorIterator($directory);

$total = 0;
$dosed = array();



foreach ($iterator as $filepath => $info) {
      if(!$info->isFile()) {continue;}

      //$filename = $info->getFilename();
      $pathname = $info->getPath();
      if(strpos($pathname,$ext3)>0 || strpos($pathname,$ext1)>0 || strpos($pathname,$ext2)>0 ) {continue;}


      //works since PHP 5.3.6 only $extension = $info->getExtension();
      $extension = pathinfo($info->getFilename(), PATHINFO_EXTENSION);

      if(in_array($extension, $allowed)){
          //
          $fullpath = $info->getPathname();
          //print '<br>'.$fullpath;
          if(findDosLineDelimiters($fullpath, $fix)){
            $dosed[] = $fullpath;
            //break;
          }
          $total++;
      }
}
print $lnbr.'total: '.$total;
print $lnbr.'with dos line delimeter:'.count($dosed);
if(!isEmptyArray($dosed)){
/*
    foreach ($dosed as $filename){
        print $lnbr.$filename;
    }
*/
if(!$fix && !$iscmd) {
?>
<div><a href="win_to_nix.php?fix=1">FIX?</a><div>
<?php
}
}

print $lnbr;

//read line by line and find dos line delimeters
function findDosLineDelimiters($filename, $fix){
    global $lnbr;

    $content = file_get_contents($filename);
    if(strpos($content,"\r\n")!==false){
        if($fix){
            $content = str_replace("\r\n","\n", $content);
            file_put_contents($filename, $content);
        }
        print $lnbr.$filename;
        $res = true;
    }else{
        $res = false;
    }
    unset($content);
    return $res;
}
?>
