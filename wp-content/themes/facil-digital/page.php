<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<section class="fd-section">
    <div class="fd-container fd-page-container">
        <?php
        while (have_posts()) :
            the_post();
            ?>

            <article
                id="post-<?php the_ID(); ?>"
                <?php post_class('fd-page'); ?>
            >
                <header class="fd-page-header">
                    <h1>
                        <?php
                        the_title();
                        ?>
                    </h1>
                </header>

                <div class="fd-page-content">
                    <?php
                    the_content();
                    ?>
                </div>
            </article>

        <?php
        endwhile;
        ?>
    </div>
</section>

<?php

get_footer();