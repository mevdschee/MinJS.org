<?php 

require "minjs/minjs.php"; 

$list = $minjs->query('list','queries.list');
foreach ($list as $l)
{ echo "$l<br/>";
}