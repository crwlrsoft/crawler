<!doctype html>
<html lang="de">
<head>
    <meta charset=utf-8>
    <title>Hello World</title>
</head>
<body>
<div>
    <div id="insert_here"></div>

    <script>
        setTimeout(function () {
            document.getElementById('insert_here').innerHTML = '<div id="delayed_container">hooray</div>';
        }, 200);
    </script>
</div>
</body>
</html>
