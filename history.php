<?php
require __DIR__ . '/lib.php';

page_head('Crown History');
?>
<main class="stage">
    <h1>Crown history</h1>
    <?php history_list(history(null)) ?>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
</body>
</html>
