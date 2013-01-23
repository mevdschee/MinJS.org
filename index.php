<?php
require "minjs/minjs.php";
require "markdown/markdown.php";

if (!$_SERVER['QUERY_STRING'])
{ $page = 'home';
  header('Location: ?'.$page);
  die();
}

$page = $_SERVER['QUERY_STRING'];
$page = preg_replace('/[^a-z_]/','',$page);

$menu = $minjs->query('list','pages.list');
$page = $minjs->query('record','pages.find',array('name'=>$page));

$content = Markdown($page['data']);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
<title>MinJS.org</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<style>
*
{ margin:0; padding:0;
}
p, ol, ul, h1,h2,h3,h4,h5,h6,h7
{ margin: 0;
  margin-bottom: 1em;
}
h1,h2,h3,h4,h5,h6,h7
{ margin-top: 40px;
  margin-bottom: 0.75em;
}
ul
{ margin-left: 2em;
}
html
{ min-height: 100%;
}
body
{ font-family: sans-serif; 
  line-height: 1.45em;
  min-height: 100%;
}
pre
{ line-height: 1.2em;
}
h1,h2,h3,h4,h5,h6,h7
{ font-family: 'Trebuchet MS', sans-serif;
}
a
{ color: black;
}
.header
{ padding: 0.5em 0; margin:0; background: black; color: white;
}
.outer-version
{ position: absolute; width: 100%; max-width: 68em;
}
.version
{ float: right; padding-right: 2em; margin-top: 0.5em
}
.menu
{ position: absolute; width: 10em; padding: 2.5em 1em;
  min-height: 319px;
  background: url(images/gradient.png) no-repeat right top; 
}
.menu ul
{ margin-left: 1em;
}
.menu li
{ list-style: none;
}
.title { padding-top: 1em; padding-left: 2em; margin-bottom: 0; }
.subtitle { padding-left: 2em; }
.content { margin-left: 14em; max-width: 40em;}
</style>
</head>
<body style="padding: 0; margin:0;">
<a href="https://github.com/mevdschee/MinJS.org"><img style="position: absolute; top: 0; right: 0; border: 0;" src="https://s3.amazonaws.com/github/ribbons/forkme_right_red_aa0000.png" alt="Fork me on GitHub"></a>
<div class="header">
<div class="outer-version">
<ul class="version">
<li>Version: 1.011.1030</li>
<li>Author: Maurits van der Schee</li>
<li>Hosting: Mark van Driel</li>
<li>License: GPL license</li>
</ul>
</div>
<p class="title"><img src="images/minjs_inv_small.png" alt="MinJS.logo"></p>
<p class="subtitle">A minimalistic framework for web applications that uses JSON.</p>
</div>
<div class="menu">
<ul>
<?php foreach($menu as $name): ?>
<li><a href="?<?php echo urlencode($name); ?>"><?php echo $name; ?></a></li>
<?php endforeach; ?>
<li><a href="minjs/">login</a></li>
</ul>
</div>
<div class="content">
<?php echo $content; ?>
</div>
</body>
</html>
