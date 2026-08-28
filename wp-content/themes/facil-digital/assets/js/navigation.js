(() => {
    "use strict";

    document.documentElement.classList.add(
        "fd-js"
    );

    const menuToggle =
        document.querySelector(
            "[data-fd-menu-toggle]"
        );

    const navigation =
        document.querySelector(
            "[data-fd-primary-nav]"
        );

    const overlay =
        document.querySelector(
            "[data-fd-navigation-overlay]"
        );

    const searchToggle =
        document.querySelector(
            "[data-fd-search-toggle]"
        );

    const searchPanel =
        document.querySelector(
            "[data-fd-search-panel]"
        );

    const mobileQuery =
        window.matchMedia(
            "(max-width: 1023px)"
        );

    let lastFocusedElement =
        null;

    function setMenuState(
        open,
        restoreFocus = false
    ) {
        if (
            !menuToggle
            || !navigation
        ) {
            return;
        }

        document.documentElement.classList.toggle(
            "fd-menu-open",
            open
        );

        menuToggle.setAttribute(
            "aria-expanded",
            open
                ? "true"
                : "false"
        );

        menuToggle.setAttribute(
            "aria-label",
            open
                ? "Fechar menu"
                : "Abrir menu"
        );

        if (overlay) {
            overlay.hidden =
                !open;
        }

        if (mobileQuery.matches) {
            navigation.inert =
                !open;
        } else {
            navigation.inert =
                false;
        }

        if (open) {
            lastFocusedElement =
                document.activeElement;

            const firstLink =
                navigation.querySelector(
                    "a"
                );

            if (firstLink) {
                window.requestAnimationFrame(
                    () => {
                        firstLink.focus();
                    }
                );
            }
        } else if (
            restoreFocus
            && lastFocusedElement
            && typeof lastFocusedElement.focus
                === "function"
        ) {
            lastFocusedElement.focus();

            lastFocusedElement =
                null;
        }
    }

    function setSearchState(
        open
    ) {
        if (
            !searchToggle
            || !searchPanel
        ) {
            return;
        }

        searchPanel.hidden =
            !open;

        searchToggle.setAttribute(
            "aria-expanded",
            open
                ? "true"
                : "false"
        );

        searchToggle.setAttribute(
            "aria-label",
            open
                ? "Fechar busca"
                : "Abrir busca"
        );

        if (open) {
            setMenuState(
                false
            );

            const input =
                searchPanel.querySelector(
                    'input[type="search"]'
                );

            if (input) {
                window.requestAnimationFrame(
                    () => {
                        input.focus();
                    }
                );
            }
        }
    }

    function syncViewport() {
        if (!navigation) {
            return;
        }

        if (mobileQuery.matches) {
            const menuOpen =
                document.documentElement
                    .classList
                    .contains(
                        "fd-menu-open"
                    );

            navigation.inert =
                !menuOpen;
        } else {
            navigation.inert =
                false;

            setMenuState(
                false
            );
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener(
            "click",
            () => {
                const open =
                    menuToggle.getAttribute(
                        "aria-expanded"
                    ) === "true";

                setSearchState(
                    false
                );

                setMenuState(
                    !open,
                    open
                );
            }
        );
    }

    if (overlay) {
        overlay.addEventListener(
            "click",
            () => {
                setMenuState(
                    false,
                    true
                );
            }
        );
    }

    if (navigation) {
        navigation.addEventListener(
            "click",
            (event) => {
                if (
                    !mobileQuery.matches
                ) {
                    return;
                }

                const link =
                    event.target.closest(
                        "a"
                    );

                if (!link) {
                    return;
                }

                setMenuState(
                    false
                );
            }
        );
    }

    if (
        searchToggle
        && searchPanel
    ) {
        searchToggle.addEventListener(
            "click",
            () => {
                const open =
                    searchToggle.getAttribute(
                        "aria-expanded"
                    ) === "true";

                setSearchState(
                    !open
                );
            }
        );
    }

    document.addEventListener(
        "keydown",
        (event) => {
            if (
                event.key !==
                "Escape"
            ) {
                return;
            }

            setSearchState(
                false
            );

            setMenuState(
                false,
                true
            );
        }
    );

    if (
        typeof mobileQuery.addEventListener
        === "function"
    ) {
        mobileQuery.addEventListener(
            "change",
            syncViewport
        );
    } else {
        mobileQuery.addListener(
            syncViewport
        );
    }

    syncViewport();
})();