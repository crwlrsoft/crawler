<!doctype html>
<html lang="en">
<head>
    <meta charset=utf-8>
    <title><?=$author?></title>
</head>
<body>
<h1>
    <?php
        if ($author === 'john') {
            echo "John Example";
        } else {
            echo "Susan Example";
        }
    ?>
</h1>

<div id="author-data">
    <div class="age">
        <?php
            if ($author === 'john') {
                echo "51";
            } else {
                echo "49";
            }
        ?>
    </div>
    <div class="born-in">
        <?php
            if ($author === 'john') {
                echo "Lisbon";
            } else {
                echo "Athens";
            }
        ?>
    </div>
    <div class="books">
        <?php
        if ($author === 'john') { ?>
            <a class="book" href="/publisher/books/1"><img src="/images/book1.jpg" /></a>
            <a class="book" href="/publisher/books/2"><img src="/images/book2.jpg" /></a>
        <?php } else { ?>
            <a class="book" href="/publisher/books/3"><img src="/images/book3.jpg" /></a>
            <a class="book" href="/publisher/books/4"><img src="/images/book4.jpg" /></a>
            <a class="book" href="/publisher/books/5"><img src="/images/book5.jpg" /></a>
        <?php } ?>
    </div>
</div>
</body>
</html>
