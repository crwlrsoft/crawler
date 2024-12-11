<!Doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Non UTF-8 charset page</title>
</head>
<body>
<div class="element">
<?php
    $string = '';

    // 178 is square (² in ISO-8859-1) but broken in UTF-8
    foreach ([48, 32, 108, 47, 109, 178] as $ord) {
        $string .= chr($ord);
    }

    echo $string;
?>
</div>
</body>
</html>
