<!doctype html>
<html lang="en">
<head>
    <meta charset=utf-8>
    <title>Book Edition</title>
</head>
<body>
<?php
    if ($bookNo === 1) {
        // Some Novel
        if ($edition === 1) {
            echo '<span class="year">1996</span> <span class="publishingCompany">Foo</span>';
        } elseif ($edition === 2) {
            echo '<span class="year">2005</span> <span class="publishingCompany">Foo</span>';
        }
    } elseif ($bookNo === 2) {
        // Another Novel
        if ($edition === 1) {
            echo '<span class="year">2001</span> <span class="publishingCompany">Foo</span>';
        } elseif ($edition === 2) {
            echo '<span class="year">2009</span> <span class="publishingCompany">Bar</span>';
        } elseif ($edition === 3) {
            echo '<span class="year">2017</span> <span class="publishingCompany">Bar</span>';
        }
    } elseif ($bookNo === 3) {
        // Poems #1
        if ($edition === 1) {
            echo '<span class="year">2008</span> <span class="publishingCompany">Poems</span>';
        } elseif ($edition === 2) {
            echo '<span class="year">2009</span> <span class="publishingCompany">Poems</span>';
        }
    } elseif ($bookNo === 4) {
        // Poems #2
        if ($edition === 1) {
            echo '<span class="year">2011</span> <span class="publishingCompany">Poems</span>';
        } elseif ($edition === 2) {
            echo '<span class="year">2014</span> <span class="publishingCompany">New Poems</span>';
        }
    } elseif ($bookNo === 5) {
        // Poems #3
        if ($edition === 1) {
            echo '<span class="year">2013</span> <span class="publishingCompany">Poems</span>';
        } elseif ($edition === 2) {
            echo '<span class="year">2017</span> <span class="publishingCompany">New Poems</span>';
        }
    }
?>
</body>
</html>
