<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$url =
    get_permalink();

if (!is_string($url)) {
    return;
}

?>

<article
    id="post-<?php the_ID(); ?>"
    <?php post_class('fd-search-card'); ?>
>
    <span class="fd-search-card__type">
        <?php
        echo esc_html(
            get_post_type_object(
                get_post_type()
            )?->labels?->singular_name
            ?? __(
                'Conteúdo',
                'facil-digital'
            )
        );
        ?>
    </span>

    <h2>
        <a
            href="<?php
                echo esc_url(
                    $url
                );
            ?>"
        >
            <?php
            the_title();
            ?>
        </a>
    </h2>

    <p>
        <?php
        echo esc_html(
            wp_trim_words(
                get_the_excerpt(),
                28
            )
        );
        ?>
    </p>

    <a
        class="fd-text-link"
        href="<?php
            echo esc_url(
                $url
            );
        ?>"
    >
        <?php
        echo esc_html__(
            'Abrir',
            'facil-digital'
        );
        ?>
    </a>
</article>
