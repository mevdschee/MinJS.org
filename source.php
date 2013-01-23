<?php
chdir('minjs');
$files = array('minjs_settings.php','minjs.html','minjs.php','backups/minjs.sql');
$file =isset($_GET['file'])?$_GET['file']:'';
if (!in_array($file,$files)) die('access denied');
if (!file_exists($file)) die('file not found: '.$file);
header('Content-Type: text/plain');
die(file_get_contents($file));