document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const lessonType = document.querySelector(
        'select[name="lesson_type"]'
    );

    const conditionalLessonFields = document.querySelectorAll(
        '[data-lesson-types]'
    );

    const syncLessonFields = function () {
        if (!lessonType) {
            return;
        }

        conditionalLessonFields.forEach(function (field) {
            const allowedTypes = (
                field.dataset.lessonTypes || ''
            )
                .split(',')
                .map(function (type) {
                    return type.trim();
                });

            field.hidden = !allowedTypes.includes(
                lessonType.value
            );
        });
    };

    if (lessonType) {
        lessonType.addEventListener(
            'change',
            syncLessonFields
        );

        syncLessonFields();
    }

    const coverInput = document.querySelector(
        '[data-course-cover-input]'
    );

    const coverPreview = document.querySelector(
        '[data-course-cover-preview]'
    );

    const coverSelect = document.querySelector(
        '[data-course-cover-select]'
    );

    const coverRemove = document.querySelector(
        '[data-course-cover-remove]'
    );

    let courseCoverFrame = null;

    const courseCoverImageUrl = function (attachment) {
        const sizes = attachment.sizes || {};

        if (sizes.medium_large && sizes.medium_large.url) {
            return sizes.medium_large.url;
        }

        if (sizes.medium && sizes.medium.url) {
            return sizes.medium.url;
        }

        return attachment.url || '';
    };

    const showCourseCoverPlaceholder = function () {
        if (!coverPreview) {
            return;
        }

        coverPreview.innerHTML = '';

        const placeholder = document.createElement('div');
        placeholder.className = 'fd-course-cover__placeholder';
        placeholder.setAttribute(
            'data-course-cover-placeholder',
            ''
        );

        const icon = document.createElement('span');
        icon.className =
            'dashicons dashicons-format-image';
        icon.setAttribute('aria-hidden', 'true');

        const title = document.createElement('strong');
        title.textContent = 'Nenhuma capa selecionada';

        const help = document.createElement('span');
        help.textContent =
            'Escolha uma imagem da Biblioteca de Mídia.';

        placeholder.appendChild(icon);
        placeholder.appendChild(title);
        placeholder.appendChild(help);
        coverPreview.appendChild(placeholder);
    };

    const applyCourseCover = function (attachment) {
        if (
            !coverInput
            || !coverPreview
            || !coverSelect
            || !coverRemove
        ) {
            return;
        }

        const attachmentId = parseInt(
            attachment.id,
            10
        );

        const imageUrl =
            courseCoverImageUrl(attachment);

        if (
            !Number.isInteger(attachmentId)
            || attachmentId <= 0
            || !imageUrl
        ) {
            return;
        }

        coverInput.value = String(attachmentId);
        coverPreview.innerHTML = '';

        const image = document.createElement('img');
        image.className = 'fd-course-cover__image';
        image.src = imageUrl;
        image.alt =
            attachment.alt
            || attachment.title
            || 'Capa do curso';

        coverPreview.appendChild(image);
        coverSelect.textContent = 'Alterar capa';
        coverRemove.hidden = false;
    };

    if (
        coverInput
        && coverPreview
        && coverSelect
        && coverRemove
    ) {
        coverSelect.addEventListener(
            'click',
            function () {
                if (
                    !window.wp
                    || !window.wp.media
                ) {
                    return;
                }

                if (!courseCoverFrame) {
                    courseCoverFrame = window.wp.media({
                        title: 'Selecionar capa do curso',
                        button: {
                            text: 'Usar como capa',
                        },
                        library: {
                            type: 'image',
                        },
                        multiple: false,
                    });

                    courseCoverFrame.on(
                        'select',
                        function () {
                            const selection =
                                courseCoverFrame
                                    .state()
                                    .get('selection')
                                    .first();

                            if (!selection) {
                                return;
                            }

                            applyCourseCover(
                                selection.toJSON()
                            );
                        }
                    );
                }

                courseCoverFrame.open();
            }
        );

        coverRemove.addEventListener(
            'click',
            function () {
                coverInput.value = '0';
                showCourseCoverPlaceholder();
                coverSelect.textContent =
                    'Selecionar capa';
                coverRemove.hidden = true;
            }
        );
    }

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