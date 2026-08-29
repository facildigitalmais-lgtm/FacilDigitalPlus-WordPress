(() => {
    "use strict";

    const fields =
        document.querySelectorAll(
            "[data-fd-orderby], [data-fd-autosubmit]"
        );

    fields.forEach((field) => {
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
