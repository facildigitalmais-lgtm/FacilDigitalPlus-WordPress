<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$contests =
    function_exists(
        'fd_theme_home_featured_contests'
    )
        ? fd_theme_home_featured_contests(6)
        : [];

if ($contests === []) {
    return;
}

?>

<section
    class="fd-section fd-section--soft fd-home-contests"
    id="concursos"
>
    <div class="fd-container">
        <?php
        get_template_part(
            'template-parts/components/section-heading',
            null,
            [
                'eyebrow' =>
                    'Concursos em destaque',

                'title' =>
                    'Encontre sua preparação por concurso',

                'text' =>
                    'Acesse rapidamente as apostilas disponíveis para os concursos selecionados pela Fácil Digital+.',
            ]
        );
        ?>

        <div class="fd-feature-grid fd-contest-grid">
            <?php foreach ($contests as $contest) : ?>
                <?php
                $url =
                    get_term_link(
                        $contest
                    );

                if (is_wp_error($url)) {
                    continue;
                }

                $count =
                    max(
                        0,
                        (int) $contest->count
                    );
                ?>

                <article class="fd-contest-card">
                    <a
                        class="fd-contest-card__link"
                        href="<?php
                            echo esc_url($url);
                        ?>"
                    >
                        <span class="fd-contest-card__type">
                            <?php
                            echo esc_html__(
                                'Concurso',
                                'facil-digital'
                            );
                            ?>
                        </span>

                        <h3>
                            <?php
                            echo esc_html(
                                $contest->name
                            );
                            ?>
                        </h3>

                        <span class="fd-contest-card__count">
                            <?php
                            echo esc_html(
                                sprintf(
                                    _n(
                                        '%d apostila disponível',
                                        '%d apostilas disponíveis',
                                        $count,
                                        'facil-digital'
                                    ),
                                    $count
                                )
                            );
                            ?>
                        </span>

                        <span class="fd-contest-card__cta">
                            <?php
                            echo esc_html__(
                                'Ver apostilas',
                                'facil-digital'
                            );
                            ?>

                            <span aria-hidden="true">
                                →
                            </span>
                        </span>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
