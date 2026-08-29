(() => {
    'use strict';

    const app = document.getElementById('fd-simulation-app');
    const config = window.fdSimulationConfig;
    if (!app || !config) {
        return;
    }

    const els = {
        start: document.getElementById('fd-simulation-start'),
        startButton: document.getElementById('fd-simulation-start-button'),
        workspace: document.getElementById('fd-simulation-workspace'),
        progress: document.getElementById('fd-simulation-progress'),
        saveStatus: document.getElementById('fd-simulation-save-status'),
        timer: document.getElementById('fd-simulation-timer'),
        subject: document.getElementById('fd-simulation-subject'),
        statement: document.getElementById('fd-simulation-statement'),
        options: document.getElementById('fd-simulation-options'),
        numbers: document.getElementById('fd-simulation-numbers'),
        prev: document.getElementById('fd-simulation-prev'),
        next: document.getElementById('fd-simulation-next'),
        finish: document.getElementById('fd-simulation-finish'),
        result: document.getElementById('fd-simulation-result'),
        error: document.getElementById('fd-simulation-error'),
    };

    const state = {
        attemptId: 0,
        questions: [],
        current: 0,
        deadlineMs: 0,
        timerId: 0,
        finishing: false,
    };

    const api = async (path, options = {}) => {
        const response = await fetch(`${config.restRoot}${path}`, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': config.nonce,
                ...(options.headers || {}),
            },
        });
        let body = {};
        try {
            body = await response.json();
        } catch (_error) {
            body = {};
        }
        if (!response.ok) {
            const error = new Error(body.message || config.messages.genericError);
            error.code = body.code || 'request_failed';
            throw error;
        }
        return body;
    };

    const showError = (message) => {
        els.error.textContent = message || config.messages.genericError;
        els.error.hidden = false;
    };

    const clearError = () => {
        els.error.hidden = true;
        els.error.textContent = '';
    };

    const formatTime = (seconds) => {
        const value = Math.max(0, Math.floor(seconds));
        const h = String(Math.floor(value / 3600)).padStart(2, '0');
        const m = String(Math.floor((value % 3600) / 60)).padStart(2, '0');
        const s = String(value % 60).padStart(2, '0');
        return `${h}:${m}:${s}`;
    };

    const answered = (question) => Number(question.selected_option_id || 0) > 0;

    const renderNumbers = () => {
        els.numbers.replaceChildren();
        state.questions.forEach((question, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(index + 1);
            button.className = 'fd-simulation-number';
            if (index === state.current) {
                button.classList.add('is-current');
            }
            if (answered(question)) {
                button.classList.add('is-answered');
            }
            button.setAttribute('aria-label', `Ir para questão ${index + 1}`);
            button.addEventListener('click', () => {
                state.current = index;
                renderQuestion();
            });
            els.numbers.append(button);
        });
    };

    const renderQuestion = () => {
        const question = state.questions[state.current];
        if (!question) {
            return;
        }
        els.progress.textContent = `Questão ${state.current + 1} de ${state.questions.length}`;
        els.subject.textContent = [question.subject, question.topic].filter(Boolean).join(' · ');
        els.statement.textContent = question.statement;
        els.options.replaceChildren();

        question.options.forEach((option) => {
            const label = document.createElement('label');
            label.className = 'fd-simulation-option';
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = `question-${question.id}`;
            input.value = String(option.id);
            input.checked = Number(question.selected_option_id) === Number(option.id);
            input.addEventListener('change', () => saveAnswer(question, option.id));
            const key = document.createElement('strong');
            key.textContent = option.key;
            const text = document.createElement('span');
            text.textContent = option.text;
            label.append(input, key, text);
            els.options.append(label);
        });

        els.prev.disabled = state.current === 0;
        els.next.disabled = state.current >= state.questions.length - 1;
        renderNumbers();
    };

    const saveAnswer = async (question, optionId) => {
        clearError();
        els.saveStatus.textContent = config.messages.saving;
        try {
            const saved = await api(`/attempts/${state.attemptId}/answers`, {
                method: 'POST',
                body: JSON.stringify({
                    question_id: question.id,
                    selected_option_id: optionId,
                }),
            });
            question.selected_option_id = saved.selected_option_id;
            els.saveStatus.textContent = config.messages.saved;
            renderNumbers();
        } catch (error) {
            els.saveStatus.textContent = '';
            showError(error.message);
            if (error.code === 'attempt_expired' || error.code === 'attempt_closed') {
                await loadResult();
            }
        }
    };

    const startTimer = (remainingSeconds) => {
        if (state.timerId) {
            window.clearInterval(state.timerId);
        }
        state.deadlineMs = Date.now() + (Math.max(0, remainingSeconds) * 1000);
        const tick = async () => {
            const remaining = Math.max(0, Math.ceil((state.deadlineMs - Date.now()) / 1000));
            els.timer.textContent = formatTime(remaining);
            if (remaining <= 0) {
                window.clearInterval(state.timerId);
                state.timerId = 0;
                if (!state.finishing) {
                    await finish(false);
                }
            }
        };
        tick();
        state.timerId = window.setInterval(tick, 1000);
    };

    const mountAttempt = (payload) => {
        if (payload.status === 'completed' && payload.result) {
            renderResult(payload.result);
            return;
        }
        state.attemptId = Number(payload.attempt_id);
        state.questions = Array.isArray(payload.questions) ? payload.questions : [];
        state.current = 0;
        els.start.hidden = true;
        els.result.hidden = true;
        els.workspace.hidden = false;
        renderQuestion();
        startTimer(Number(payload.remaining_seconds || 0));
    };

    const renderResult = (result) => {
        if (state.timerId) {
            window.clearInterval(state.timerId);
            state.timerId = 0;
        }
        els.workspace.hidden = true;
        els.start.hidden = true;
        els.result.hidden = false;
        els.result.replaceChildren();

        const heading = document.createElement('h2');
        heading.textContent = 'Resultado';
        const score = document.createElement('p');
        score.className = 'fd-simulation-result__score';
        score.textContent = `${Number(result.percentage).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
        const counts = document.createElement('p');
        counts.textContent = `Acertos: ${result.correct_count} · Erros: ${result.incorrect_count} · Em branco: ${result.unanswered_count}`;
        const status = document.createElement('p');
        status.className = result.passed ? 'is-approved' : 'is-not-approved';
        status.textContent = result.passed ? 'Nota mínima alcançada' : 'Abaixo da nota mínima';
        els.result.append(heading, score, counts, status);

        if (Array.isArray(result.breakdown) && result.breakdown.length) {
            const breakdown = document.createElement('div');
            breakdown.className = 'fd-simulation-breakdown';
            result.breakdown.forEach((row) => {
                const item = document.createElement('div');
                const title = document.createElement('strong');
                title.textContent = row.subject;
                const text = document.createElement('span');
                text.textContent = `${row.correct}/${row.total} · ${Number(row.percentage).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
                item.append(title, text);
                breakdown.append(item);
            });
            els.result.append(breakdown);
        }

        if (Array.isArray(result.review) && result.review.some((item) => Object.prototype.hasOwnProperty.call(item, 'correct_key'))) {
            const review = document.createElement('div');
            review.className = 'fd-simulation-review';
            const reviewHeading = document.createElement('h3');
            reviewHeading.textContent = 'Gabarito comentado';
            review.append(reviewHeading);
            result.review.forEach((item, index) => {
                const article = document.createElement('article');
                const title = document.createElement('strong');
                title.textContent = `Questão ${index + 1}`;
                const answer = document.createElement('p');
                answer.textContent = `Você marcou: ${item.selected_key || '—'} · Correta: ${item.correct_key || '—'} · ${item.is_correct ? 'Acertou' : 'Errou'}`;
                article.append(title, answer);
                if (item.explanation) {
                    const comment = document.createElement('p');
                    comment.textContent = item.explanation;
                    article.append(comment);
                }
                review.append(article);
            });
            els.result.append(review);
        }
    };

    const loadResult = async () => {
        if (!state.attemptId) {
            return;
        }
        try {
            const result = await api(`/attempts/${state.attemptId}/result`);
            renderResult(result);
        } catch (error) {
            showError(error.message);
        }
    };

    const finish = async (askConfirmation = true) => {
        if (state.finishing || !state.attemptId) {
            return;
        }
        if (askConfirmation && !window.confirm(config.messages.finishConfirm)) {
            return;
        }
        state.finishing = true;
        clearError();
        els.finish.disabled = true;
        try {
            const result = await api(`/attempts/${state.attemptId}/finish`, {
                method: 'POST',
                body: '{}',
            });
            renderResult(result);
        } catch (error) {
            showError(error.message);
        } finally {
            state.finishing = false;
            els.finish.disabled = false;
        }
    };

    els.startButton?.addEventListener('click', async () => {
        clearError();
        els.startButton.disabled = true;
        try {
            const payload = await api(`/simulations/${config.simulationId}/attempts`, {
                method: 'POST',
                body: '{}',
            });
            mountAttempt(payload);
        } catch (error) {
            showError(error.message);
        } finally {
            els.startButton.disabled = false;
        }
    });

    els.prev?.addEventListener('click', () => {
        if (state.current > 0) {
            state.current -= 1;
            renderQuestion();
        }
    });
    els.next?.addEventListener('click', () => {
        if (state.current < state.questions.length - 1) {
            state.current += 1;
            renderQuestion();
        }
    });
    els.finish?.addEventListener('click', () => finish(true));
})();
