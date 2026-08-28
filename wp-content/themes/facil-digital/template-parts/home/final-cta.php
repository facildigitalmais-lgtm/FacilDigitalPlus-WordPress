<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

?>

<section class="fd-section fd-home-final-cta">
    <div class="fd-container">
        <div class="fd-final-cta">
            <div>
                <span class="fd-eyebrow">
                    Comece agora
                </span>

                <h2>
                    Encontre o material
                    certo para sua preparacao.
                </h2>

                <p>
                    Explore o catalogo Facil Digital+
                    e escolha sua proxima apostila.
                </p>
            </div>

            <a
                class="fd-button fd-button--primary fd-button--large"
                href="<?php
                    echo esc_url(
                        fd_theme_get_shop_url()
                    );
                ?>"
            >
                Explorar apostilas
            </a>
        </div>
    </div>
</section>