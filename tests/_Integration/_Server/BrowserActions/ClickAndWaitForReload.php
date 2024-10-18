<!doctype html>
<html lang="de">
<head>
    <meta charset=utf-8>
    <title>Hello World</title>
</head>
<body>
<div>
    <a id="click" href="?reloaded=1">Click here</a>

    <?php if (isset($_GET['reloaded'])) { ?>
        <div id="reloaded">yes</div>
    <?php } ?>
</div>
</body>
</html>
