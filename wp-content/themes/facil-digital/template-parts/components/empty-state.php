<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$title =
    isset($args['title'])
    && is_string($args['title'])
        ? $args['title']
        : 'Nenhum item encontrado';

$text =
    isset($args['text'])
    && is_string($args['text'])
        ? $args['text']
        : '';

?>

<div class="fd-empty-state">
    <strong>
        <?php
        echo esc_html(
            $title
        );
        ?>
    </strong>

    <?php if ($text !== '') : ?>
        <p>
            <?php
            echo esc_html(
                $text
            );
            ?>
        </p>
    <?php endif; ?>
</div>