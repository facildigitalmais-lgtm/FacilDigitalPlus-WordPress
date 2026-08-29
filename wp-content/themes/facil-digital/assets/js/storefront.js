(() => {
    "use strict";

    const orderingFields =
        document.querySelectorAll(
            "[data-fd-orderby]"
        );

    orderingFields.forEach((field) => {
        field.addEventListener(
            "change",
            () => {
                const form =
                    field.closest(
                        "form"
                    );

                if (!form) {
                    return;
                }

                if (
                    typeof form.requestSubmit
                    === "function"
                ) {
                    form.requestSubmit();
                    return;
                }

                form.submit();
            }
        );
    });
})();
