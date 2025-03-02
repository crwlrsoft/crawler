<!doctype html>
<html lang="de">
<head>
    <meta charset=utf-8>
    <title>Hello World</title>
</head>
<body>
<div>
    <div id="click">Click here</div>

    <script>
        document.getElementById('click').addEventListener('click', function (ev) {
            setTimeout(function () {
                window.location.href = '/browser-actions/click-and-wait-for-reload?reloaded=1';
            }, 200);
        })
    </script>

    <?php if (isset($_GET['reloaded'])) { ?>
        <div id="reloaded">yes</div>
    <?php } ?>
</div>
</body>
</html>
