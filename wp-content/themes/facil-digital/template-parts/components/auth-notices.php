<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$errors =
    fd_theme_auth_get_errors();

$success =
    isset($args['success'])
    && is_string($args['success'])
        ? $args['success']
        : '';

?>

<?php if ($errors !== []) : ?>
    <div
        class="fd-notice fd-notice--error"
        role="alert"
        aria-live="assertive"
    >
        <strong>
            Verifique os dados informados:
        </strong>

        <ul>
            <?php
            foreach ($errors as $error) :
                ?>
                <li>
                    <?php
                    echo esc_html(
                        (string) $error
                    );
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success !== '') : ?>
    <div
        class="fd-notice fd-notice--success"
        role="status"
        aria-live="polite"
    >
        <?php
        echo esc_html(
            $success
        );
        ?>
    </div>
<?php endif; ?>
