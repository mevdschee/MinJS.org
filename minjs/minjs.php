<?php
class MinJS
{
  private $settings = array();
  private $session_key = '';
  private $csrf_key = '';
  private $csrf_check = false;
  private $queries = array();
  private $current_user_id = 0;
  private $current_user = 'nobody';
  private $current_group_id = 0;
  private $data = array();
  
  public function __construct($settings,$csrf_check=true)
  { // settings should be given as constructor parameter
    $this->settings = $settings;
    // Cross Site Request Forgery check is enabled by default
    $this->csrf_check = $csrf_check;
    // get arguments from POST only
    $arguments = $_POST;
    // check for a session key
    if (!isset($_COOKIE[$this->settings['cookie']])) $this->session_key = false;
    // sanitize session key
    else $this->session_key = preg_replace('/[^a-f0-9]+/i','',$_COOKIE[$this->settings['cookie']]);
    // check for a csrf key
    if (!isset($_POST['csrf_key'])) $this->csrf_key = false;
    // sanitize csrf key
    else $this->csrf_key = preg_replace('/[^a-f0-9]+/i','',$_POST['csrf_key']);
    // connect to mysql
    $link = mysql_connect($this->settings['server'], $this->settings['username'], $this->settings['password']);
    // error on failure to connect
    if (!$link) die('mysql_connect: '.mysql_error());
    // select database
    $db_selected = mysql_select_db($this->settings['database'], $link);
    // error on failure select
    if (!$db_selected) mysql_close() && die('mysql_select_db: '.mysql_error());
    // set utf8
    mysql_query("SET NAMES 'UTF8'");
    // try to identify user by session key
    $this->authenticate();
    // create or update session timestamps
    $this->update_sessions();
  }
  
  private function authenticate()
  { // if user is logged in get current user id
    $start_time = date('Y-m-d H:i:s',strtotime('-'.$this->settings['session_max_age']));
    $session_key = $this->session_key;
    $csrf_key = $this->csrf_key;
    $csrf_check = $this->csrf_check?"`csrf_key`='$csrf_key'":'1=1';
    $result = mysql_query("SELECT `user_id` FROM `sessions` WHERE `session_key`='$session_key' and $csrf_check and `updated`>'$start_time'");
    if ($row = mysql_fetch_row($result)) 
    { $this->current_user_id = $row[0]+0;
    }
    else
    { $this->session_key='';
      $this->csrf_key='';
    }
    if ($this->current_user_id)
    { $result = mysql_query("SELECT `username`,`group_id` FROM `users` WHERE `id`=".$this->current_user_id);
      if ($row = mysql_fetch_row($result))
      { $this->current_user = $row[0];
        $this->current_group_id = $row[1]+0;
      }
    }
    // set group and user vars for history triggers
    mysql_query("SET @GROUP_ID = ".$this->current_group_id);
    mysql_query("SET @USER_ID = ".$this->current_user_id);
  }

  private function update_sessions()
  { $start_time = date('Y-m-d H:i:s','-'.strtotime($this->settings['session_max_age']));
    $current_time = date('Y-m-d H:i:s');
    // clear old sessions (to save space?)
    mysql_query("DELETE `sessions` WHERE `updated`<='$start_time'");
    // update current session if one found
    if ($this->session_key) 
    { $session_key = $this->session_key;
      $csrf_key = $this->csrf_key;
      mysql_query("UPDATE `sessions` SET `updated`='$current_time' WHERE `session_key`='$session_key' and `updated`>'$start_time'");
    }
    else // create a new session if nessecary
    { $this->session_key = sha1(microtime().mt_rand(1,999999999));
      $session_key = $this->session_key;
      $this->csrf_key = sha1('csrf'.microtime().mt_rand(1,999999999));
      $csrf_key = $this->csrf_key;
      $user_id = $this->current_user_id?$this->current_user_id:'NULL';
      mysql_query("INSERT INTO `sessions` (`session_key`,`csrf_key`,`user_id`,`created`,`updated`) VALUES ('$session_key','$csrf_key',$user_id,'$current_time','$current_time')");
      // store session key in cookie
      setcookie($this->settings['cookie'],$this->session_key);
      // store csrf key in custom header
      header('X-MinJS-CSRF-Key: '.$this->csrf_key);
    }
  }
  
  private function properties($query_name)
  { $query_name = mysql_real_escape_string($query_name);
    $result = mysql_query("SELECT `id`, `text` FROM `queries` WHERE `name` LIKE '$query_name'");
    if (!($row = mysql_fetch_row($result))) $this->stop("query '$query_name' not found");
    $query_id = $row[0];
    $sql = $row[1];
    return array('id'=>$query_id,'sql'=>$sql);
  }

  private function authorize($query_id)
  { // check permissions for query
    $result = mysql_query("SELECT id FROM roles_queries WHERE (role_id in (select role_id from users_roles where user_id = $this->current_user_id) OR role_id = 1) and (query_id=$query_id OR query_id = 1)");
    if (!($row = mysql_fetch_row($result))) return false;
    return true;
  }

  private function stop($message)
  { mysql_query("ROLLBACK");
    throw new Exception($message);
  }

  private function login($rows)
  { $user_id = count($rows)==1?$rows[0]['id']+0:0;
    $session_key = $this->session_key;
    if ($this->current_user_id!=$user_id)
    { $user_id = $user_id?$user_id:'NULL';
      mysql_query("UPDATE `sessions` SET `user_id`=$user_id WHERE `session_key`='$session_key'");
    }
  }

  private function get_parameter_definitions($query_id)
  { // get parameters
    $result = mysql_query("SELECT `id`, `name`, `type`, `validator`, `default` FROM `parameters` WHERE `query_id` = $query_id");
    $parameters = array();
    while ($field = mysql_fetch_assoc($result))
    { $i = $field['id'];
      $f = $field['name'];
      if (!preg_match('/^[A-Za-z_0-9]+$/',$f)) $this->stop("parameter $i has invalid name: $f");
      $parameters[$f]=array('type'=>$field['type'],'validator'=>$field['validator'],'default'=>$field['default']);
    }
    return $parameters;
  }

  private function apply_parameters($i,$sql,$query,$definitions)
  { // convert parameters in sql
    $parameters = $query['parameters'];
    foreach ($parameters as $key => $value)
    { if (!preg_match('/^[A-Za-z_0-9]+$/',$key)) $this->stop("argument name invalid: queries[$i].parameters.$key");
    }
    $count = -1;
    foreach ($definitions as $key => $definition)
    { if (!isset($parameters[$key])) continue;
      $value = $parameters[$key];
      if (!is_array($value)) continue;
      if ($count<0) $count = count($value);
      else if ($count != count($value)) $this->stop("argument count invalid: queries[$i].parameters.$key");
    }
    if ($count<0) $count = 1;
    $sql_queries = array();
    for ($q=0;$q<$count;$q++)
    { $search = array('::user_id','::group_id','::secret');
      $secret = $this->settings['secret'];
      $replace = array($this->current_user_id,$this->current_group_id,"'$secret'");
      // don't waste randomness
      if (preg_match('/::random/',$sql))
      { $search[]='::random';
        $random = sha1(microtime().mt_rand(1,999999999));
        $replace[]="'$random'";
      }
      $validation_errors = array();
      foreach ($definitions as $key => $definition)
      { if (!isset($parameters[$key]))
        { $value = $definition['default'];
          if (!strlen($value)) $this->stop("argument not found: queries[$i].parameters.$key");
          array_push($search,":$key");
          array_push($replace,$value);
        }
        else
        { $value = $parameters[$key];
          array_push($search,":$key");
          if ($definition['type']=='string')
          { if (is_array($value)) $value = $value[$q];
            $safe_value = mysql_real_escape_string($value);
            array_push($replace,"'$safe_value'");
          }
          elseif ($definition['type']=='int')
          { if (is_array($value)) $value = $value[$q];
            $safe_value = $value+0;
            array_push($replace,$safe_value);
          }
          elseif ($definition['type']=='column')
          { if (is_array($value)) $value = $value[$q];
            $safe_value = preg_replace('/[^a-z_0-9]/i','',$value);
            array_push($replace,"`$safe_value`");
          }
          if ($definition['validator'])
          { $validator = create_function('$value', 'return '.$definition['validator'].';');
            if (!$validator($value)) array_push($validation_errors,$key);
          }
        }
      }
      if (count($validation_errors)) $this->stop("validation errors on: ".implode(',',$validation_errors));
      // find unmatched vars
      $full_sql = str_replace($search,'?',$sql);
      if (preg_match_all('/::?([A-Za-z_0-9]+)/',$full_sql,$matches))
      { $keys = implode(',',$matches[1]);
        $query_name = $query['query'];
        $query_id = $query['id'];
        $this->stop("query '$query_name' parameters not found: $keys");
      }
      $full_sql = $this->search_replace($search,$replace,$sql);
      array_push($sql_queries,$full_sql);
    }
    return $sql_queries;
  }

  private $search_replace_data = array();

  private function search_replace_callback($v)
  { return $this->search_replace_data[$v[1]]; 
  }

  private function search_replace($s,$r,$sql)
  { $e = '/('.implode('|',array_map('preg_quote', $s)).')/';
    $this->search_replace_data = array_combine($s,$r);
    return preg_replace_callback($e, array($this,'search_replace_callback'), $sql);
  }

  private function execute_sql($sql)
  { $rows = array();
    $result = mysql_query($sql);
    if (!$result) { $this->stop(mysql_error().' - '.$sql); }
    if ($result!==true)
    { while ($row = mysql_fetch_assoc($result))
      { array_push($rows,$row);
      }
    }
    else
    { $row = array();
      $row['insert_id']=mysql_insert_id();
      $row['affected_rows']=mysql_affected_rows();
      array_push($rows,$row);
    }
    return $rows;
  }

  // convert queries from numbered to named properties
  private function add_field_names_to_queries($queries)
  { $fields = array('path','type','query','parameters','pathParameters');
    foreach ($queries as $i=>$query) 
    { $queries[$i]=array();
      foreach ($fields as $f=>$field) 
      { if (isset($query[$f])) $queries[$i][$field]=$query[$f];
      }
    }
    return $queries;
  }

  public function query($type,$query,$parameters=null)
  { $results = $this->queries(array(array_filter(array('data',$type,$query,$parameters))));
    if ($results) return $results['data'];
    else return $results;
  }

  public function queries($queries = null)
  { $this->data = array();
    if ($queries) $this->queries=$this->add_field_names_to_queries($queries);
    mysql_query("BEGIN");
    foreach($this->queries as $i=>$query)
    { if (!isset($query['query'])) $this->stop("argument not found: queries[$i].query");
      $properties = $this->properties($query['query']);
      if (!$this->authorize($properties['id']))
      { $this->stop("query '$query[query]' not permitted for '$this->current_user'");
      }
      $this->queries[$i]['id'] = $properties['id'];
      $this->queries[$i]['sql'] = $properties['sql'];
    } 
    foreach($this->queries as $i=>$query)
    { $sql = $query['sql'];
      $definitions = $this->get_parameter_definitions($query['id']);
      if (!isset($query['parameters']) || !$query['parameters']) $query['parameters'] = array();
      $query['parameters']=$this->apply_path_parameters($query);
      $sql_queries = $this->apply_parameters($i,$sql,$query,$definitions);
      $rows = array();
      foreach ($sql_queries as $sql)
      { $rows = array_merge($rows,$this->execute_sql($sql));
      }
      if($query['query']=='users.login')
      { $this->login($rows);
        $this->authenticate();
      }
      $this->store_rows($i,$query,$rows);
    }
    mysql_query("COMMIT");
    return $this->data;
  }
  
  private function apply_path_parameters($query)
  { $parameters = $query['parameters'];
    if (isset($query['pathParameters']))
    { foreach ($query['pathParameters'] as $name=>$path)
      { $name = preg_replace('/[^a-zA-Z_0-9]/','',$name);
        $path = preg_replace('/[^a-zA-Z_0-9\.]/','',$path);
        $path = explode('.',$path);
        $data =& $this->data;
        foreach($path as $p) $data =& $data[$p];
        $parameters[$name] = $data;
      }
    }
    return $parameters;
  }

  private function store_rows($i,$query,$rows)
  { $query['path'] = preg_replace('/[^a-zA-Z_0-9\.]/','',$query['path']);
    $query['type'] = preg_replace('/[^a-zA-Z_0-9\:\,]/','',$query['type']);
    if (!isset($query['type'])) $this->stop("argument not found: queries[$i].type");
    if (!isset($query['path'])) $this->stop("argument not found: queries[$i].path");
    if (strpos($query['type'],':')) 
    { list($type,$parameters) = explode(':',$query['type']);
    }
    else list($type,$parameters) = array($query['type'],false);
    if ($parameters) $parameters = explode(',',$parameters);
    $path = explode('.',$query['path']);
    // no results, define returned output for each query type
    if (count($rows)==0) switch($type)
    { case 'records': $rows = array(); break;
      case 'record':  $rows = null; break;
      case 'list':    $rows = (object) array(); break;
      case 'values':  $rows = array(); break;
      case 'value':   $rows = null; break;
      case 'success': $rows = false; break;
      default: $this->stop("query type '$type' not defined");
    }
    else switch ($type)
    { case 'records':
        if ($parameters)
        { $records = array();
          foreach ($rows as $row) 
          { $record = array();  
            foreach ($parameters as $parameter) 
            { if (!array_key_exists($parameter,$row))
              { $this->stop("query[$i] field '$parameter' not found in row: ".json_encode($row));
              }
              $record[$parameter]=$row[$parameter]; 
            }
            $records[]=$record; 
          }
          $rows=$records;
        }
        break;
      case 'record': 
        $row=$rows[0];
        if ($parameters)
        { $record = array();  
          foreach ($parameters as $parameter) 
          { if (!array_key_exists($parameter,$row)) 
            { $this->stop("query[$i] field '$parameter' not found in row: ".json_encode($row));
            }
            $record[$parameter]=$row[$parameter]; 
          }
          $row = (object)$record;
        }
        $rows = $row;
        break;
      case 'list':
        if (!isset($parameters[0])) $parameters[0] = 'id';
        if (!isset($parameters[1])) $parameters[1] = 'name';
        $list = array();
        foreach ($rows as $row)
        { if (!array_key_exists($parameters[0],$row))
          { $this->stop("query[$i] field '$parameters[0]' not found in row: ".json_encode($row));
          }
          if (!array_key_exists($parameters[1],$row))
          { $this->stop("query[$i] field '$parameters[1]' not found in row: ".json_encode($row));
          }
          $list[$row[$parameters[0]]]=$row[$parameters[1]]; 
        }
        $rows=(object)$list;
        break;
      case 'values': 
        if (!isset($parameters[0])) $parameters[0] = 'id';
        $values = array(); 
        foreach ($rows as $row) 
        { if (!array_key_exists($parameters[0],$row))
          { $this->stop("query[$i] field '$parameters[0]' not found in row: ".json_encode($row));
          }
          $values[]=$row[$parameters[0]]; 
        }
        $rows=$values;
        break;
      case 'value': 
        if (!isset($parameters[0])) $parameters[0] = 'id';
        if (!array_key_exists($parameters[0],$rows[0]))
        { $this->stop("query[$i] field '$parameters[0]' not found in row: ".json_encode($rows[0]));
        }
        $rows=$rows[0][$parameters[0]];
        break;
      case 'success':
        $rows=$rows?true:false;
        break;
      default:
        $this->stop("query type '$type' not defined");
    }
    $data =& $this->data;
    foreach($path as $p) $data =& $data[$p];
    $data = $rows;
  }
 
  static public function standalone()
  { return realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME']);
  }
}

// require MinJS settings
require "minjs_settings.php";
// is MinJS running using include or require?
if (MinJS::standalone())
{ try // no, run it as a Query API
  { $minjs = new MinJS($minjs_settings);
    // get arguments from POST and make sure magic quotes do no harm
    if (get_magic_quotes_gpc())
    { $_POST = json_decode(stripslashes(json_encode($_POST)),true);
    }
    echo json_encode($minjs->queries(json_decode($_POST['queries'], true))); 
  }
  catch (Exception $e) { die($e->getMessage()); }
}
else // yes, run it as Data Abstration Layer library
{ //turn off automatic CSRF protection
  $minjs = new MinJS($minjs_settings, false);
}
