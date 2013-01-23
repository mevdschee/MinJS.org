<?php
if (isset($_FILES['file']))
{ $filename = $_FILES['file']['tmp_name'];
  $_FILES['file']['data'] = '';
  if (is_uploaded_file($filename))
  { $_FILES['file']['data'] = bin2hex(file_get_contents($filename));
    unset($_FILES['file']['error']);
    unset($_FILES['file']['tmp_name']);
  }
  else unset($_FILES['file']);
}
if (isset($_FILES['file']))
{
?>
<html>
<head>
</head>
<body>
<script type="text/javascript">
parent.minjs.file(document.location.href.split('?')[1],<?php echo json_encode($_FILES['file']); ?>);
</script>
</body>
</html>
<?php } else { ?>
<html><head>
<style>
html, body, form { margin: 0; border:0; padding: 0; overflow: hidden; }
</style>
</head><body>
<script type="text/javascript">
document.write('<form method="post" enctype="multipart/form-data" action="file.php?'+document.location.href.split('?')[1]+'">');
</script>
<input name="file" type="file" onchange="document.forms[0].submit();"><br>
</form>
</body></html>
<?php } ?>
