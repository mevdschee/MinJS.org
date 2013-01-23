<?php 

require "minjs/minjs.php"; 

$list = $minjs->query('list','controllers.list');
foreach ($list as $l)
{ echo "$l<br/>";
}