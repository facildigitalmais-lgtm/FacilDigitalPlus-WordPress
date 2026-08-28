<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$eyebrow =
    isset($args['eyebrow'])
    && is_string($args['eyebrow'])
        ? $args['eyebrow']
        : '';

$title =
    isset($args['title'])
    && is_string($args['title'])
        ? $args['title']
        : '';

$text =
    isset($args['text'])
    && is_string($args['text'])
        ? $args['text']
        : '';

$center =
    !empty($args['center']);

?>

<div
    class="fd-section-heading<?php
        echo $center
            ? ' fd-section-heading--center'
            : '';
    ?>"
>
    <?php if ($eyebrow !== '') : ?>
        <span class="fd-eyebrow">
            <?php
            echo esc_html(
                $eyebrow
            );
            ?>
        </span>
    <?php endif; ?>

    <?php if ($title !== '') : ?>
        <h2>
            <?php
            echo esc_html(
                $title
            );
            ?>
        </h2>
    <?php endif; ?>

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