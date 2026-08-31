<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

<div class="fd-footer-bottom">
    <div class="fd-container fd-footer-bottom__inner">
        <p>
            &copy;
            <?php
            echo esc_html(
                wp_date('Y')
            );
            ?>
            Fácil Digital+.
            Todos os direitos reservados.
        </p>

        <p>
            Plataforma de materiais digitais
            para concursos públicos.
        </p>
    </div>
</div>
