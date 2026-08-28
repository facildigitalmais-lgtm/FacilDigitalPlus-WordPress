<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

get_header();

?>

<section class="fd-content">
    <div class="fd-container">
        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : ?>
                <?php the_post(); ?>

                <article <?php post_class('fd-article'); ?>>
                    <h1>
                        <?php the_title(); ?>
                    </h1>

                    <div class="fd-article__content">
                        <?php the_content(); ?>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php else : ?>
            <p>
                Nenhum conteudo encontrado.
            </p>
        <?php endif; ?>
    </div>
</section>

<?php

get_footer();
