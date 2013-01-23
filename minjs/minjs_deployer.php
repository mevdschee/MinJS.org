<?php
require "minjs_settings.php";
$settings = $minjs_settings;
$apache = trim(shell_exec('whoami'));
$checks = array(array(
    'title'  => '"index.html" file integrity check',
    'status' => file_exists('index.html') && md5_file('index.html')=='22dedd850018ab06d3e5f8ecff904586',
    'action' => 'download_index_html',
  ), array(
    'title'  => '"minjs_deployer.php" file integrity check',
    'status' => file_exists('minjs_deployer.php') && md5_file('minjs_deployer.php')=='fcad0c49627f7d6dbf8e2dd4ccd48951',
    'action' => 'download_minjs_deployer_php',
  ), array(
    'title'  => '"minjs.php" file integrity check',
    'status' => file_exists('minjs.php') && md5_file('minjs.php')=='fcad0c49627f7d6dbf8e2dd4ccd48951',
    'action' => 'download_minjs_php',
  ), array(
    'title'  => '"minjs_proxy.php" file integrity check',
    'status' => file_exists('minjs_proxy.php') && md5_file('minjs_proxy.php')=='fcad0c49627f7d6dbf8e2dd4ccd48951',
    'action' => 'download_minjs_proxy_php',
  ), array(
    'title'  => '"minjs_settings.php" file exists',
    'status' => file_exists('minjs_settings.php'),
    'action' => 'download_minjs_settings_php',
  ), array(
    'title'  => '"minjs_settings.php" salt changed to random string',
    'status' => $settings['salt']!='salt',
    'action' => 'create_random_salt',
  ), array(
    'title'  => '"minjs_settings.php" password changed',
    'status' => $settings['password']!='minjs',
    'action' => 'change_database_password',
  ), array(
    'title'  => '"minjs_settings.php" server/username/password correct',
    'status' => mysql_connect($settings['server'],$settings['username'],$settings['password']),
    'action' => 'create_database',
  ), array(
    'title'  => '"minjs_settings.php" database correct',
    'status' => mysql_select_db($settings['database']),    
    'action' => 'create_database',
  ), array(
    'title'  => 'initial database state loaded from backup',
    'status' => mysql_num_rows(mysql_query("show tables;"))>0,
    'action' => 'load_database_backup',
  ), array(
    'title'  => 'initial database state file deleted from disk',
    'status' => !file_exists('minjs.sql'),
    'action' => 'delete_minjs_sql',
  ), array(
    'title'  => 'password for admin account is changed',
    'status' => get_admin_password()&&get_admin_password()!=sha1($settings['salt'].'admin'),    
    'action' => 'change_admin_password',
));

$page = @$_GET['page'];
$pages = array('status'=>true);
foreach ($checks as $check) $pages[$check['action']]=true;
if (!isset($pages[$page])) $page = 'status';
echo '<html><body>'.call_user_func($page).'</body></html>';

function get_admin_password()
{ $result = mysql_query("select password from users where username='admin';");
  if (!$result) return false;
  $row = mysql_fetch_assoc($result);
  return $row['password'];
}

function change_admin_password()
{ global $settings;
  if(isset($_POST['password']))
  { $password = sha1($settings['salt'].$_POST['password']);
  	mysql_query("update users set password='$password' where username='admin';");
    die(header('Location: ?'));
  }
  $title = '<h1>Change admin password</h1>';
  $text = 'Default password:<pre>admin</pre>';
  $form = '<form method="post">New password:<br/><input name="password"><input type="submit" value="save"></form>';
  $link = '<a href="?">NO, continue</a>';
  return $title.$text.$form.$link;
}


function create_database()
{ global $settings;
  $database = $settings['database'];
  $username = $settings['username'];
  $password = $settings['password'];
  $q = array();
  $q[] = "CREATE USER '$username'@'localhost' IDENTIFIED BY '$password';";
  $q[] = "GRANT USAGE ON * . * TO '$username'@'localhost' IDENTIFIED BY '$password';";
  $q[] = "CREATE DATABASE IF NOT EXISTS `$database` ;";
  $q[] = "GRANT ALL PRIVILEGES ON `$database` . * TO '$username'@'localhost';";
  if(isset($_POST['username']))
  { @mysql_close();
    mysql_connect($settings['server'],'root',$_POST['password']);
    foreach($q as $query) mysql_query($query);
    mysql_close();
    die(header('Location: ?'));
  }
  $title = '<h1>Create database "'.$database.'"</h1>';
  $text = 'SQL to execute:<pre>'.implode("\n",$q).'</pre>';
  $form = '<form method="post">Root password:<br/><input name="password"><input type="submit" value="execute"></form>';
  $link = '<a href="?">NO, continue</a>';
  return $title.$text.$form.$link;
}

function create_random_salt()
{ global $settings;
  $salt = sha1('minjs'.time().mt_rand());
  $file = '<h1>Change file "minjs_settings.php"</h1>';
  $salt = 'Current salt setting:<pre>'.$settings['salt'].'</pre>Proposed (random) salt:<pre>'.$salt.'</pre>';
  $warn = 'NB: All user accounts will become inaccessible when you change the salt!<br/><br/>';
  $link = '<a href="?">OK, continue</a>';
  return $file.$salt.$warn.$link;
}

function change_database_password()
{ global $settings;
  $pwd = base_convert(md5('minjs'.time().mt_rand()),16,36);
  $file = '<h1>Change file "minjs_settings.php"</h1>';
  $pwd = 'Current database password:<pre>'.$settings['password'].'</pre>Proposed (random) password:<pre>'.$pwd.'</pre>';
  $warn = 'NB: Password is also used for Adminer and/or PHPMyAdmin database access!<br/><br/>';
  $link = '<a href="?">OK, continue</a>';
  return $file.$pwd.$warn.$link;
}

function lock_backups_directory()
{ return lock_directory('data');
}

function lock_tools_directory()
{ return lock_directory('tool');
}

function lock_directory($directory)
{ global $settings;
  $pwd = base_convert(md5('minjs'.time().mt_rand()),16,36);
  $file = '<h1>Change file rights on directory "'.$directory.'"</h1>';
  $rights = substr(sprintf('%o', fileperms($directory)), -3);
  $rights2 = substr(array_shift(explode(' ',shell_exec('ls -dl '.$directory))),-9);
  $pwd = 'Current file rights:<pre>'.$rights.' = '.$rights2.'</pre>Proposed file rights:<pre>700 = rwx------</pre>';
  $warn = 'NB: File rights can be changed using the "chmod 755 '.$directory.'" command or through an (S)FTP client.<br/><br/>';
  $link = '<a href="?">OK, continue</a>';
  return $file.$pwd.$warn.$link;
}

function load_database_backup()
{ global $settings;
  $database = $settings['database'];
  $username = $settings['username'];
  $password = $settings['password'];
  $mysql = shell_exec('locate mysql');
  if (!file_exists($mysql)) $mysql = '/usr/local/mysql/bin/mysql';
  $cmd = "cat backups/minjs.sql | $mysql -u$username -p$password $database";
  if(isset($_POST['confirm']))
  { shell_exec($cmd);
    die(header('Location: ?'));
  }
  $title = '<h1>Load database backup into "'.$database.'"</h1>';
  $text = 'Shell command to excecute:<pre>'.$cmd.'</pre>';
  $form = '<form method="post">Confirmation:<br/><input type="submit" name="confirm" value="I understand the risk"></form>';
  $link = '<a href="?">NO, continue</a>';
  return $title.$text.$form.$link;
}

function status()
{ global $checks;
  $result = '';
  foreach ($checks as $check)
  { $title = $check['title'];
    $status = $check['status'];
  	$action = $check['action'];
  	$color = $status?'green':'red';
  	$value = $status?' OK ':'    ';
  	$action = $status?'':"<a href=\"?page=$action\">hint</a>";
  	$result .= "[$value] <span style=\"color:$color\">$title</span> $action\n";
  }
  return '<h1>Deployer</h1><pre>'.$result.'</pre>'; 
}

function fail($sql)
{ $error = mysql_error();
  mysql_close();
  echo "<pre>COMMAND:\n$sql\n\nERROR:\n$error\n</pre>\n";
  die();
}

function connect()
{ global $settings;
  $link = mysql_connect('localhost', $settings['username'], $settings['password']);
  if (!$link) die('mysql_connect: '.mysql_error());
  $db_selected = mysql_select_db($settings['database'], $link);
  if (!$db_selected) fail('mysql_select_db('.$settings['database'].')');
}
