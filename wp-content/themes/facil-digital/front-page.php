<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<div class="fd-home">
    <?php
    get_template_part(
        'template-parts/home/hero'
    );

    get_template_part(
        'template-parts/home/featured-contests'
    );

    get_template_part(
        'template-parts/home/featured-products'
    );

    get_template_part(
        'template-parts/home/benefits'
    );

    get_template_part(
        'template-parts/home/steps'
    );

    get_template_part(
        'template-parts/home/simulations'
    );

    get_template_part(
        'template-parts/home/faq'
    );

    get_template_part(
        'template-parts/home/final-cta'
    );
    ?>
</div>

<?php

get_footer();