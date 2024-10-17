<?php setcookie('testcookie', 'foo123'); ?>
<!doctype html>
<html lang="de">
<head><meta charset=utf-8><title>yo</title></head>
<body>
<div>{$cookies}</div>
<script>document.cookie = "testcookie=javascriptcookie";</script>
</body>
</html>
