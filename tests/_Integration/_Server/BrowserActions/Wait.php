<!doctype html>
<html lang="de">
<head>
    <meta charset=utf-8>
    <title>Hello World</title>
</head>
<body>
<div>
    <div id="delayed_container"></div>

    <script>
        setTimeout(function () {
            document.getElementById('delayed_container').innerHTML = 'hooray';
        }, 200);
    </script>
</div>
</body>
</html>
