(function () {
    'use strict';

    const config =
        window.fdCoursesLearning || {};

    const classroom =
        document.querySelector(
            '.fd-course-classroom'
        );

    if (!classroom) {
        return;
    }

    const ajaxPost = async function (
        action,
        payload
    ) {
        const body =
            new URLSearchParams();

        body.set(
            'action',
            action
        );

        body.set(
            'nonce',
            config.nonce || ''
        );

        Object.keys(payload)
            .forEach(function (key) {
                body.set(
                    key,
                    String(payload[key])
                );
            });

        const response = await fetch(
            config.ajaxUrl,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':
                        'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            }
        );

        return response.json();
    };

    const reloadOnCompletion =
        function (response) {
            if (
                !response
                || !response.success
                || !response.data
            ) {
                return;
            }

            if (
                response.data.lesson_completed
                || response.data.course_completed
            ) {
                window.setTimeout(
                    function () {
                        window.location.reload();
                    },
                    500
                );
            }
        };

    const textButton =
        document.querySelector(
            '.fd-course-complete-text'
        );

    if (textButton) {
        textButton.addEventListener(
            'click',
            async function () {
                if (
                    textButton.disabled
                ) {
                    return;
                }

                textButton.disabled = true;

                try {
                    const response =
                        await ajaxPost(
                            'fd_course_complete_text',
                            {
                                enrollment_id:
                                    textButton.dataset
                                        .enrollmentId,
                                lesson_id:
                                    textButton.dataset
                                        .lessonId
                            }
                        );

                    if (
                        !response.success
                    ) {
                        textButton.disabled =
                            false;
                        return;
                    }

                    reloadOnCompletion(
                        response
                    );
                } catch (error) {
                    textButton.disabled =
                        false;
                }
            }
        );
    }

    const playerElement =
        document.getElementById(
            'fd-course-player'
        );

    if (!playerElement) {
        return;
    }

    const videoId =
        playerElement.dataset.videoId;

    const enrollmentId =
        classroom.dataset.enrollmentId;

    const lessonId =
        classroom.dataset.lessonId;

    if (
        !videoId
        || !enrollmentId
        || !lessonId
    ) {
        return;
    }

    let player = null;
    let timer = null;
    let lastPosition = 0;
    let sending = false;
    let lessonFinished = false;

    const loadYouTubeApi =
        function () {
            if (
                window.YT
                && window.YT.Player
            ) {
                return Promise.resolve(
                    window.YT
                );
            }

            return new Promise(
                function (
                    resolve,
                    reject
                ) {
                    const previous =
                        window
                            .onYouTubeIframeAPIReady;

                    let settled = false;

                    const finish =
                        function () {
                            if (settled) {
                                return;
                            }

                            settled = true;

                            if (
                                typeof previous
                                === 'function'
                            ) {
                                try {
                                    previous();
                                } catch (error) {
                                }
                            }

                            resolve(window.YT);
                        };

                    window
                        .onYouTubeIframeAPIReady =
                            finish;

                    if (
                        !document.querySelector(
                            'script[data-fd-youtube-api]'
                        )
                    ) {
                        const script =
                            document.createElement(
                                'script'
                            );

                        script.src =
                            'https://www.youtube.com/iframe_api';

                        script.async = true;

                        script.dataset
                            .fdYoutubeApi =
                                '1';

                        script.onerror =
                            function () {
                                if (!settled) {
                                    settled =
                                        true;

                                    reject(
                                        new Error(
                                            'youtube_api_failed'
                                        )
                                    );
                                }
                            };

                        document.head
                            .appendChild(
                                script
                            );
                    }

                    window.setTimeout(
                        function () {
                            if (!settled) {
                                reject(
                                    new Error(
                                        'youtube_api_timeout'
                                    )
                                );
                            }
                        },
                        15000
                    );
                }
            );
        };

    const stopTimer =
        function () {
            if (timer !== null) {
                window.clearInterval(
                    timer
                );

                timer = null;
            }
        };

    const updateLastPosition =
        function () {
            if (!player) {
                return 0;
            }

            const current =
                Math.max(
                    0,
                    Number(
                        player.getCurrentTime()
                        || 0
                    )
                );

            let delta =
                current
                - lastPosition;

            if (
                delta < 0
                || delta > 25
            ) {
                delta = 0;
            }

            lastPosition = current;

            return delta;
        };

    const sendProgress =
        async function () {
            if (
                sending
                || lessonFinished
                || !player
            ) {
                return;
            }

            const delta =
                updateLastPosition();

            if (delta <= 0) {
                return;
            }

            sending = true;

            try {
                const response =
                    await ajaxPost(
                        'fd_course_progress',
                        {
                            enrollment_id:
                                enrollmentId,
                            lesson_id:
                                lessonId,
                            position:
                                Math.round(
                                    player
                                        .getCurrentTime()
                                    || 0
                                ),
                            duration:
                                Math.round(
                                    player
                                        .getDuration()
                                    || 0
                                ),
                            watched_delta:
                                Math.round(
                                    delta
                                )
                        }
                    );

                if (
                    response.success
                    && response.data
                    && response.data
                        .lesson_completed
                ) {
                    lessonFinished = true;
                    stopTimer();
                }

                reloadOnCompletion(
                    response
                );
            } catch (error) {
            } finally {
                sending = false;
            }
        };

    const startTimer =
        function () {
            if (
                !player
                || timer !== null
            ) {
                return;
            }

            lastPosition =
                Number(
                    player.getCurrentTime()
                    || 0
                );

            timer = window.setInterval(
                sendProgress,
                Number(
                    config.heartbeatMs
                    || 10000
                )
            );
        };

    const pauseTracking =
        function () {
            stopTimer();

            sendProgress();
        };

    loadYouTubeApi()
        .then(function () {
            player = new window.YT.Player(
                'fd-course-player',
                {
                    videoId: videoId,
                    playerVars: {
                        playsinline: 1,
                        rel: 0,
                        origin:
                            window.location
                                .origin
                    },
                    events: {
                        onReady:
                            function (event) {
                                lastPosition =
                                    Number(
                                        event.target
                                            .getCurrentTime()
                                        || 0
                                    );
                            },
                        onStateChange:
                            function (event) {
                                if (
                                    event.data
                                    === window.YT
                                        .PlayerState
                                        .PLAYING
                                ) {
                                    startTimer();
                                    return;
                                }

                                if (
                                    event.data
                                    === window.YT
                                        .PlayerState
                                        .PAUSED
                                    || event.data
                                    === window.YT
                                        .PlayerState
                                        .BUFFERING
                                    || event.data
                                    === window.YT
                                        .PlayerState
                                        .ENDED
                                ) {
                                    pauseTracking();
                                }
                            }
                    }
                }
            );
        })
        .catch(function () {
            playerElement.innerHTML =
                '<p>Não foi possível carregar o vídeo.</p>';
        });

    window.addEventListener(
        'pagehide',
        function () {
            stopTimer();
        }
    );
})();