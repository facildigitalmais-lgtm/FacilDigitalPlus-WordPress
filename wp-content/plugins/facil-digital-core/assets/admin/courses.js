document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const builder = document.querySelector('.fd-course-builder');

    if (!builder) {
        return;
    }

    let dragged = null;

    const syncOrder = function () {
        const modules = Array.from(
            builder.querySelectorAll(
                '.fd-course-modules > .fd-course-module'
            )
        );

        const moduleIds = [];
        const lessonOrder = {};

        modules.forEach(function (module) {
            const moduleId = module.dataset.moduleId;

            moduleIds.push(moduleId);

            lessonOrder[moduleId] = Array.from(
                module.querySelectorAll(
                    '.fd-course-lessons > .fd-course-lesson'
                )
            ).map(function (lesson) {
                return parseInt(
                    lesson.dataset.lessonId,
                    10
                );
            });
        });

        const form = builder.querySelector(
            '.fd-course-reorder-form'
        );

        if (!form) {
            return;
        }

        form.querySelector(
            '[name="module_order"]'
        ).value = moduleIds.join(',');

        form.querySelector(
            '[name="lesson_order"]'
        ).value = JSON.stringify(
            lessonOrder
        );
    };

    const move = function (element, direction) {
        if (!element || !element.parentElement) {
            return;
        }

        if (direction === 'up') {
            const previous =
                element.previousElementSibling;

            if (previous) {
                element.parentElement.insertBefore(
                    element,
                    previous
                );
            }
        } else {
            const next =
                element.nextElementSibling;

            if (next) {
                element.parentElement.insertBefore(
                    next,
                    element
                );
            }
        }

        syncOrder();
    };

    builder.addEventListener(
        'click',
        function (event) {
            const up = event.target.closest(
                '.fd-course-move-up'
            );

            const down = event.target.closest(
                '.fd-course-move-down'
            );

            if (!up && !down) {
                return;
            }

            event.preventDefault();

            const element =
                event.target.closest(
                    '.fd-course-lesson, .fd-course-module'
                );

            move(
                element,
                up ? 'up' : 'down'
            );
        }
    );

    builder.addEventListener(
        'dragstart',
        function (event) {
            const handle = event.target.closest(
                '.fd-course-drag-handle'
            );

            if (!handle) {
                event.preventDefault();
                return;
            }

            dragged = handle.closest(
                '.fd-course-lesson, .fd-course-module'
            );

            if (!dragged) {
                return;
            }

            dragged.classList.add(
                'is-dragging'
            );

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed =
                    'move';
            }
        }
    );

    builder.addEventListener(
        'dragover',
        function (event) {
            if (!dragged) {
                return;
            }

            const isModule =
                dragged.classList.contains(
                    'fd-course-module'
                );

            const selector = isModule
                ? '.fd-course-module'
                : '.fd-course-lesson';

            const target =
                event.target.closest(selector);

            if (
                !target
                || target === dragged
            ) {
                return;
            }

            if (!isModule) {
                const draggedList =
                    dragged.closest(
                        '.fd-course-lessons'
                    );

                const targetList =
                    target.closest(
                        '.fd-course-lessons'
                    );

                if (
                    !draggedList
                    || !targetList
                ) {
                    return;
                }
            }

            event.preventDefault();

            const rect =
                target.getBoundingClientRect();

            const before =
                event.clientY
                < rect.top
                    + rect.height / 2;

            target.parentElement.insertBefore(
                dragged,
                before
                    ? target
                    : target.nextElementSibling
            );
        }
    );

    builder.addEventListener(
        'dragend',
        function () {
            if (dragged) {
                dragged.classList.remove(
                    'is-dragging'
                );
            }

            dragged = null;
            syncOrder();
        }
    );

    const reorderForm =
        builder.querySelector(
            '.fd-course-reorder-form'
        );

    if (reorderForm) {
        reorderForm.addEventListener(
            'submit',
            syncOrder
        );
    }

    syncOrder();
});