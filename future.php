<?php
require __DIR__ . '/lib.php';

page_head('Future Matches');
?>
<main class="stage">
    <h1>Future matches</h1>
    <?php future_list(future_matches()) ?>
</main>
<a class="admin-link" href="./">← Back to the crown</a>
</body>
</html>
