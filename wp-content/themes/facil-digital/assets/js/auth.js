(() => {
    "use strict";

    const toggles =
        document.querySelectorAll(
            "[data-fd-password-toggle]"
        );

    toggles.forEach((toggle) => {
        toggle.addEventListener(
            "click",
            () => {
                const wrapper =
                    toggle.closest(
                        ".fd-password-field"
                    );

                if (!wrapper) {
                    return;
                }

                const input =
                    wrapper.querySelector(
                        'input[type="password"], input[type="text"]'
                    );

                if (!input) {
                    return;
                }

                const showing =
                    input.type === "text";

                input.type =
                    showing
                        ? "password"
                        : "text";

                toggle.textContent =
                    showing
                        ? "Mostrar"
                        : "Ocultar";

                toggle.setAttribute(
                    "aria-label",
                    showing
                        ? "Mostrar senha"
                        : "Ocultar senha"
                );
            }
        );
    });

    const registerForm =
        document.querySelector(
            "[data-fd-register-form]"
        );

    if (!registerForm) {
        return;
    }

    const password =
        registerForm.querySelector(
            "[data-fd-password]"
        );

    const confirmation =
        registerForm.querySelector(
            "[data-fd-password-confirm]"
        );

    const message =
        registerForm.querySelector(
            "[data-fd-password-match]"
        );

    function validateMatch() {
        if (
            !password
            || !confirmation
            || !message
        ) {
            return;
        }

        if (
            confirmation.value === ""
        ) {
            message.textContent = "";
            message.removeAttribute(
                "data-state"
            );

            return;
        }

        const matches =
            password.value
            === confirmation.value;

        message.textContent =
            matches
                ? "As senhas coincidem."
                : "As senhas nao coincidem.";

        message.dataset.state =
            matches
                ? "valid"
                : "invalid";

        confirmation.setCustomValidity(
            matches
                ? ""
                : "As senhas nao coincidem."
        );
    }

    if (password) {
        password.addEventListener(
            "input",
            validateMatch
        );
    }

    if (confirmation) {
        confirmation.addEventListener(
            "input",
            validateMatch
        );
    }
})();