<?php

chdir('..');
require "minjs_settings.php";
$settings = $minjs_settings;

list($action,$table) = Historian::get_arguments();
Historian::action($settings['server'],$settings['username'],$settings['password'],$settings['database'],$action,$table); // parameters (server,username,password,database)

class Historian
{
  public static function get_arguments()
  { if (isset($_GET['action'])) $action = preg_replace('/[^a-z]/','',$_GET['action']);
    else $action = '';
    if (isset($_GET['table'])) $table = preg_replace('/[^a-z_A-Z]/','',$_GET['table']);
    else $table = '';
    return array($action,$table);
  }

  public static function action($server,$username,$password,$database,$action,$table)
  { Historian::connect($server,$username,$password,$database);
    $queries = array(); 
    if ($action=='remove') $queries = Historian::remove($table);
    if ($action=='update') $queries = Historian::update($table);
    if ($action=='add') $queries = Historian::add($table);
    if (count($queries))
    { foreach ($queries as $query) mysql_query($query) || die($query.' => '.mysql_error());
      //echo "<pre>".implode("\n\n\n",$queries)."</pre>";
      header('Location: ?');
    }
    else Historian::print_check();
  }

  public static function print_check()
  { $tables = Historian::check();
    $history = Historian::query_list("SELECT TABLE_NAME,TABLE_ROWS FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name like 'history'");
    echo "<h1>Historian</h1>";
    echo "<p>History length: ".(isset($history['history'])?$history['history']:'-')."</p>";
    echo "<table cellpadding=\"4\"><tr><th>#</th><th>name</th><th>history</th><th>start</th><th>length</th><th>actions</th></tr>";
    foreach($tables as $i=>$t) echo "<tr><td>".($i+1).".</td><td>$t[name]</td><td>$t[history]</td><td>$t[start]</td><td>$t[length]</td><td>$t[actions]</td></tr>";
    echo "</table>";
  }

  public static function check()
  { $tableNames = Historian::query_list("SELECT TABLE_NAME,TABLE_ROWS FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name NOT like '%_history' AND table_name NOT like 'history'");
    $historyTableNames = Historian::query_list("SELECT TABLE_NAME,TABLE_ROWS FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name like '%_history'");
    $tables = array();
    foreach (array_keys($tableNames) as $tableName)
    { $table = array('name'=>$tableName);
      $historyTableName = $tableName.'_history';
      $hasHistoryTable = in_array($historyTableName,array_keys($historyTableNames));
      $table['history']=$hasHistoryTable?'yes':'no';
      if ($hasHistoryTable)
      { $from = Historian::query("SELECT `history`.`time` FROM `$historyTableName`,`history` where `history`.id = `history_start_id` LIMIT 1;");
        if (count($from)) $table['start']=$from[0]['time'];
        else $table['start']='-';
        $table['length'] = $historyTableNames[$historyTableName];
      }
      else
      { $table['start'] = '-';
        $table['length'] = '-';
      }
      $actions = array();
      if ($hasHistoryTable)
      { $actions[] = '<a href="?action=update&table='.urlencode($tableName).'">update</a>';
        $actions[] = '<a href="?action=remove&table='.urlencode($tableName).'">remove</a>';
      }
      else $actions[] = '<a href="?action=add&table='.urlencode($tableName).'">add</a>';
      $table['actions'] = implode(' ',$actions);
      $tables[] = $table;
    }
    return $tables;
  }

  private static function create_transaction_table()
  { $queries = array(); 
     /* Table for history */
    $q = array();
    $q[] = 'CREATE TABLE IF NOT EXISTS `history` (';
    $q[] = '  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,';
    $q[] = '  `time` datetime NOT NULL,';
    $q[] = '  `group_id` int(11) DEFAULT NULL,';
    $q[] = '  `user_id` int(11) DEFAULT NULL,';
    $q[] = '  PRIMARY KEY (`id`),';
    $q[] = '  KEY `user_id` (`user_id`),';
    $q[] = '  KEY `group_id` (`group_id`)';
    $q[] = ') ENGINE=InnoDB DEFAULT CHARSET=utf8;';
    $queries[] = implode("\n",$q);
    return $queries;
  }

  private static function drop_transaction_table()
  { return array('DROP TABLE IF EXISTS `history`;');
  }

  private static function create_transaction_function()
  { $queries = array(); 
    /* Procedure for get_transaction_id() */
    $q = array();
    $q[] = 'CREATE FUNCTION `get_transaction_id`() RETURNS bigint BEGIN';
    $q[] = '  INSERT INTO `history` SET `time`=NOW(), `user_id`=@USER_ID, `group_id`=@GROUP_ID;';
    $q[] = '  RETURN LAST_INSERT_ID();';
    $q[] = 'END;';
    $queries[] = implode("\n",$q);
    return $queries;
  }

  private static function drop_transaction_function()
  { return array('DROP FUNCTION IF EXISTS `get_transaction_id`;');
  }

  private static function create_history_table($tableName)
  { $queries = array(); 
    $historyTableName = $tableName.'_history';
    $create = Historian::query("show create table `$tableName`;");
    $create = explode("\n",$create[0]["Create Table"]);
    $fields = array(
    'CREATE TABLE IF NOT EXISTS `'.$historyTableName.'` (',
    '  `history_id` int(11) NOT NULL AUTO_INCREMENT,',
    '  `history_start_id` bigint(20) unsigned NOT NULL,',
    '  `history_end_id` bigint(20) unsigned DEFAULT NULL,',
    );
    foreach ($create as $c) if (substr($c,0,3)=='  `') $fields[] = str_replace(' AUTO_INCREMENT','',$c);
    $fields[] = '  PRIMARY KEY (`history_id`),';
    $fields[] = '  KEY `history_start_end` (`history_start_id`,`history_end_id`)';
    $fields[] = ') ENGINE=InnoDB DEFAULT CHARSET=utf8;';
    $queries[] = implode("\n",$fields);
    return $queries;
  }

  private static function drop_history_table($tableName)
  { $historyTableName = $tableName.'_history';
    return array("DROP TABLE IF EXISTS `$historyTableName`;");
  }

  private static function create_triggers($tableName)
  { $historyTableName = $tableName.'_history';
    $queries = array(); 
    $columns = Historian::query_list("SELECT `ORDINAL_POSITION`-1,`COLUMN_NAME` FROM information_schema.`COLUMNS` WHERE table_schema=DATABASE() AND `TABLE_NAME`='$tableName'");
    /* Trigger for INSERT */
    $q = array();
    $q[] = 'CREATE TRIGGER `'.$historyTableName.'_insert` AFTER INSERT ON `'.$tableName.'` FOR EACH ROW BEGIN';
    $q[] = '  IF @USER_ID IS NOT NULL THEN';
    $q[] = '    SET @TRANSACTION_ID = IFNULL(@TRANSACTION_ID,get_transaction_id());';
    $q[] = '    INSERT INTO `'.$historyTableName.'` (`history_start_id`, `history_end_id`, `'.implode('`, `',$columns).'`)';
    $q[] = '    VALUES (@TRANSACTION_ID, NULL, NEW.`'.implode('`, NEW.`',$columns).'`);';
    $q[] = '  END IF;';
    $q[] = 'END;';
    $queries[] = implode("\n",$q);
    /* Trigger for DELETE */
    $q = array();
    $q[] = 'CREATE TRIGGER `'.$historyTableName.'_delete` AFTER DELETE ON `'.$tableName.'` FOR EACH ROW BEGIN';
    $q[] = '  IF @USER_ID IS NOT NULL THEN';
    $q[] = '    SET @TRANSACTION_ID = IFNULL(@TRANSACTION_ID,get_transaction_id());';
    $q[] = '    UPDATE `'.$historyTableName.'` SET `history_end_id` = @TRANSACTION_ID WHERE `id`=OLD.`id` AND `history_end_id` IS NULL;';
    $q[] = '  END IF;';
    $q[] = 'END;';
    $queries[] = implode("\n",$q);
    /* Trigger for UPDATE */
    $q = array();
    $q[] = 'CREATE TRIGGER `'.$historyTableName.'_update` AFTER UPDATE ON `'.$tableName.'` FOR EACH ROW BEGIN';
    $q[] = '  IF @USER_ID IS NOT NULL THEN';
    $q[] = '    SET @TRANSACTION_ID = IFNULL(@TRANSACTION_ID,get_transaction_id());';
    $q[] = '    UPDATE `'.$historyTableName.'` SET `history_end_id` = @TRANSACTION_ID WHERE `id`=OLD.`id` AND `history_end_id` IS NULL;';
    $q[] = '    INSERT INTO `'.$historyTableName.'` (`history_start_id`, `history_end_id`, `'.implode('`, `',$columns).'`)';
    $q[] = '    VALUES (@TRANSACTION_ID, NULL, NEW.`'.implode('`, NEW.`',$columns).'`);';
    $q[] = '  END IF;';
    $q[] = 'END;';
    $queries[] = implode("\n",$q);
    return $queries;
  }

  private static function drop_triggers($tableName)
  { $historyTableName = $tableName.'_history';
    $queries = array(); 
    $queries[] = "DROP TRIGGER IF EXISTS `${historyTableName}_insert`;";
    $queries[] = "DROP TRIGGER IF EXISTS `${historyTableName}_delete`;";
    $queries[] = "DROP TRIGGER IF EXISTS `${historyTableName}_update`;";
    return $queries;
  }
  
  private static function touch_table_data($tableName)
  { return array("UPDATE `$tableName` SET `id`=`id`;");
  }

  private static function add($tableName)
  { $queries = array();
    $historyTableNames = Historian::query_list("SELECT TABLE_NAME,TABLE_ROWS FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name like '%_history'");
    if (count($historyTableNames)==0) 
    { $queries = array_merge($queries,Historian::create_transaction_table());
      $queries = array_merge($queries,Historian::create_transaction_function());
    }
    $queries = array_merge($queries,Historian::drop_history_table($tableName));
    $queries = array_merge($queries,Historian::create_history_table($tableName));
    $queries = array_merge($queries,Historian::drop_triggers($tableName));
    $queries = array_merge($queries,Historian::create_triggers($tableName));
    $queries = array_merge($queries,Historian::touch_table_data($tableName));
    return $queries;
  }

  private static function update($tableName)
  { $queries = array();
    $historyTableName = $tableName.'_history';
    $historyTableNames = Historian::query_list("SELECT TABLE_NAME,TABLE_ROWS FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name like '%_history'");
    // return if history table doesn't exist
    if (!isset($historyTableNames[$historyTableName])) return $queries;
    $queries = array_merge($queries,Historian::drop_triggers($tableName));
    $queries = array_merge($queries,Historian::create_triggers($tableName));
    return $queries;
  }

  private static function remove($tableName)
  { $queries = array();
    $historyTableName = $tableName.'_history';
    $historyTableNames = Historian::query_list("SELECT TABLE_NAME,TABLE_ROWS FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name like '%_history'");
    // return if history table doesn't exist
    if (!isset($historyTableNames[$historyTableName])) return $queries;
    $queries = array_merge($queries,Historian::drop_triggers($tableName));
    $queries = array_merge($queries,Historian::drop_history_table($tableName));
    // if this is the last table
    if (count($historyTableNames)==1)
    { $queries = array_merge($queries,Historian::drop_transaction_function());
      $queries = array_merge($queries,Historian::drop_transaction_table());
    }
    return $queries;
  }
  
  private static function connect($server,$username,$password,$database)
  { // connect to mysql
    $link = mysql_connect($server, $username, $password);
    if (!$link) die('mysql_connect: '.mysql_error());
    $db_selected = mysql_select_db($database, $link);
    if (!$db_selected)
    { mysql_close();
      die ('mysql_select_db: '.mysql_error());
    }
    mysql_query("SET NAMES 'UTF8'");
  }

  private static function query($query)
  { $result = mysql_query($query);
    if (!$result) die('mysql_query failed: '.$query);
    $rows = array();
    while ($row = mysql_fetch_assoc($result)) $rows[] = $row;
    return $rows;
  }
  
  private static function query_list($query)
  { $result = mysql_query($query);
    if (!$result) die('mysql_query failed: '.$query);
    $rows = array();
    while ($row = mysql_fetch_row($result)) $rows[$row[0]] = $row[1];
    return $rows;
  }
}
