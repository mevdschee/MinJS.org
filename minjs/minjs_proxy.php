<?php

// required MinJS proxy settings
$minjs_proxy_settings = array(
    'url'            => 'http://localhost/~maurits/minjs.php',
    'cookie'         => 'minjs',
);

class MinJS_Proxy
{
  private $settings = array();
  private $session_key = '';
  private $csrf_key = '';
  private $csrf_check = false;
  private $queries = array();
  
  public function __construct($settings)
  { // settings should be given as constructor parameter
    $this->settings = $settings;
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
  }

  public function query($type,$query,$parameters=null)
  { $results = $this->queries(array(array_filter(array('data',$type,$query,$parameters))));
    if ($results) return $results['data'];
    else return $results;
  }

  public function queries($queries = null)
  { if ($queries) $this->queries = $queries;
    $url = $this->settings['url'];
    $cookie = $this->settings['cookie'];
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST,1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie.'='.$this->session_key);
    $queries = urlencode(json_encode($this->queries));
    $csrf_key = urlencode($this->csrf_key);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "queries=$queries&csrf_key=$csrf_key");

    $response = curl_exec($ch);
    $parts = preg_split('/\r\n\r\n/', $response);
    $body = array_pop($parts);
    $headers = join('\r\n\r\n',$parts);
    if (preg_match('/^Set-Cookie: '.$cookie.'=.*/mi', $headers, $m)) header($m[0]);
    if (preg_match('/^X-MinJS-CSRF-Key: .*/mi', $headers, $m)) header($m[0]);
    curl_close($ch);
    if ($body && substr($body,0,1)!='{') throw new Exception($body);
    return json_decode($body,true);
  }

  static public function standalone()
  { return realpath(__FILE__) == realpath($_SERVER['SCRIPT_FILENAME']);
  }
}

// is MinJS running using include or require?
if (MinJS_Proxy::standalone())
{ try // no, run it as a Query API
  { $minjs = new MinJS_Proxy($minjs_proxy_settings);
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
  $minjs = new MinJS_Proxy($minjs_settings, false);
}
