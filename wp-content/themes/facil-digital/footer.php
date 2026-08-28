<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>
</main>

<footer class="fd-footer">
    <div class="fd-container">
        <strong>Facil Digital+</strong>

        <p>
            Apostilas digitais e preparacao
            para concursos publicos.
        </p>

        <p class="fd-footer__copyright">
            &copy;
            <?php echo esc_html(gmdate('Y')); ?>
            Facil Digital+.
        </p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
