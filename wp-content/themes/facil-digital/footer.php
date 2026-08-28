<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

</main>

<footer class="fd-site-footer">
    <?php
    get_template_part(
        'template-parts/footer/footer-main'
    );
    ?>

    <?php
    get_template_part(
        'template-parts/footer/footer-bottom'
    );
    ?>
</footer>

<?php wp_footer(); ?>

</body>
</html>