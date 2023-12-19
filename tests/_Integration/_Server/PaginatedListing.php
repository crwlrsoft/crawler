<!Doctype html>
<html>
<head>
    <title>Paginated Listing</title>
</head>
<body>
<div id="listing">
    <?php
        $page = $_GET['page'] ?? 1;
        $additionalFooQueryParam = '';
        $itemsPerPage = 3;

        if (!empty($_GET['foo'])) {
            $additionalFooQueryParam = '&foo=' . $_GET['foo'];
        }

        if ($page < 4) {
            for ($i = 1; $i < 4; $i++) {
                $itemNumber = (($page - 1) * $itemsPerPage) + $i; ?>
                <div class="item">
                    <a href="/paginated-listing/items/<?=$itemNumber?>">Item <?=$itemNumber?></a>
                    <p>asdlfkj asdlfka jsdlfk ajsdflk</p>
                </div>
            <?php } ?>
        <?php } else {
            $itemNumber = (($page - 1) * $itemsPerPage) + 1; ?>
            <div class="item">
                <a href="/paginated-listing/items/<?=$itemNumber?>">Item <?=$itemNumber?></a>
                <p>asdflk jasdlfk asdlfk asldfk</p>
            </div>
        <?php } ?>

    <div id="pagination">
        <?php if ($page > 1) { ?>
            <a id="prevPage" href="/paginated-listing?page=<?=($page - 1) . $additionalFooQueryParam?>">&lt;&lt;</a>
        <?php } ?>

        <?php if ($page < 4) { ?>
            <a id="nextPage" href="/paginated-listing?page=<?=($page + 1) . $additionalFooQueryParam?>">&gt;&gt;</a>
        <?php } ?>
    </div>
</div>
</body>
</html>
