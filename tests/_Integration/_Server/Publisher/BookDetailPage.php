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

<div id="editions">
<?php
    if (in_array($bookNo, [1, 3, 4, 5])) {
        // Some Novel
        echo '<a href="/publisher/books/' . $bookNo . '/edition/1">First Edition</a> ' .
            '<a href="/publisher/books/' . $bookNo . '/edition/2">Second Edition</a>';
    } elseif ($bookNo === 2) {
        // Another Novel
        echo '<a href="/publisher/books/' . $bookNo . '/edition/1">First Edition</a> ' .
            '<a href="/publisher/books/' . $bookNo . '/edition/2">Second Edition</a> ' .
            '<a href="/publisher/books/' . $bookNo . '/edition/3">Third Edition</a>';
    }
?>
</div>
</body>
</html>
