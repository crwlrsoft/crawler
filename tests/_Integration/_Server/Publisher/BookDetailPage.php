<!doctype html>
<html lang="en">
<head>
    <meta charset=utf-8>
    <title>Book</title>
</head>
<body>
<h1>
    <?php
        if ($bookNo === 1) {
            echo "Some novel";
        } elseif ($bookNo === 2) {
            echo "Another novel";
        } elseif ($bookNo === 3) {
            echo "Poems #1";
        } elseif ($bookNo === 4) {
            echo "Poems #2";
        } elseif ($bookNo === 5) {
            echo "Poems #3";
        }
    ?>
</h1>
</body>
</html>
