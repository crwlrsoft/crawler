<!doctype html>
<html lang="de">
<head>
    <meta charset=utf-8>
    <title>Hello World</title>
</head>
<body>
<div>
    <div id="delayed_el_container"></div>

    <div id="click_worked"></div>
    <div id="click_element" onclick="document.getElementById('click_worked').innerHTML = 'yes'">Click me</div>

    <div id="evaluation_container"></div>

    <script>
        setTimeout(function () {
            document.getElementById('delayed_el_container').innerHTML = '<div id="delayed_el">a</div>';
        }, 300);
    </script>
</div>
</body>
</html>
