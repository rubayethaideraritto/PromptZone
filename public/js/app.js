/*
 * Progressive enhancement only: every control on the site works without this
 * file. Three behaviours live here:
 *
 *   1. confirmation for destructive form submissions
 *   2. the copy-to-clipboard button, which reports the copy to the server
 *   3. the AI assistance panel on the create/edit prompt forms
 *
 * Nothing in the AI panel writes to a field on its own. Results are displayed
 * and the user chooses to apply them, so an unexpected reply can never silently
 * overwrite typed input. Model output is inserted with textContent, never
 * innerHTML, because it is untrusted text.
 */
(function () {
    'use strict';

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';

    // --- small DOM helpers ------------------------------------------------

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== undefined && text !== null) {
            node.textContent = text;
        }

        return node;
    }

    function setStatus(element, message, state) {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.dataset.state = state;
    }

    /* Build a titled bullet list. Returns null when there is nothing to show. */
    function listBlock(title, items) {
        if (!items || !items.length) {
            return null;
        }

        var fragment = document.createDocumentFragment();
        fragment.appendChild(el('h4', 'ai-result__label', title));

        var list = el('ul', 'ai-result__list');
        items.forEach(function (item) {
            list.appendChild(el('li', null, item));
        });
        fragment.appendChild(list);

        return fragment;
    }

    // --- clipboard --------------------------------------------------------

    /*
     * Write to the clipboard, preferring the async Clipboard API and falling
     * back to execCommand for browsers or insecure contexts where it is
     * unavailable. Resolves to true only when the text really was copied.
     */
    async function writeToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            try {
                await navigator.clipboard.writeText(text);
                return true;
            } catch (error) {
                // Permission denied or unavailable: try the legacy path.
            }
        }

        var helper = document.createElement('textarea');
        helper.value = text;
        helper.setAttribute('readonly', '');
        helper.style.position = 'fixed';
        helper.style.top = '-1000px';
        helper.style.opacity = '0';
        document.body.appendChild(helper);

        var copied = false;
        try {
            helper.select();
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }

        document.body.removeChild(helper);

        return copied;
    }

    // --- JSON helper ------------------------------------------------------

    /*
     * POST JSON and return the payload's `data`, or throw an Error carrying a
     * message fit to display. Every failure mode is turned into a readable
     * sentence: the caller never has to interpret a status code.
     */
    async function postJson(url, body) {
        var response;

        try {
            response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
            });
        } catch (error) {
            throw new Error('The request could not be sent. Check your connection and try again.');
        }

        var payload = null;
        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }

        if (response.ok && payload && payload.success === true) {
            return payload.data;
        }

        if (response.status === 422 && payload && payload.errors) {
            var first = Object.keys(payload.errors)[0];
            throw new Error(payload.errors[first][0]);
        }

        if (payload && payload.error) {
            throw new Error(payload.error);
        }

        if (response.status === 429) {
            throw new Error('Too many AI requests in a short time. Please wait a minute and try again.');
        }

        if (response.status === 419) {
            throw new Error('Your session expired. Reload the page and try again.');
        }

        if (response.status === 401 || response.status === 302) {
            throw new Error('Your session has ended. Log in again and retry.');
        }

        throw new Error('The AI request failed (' + response.status + ').');
    }

    // --- submission confirmation -----------------------------------------

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        var message = form.dataset.confirm;

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // --- clicks -----------------------------------------------------------

    document.addEventListener('click', async function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }

        var copyButton = event.target.closest('[data-copy-url]');
        if (copyButton) {
            await handleCopy(copyButton, event);
            return;
        }

        var aiButton = event.target.closest('[data-ai-action]');
        if (aiButton) {
            await handleAiAction(aiButton);
            return;
        }

        var useButton = event.target.closest('[data-ai-use]');
        if (useButton) {
            handleUseDraft(useButton);
            return;
        }

        var acceptButton = event.target.closest('[data-ai-accept]');
        if (acceptButton) {
            handleAcceptSuggestions(acceptButton);
            return;
        }

        var versionButton = event.target.closest('[data-ai-save-version]');
        if (versionButton) {
            handleSaveVersion(versionButton);
        }
    });

    // --- copy to clipboard ------------------------------------------------

    async function handleCopy(button, event) {
        event.preventDefault();

        var source = button.dataset.copyTarget
            ? document.querySelector(button.dataset.copyTarget)
            : null;

        if (!source) {
            return;
        }

        var status = button.dataset.copyStatus
            ? document.querySelector(button.dataset.copyStatus)
            : null;
        var counter = button.dataset.copyCounter
            ? document.querySelector(button.dataset.copyCounter)
            : null;

        button.disabled = true;
        setStatus(status, 'Copying\u2026', 'pending');

        var copied = await writeToClipboard(source.textContent);

        if (!copied) {
            button.disabled = false;
            setStatus(
                status,
                'Your browser blocked the clipboard. Select the text and copy it manually.',
                'error'
            );
            return;
        }

        /*
         * The text is on the clipboard, so report the copy now. Counting before
         * a successful copy would inflate the total, which is why the order is
         * clipboard first and server second.
         */
        try {
            var response = await fetch(button.dataset.copyUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error('Request failed with status ' + response.status);
            }

            var data = await response.json();

            if (counter && typeof data.copy_count === 'number') {
                counter.textContent = data.copy_count.toLocaleString();
            }

            setStatus(status, 'Copied to your clipboard.', 'success');
        } catch (error) {
            // Be accurate: the copy succeeded, only the tally did not.
            setStatus(status, 'Copied, but the copy counter could not be updated.', 'error');
        } finally {
            button.disabled = false;
        }
    }

    // --- AI panel ---------------------------------------------------------

    function panelFor(button) {
        return button.closest('[data-ai-panel]');
    }

    async function handleAiAction(button) {
        var panel = panelFor(button);

        if (!panel) {
            return;
        }

        var action = button.dataset.aiAction;
        var status = panel.querySelector('[data-ai-status]');
        var result = panel.querySelector('[data-ai-result]');
        var textarea = document.getElementById('prompt_text');
        var text = textarea ? textarea.value.trim() : '';

        if (!text) {
            setStatus(status, 'Write something in the Prompt text box first.', 'error');
            return;
        }

        var url = {
            analyze: panel.dataset.analyzeUrl,
            improve: panel.dataset.improveUrl,
            suggest: panel.dataset.suggestUrl
        }[action];

        if (!url) {
            return;
        }

        var label = button.textContent;
        button.disabled = true;
        result.hidden = true;
        result.replaceChildren();

        setStatus(status, {
            analyze: 'Analyzing\u2026',
            improve: 'Writing an improved draft\u2026',
            suggest: 'Choosing a category and tags\u2026'
        }[action], 'pending');

        try {
            var data = await postJson(url, { prompt_text: text });

            renderResult(action, data, panel, result);
            result.hidden = false;
            setStatus(status, 'Done. Review the result below \u2014 nothing has been applied yet.', 'success');
        } catch (error) {
            // The user's text is untouched: this branch never writes to the form.
            setStatus(status, error.message, 'error');
        } finally {
            button.disabled = false;
            button.textContent = label;
        }
    }

    function renderResult(action, data, panel, result) {
        if (action === 'analyze') {
            renderAnalysis(data, result);
            return;
        }

        if (action === 'improve') {
            renderImprovement(data, panel, result);
            return;
        }

        renderSuggestions(data, result);
    }

    function renderAnalysis(data, result) {
        var header = el('div', 'ai-result__header');

        if (typeof data.clarity_score === 'number') {
            header.appendChild(el('span', 'ai-result__score', data.clarity_score + ' / 100 clarity'));
        }

        if (data.score_explanation) {
            header.appendChild(el('p', 'ai-result__explanation', data.score_explanation));
        }

        if (header.childNodes.length) {
            result.appendChild(header);
        }

        [
            ['Strengths', data.strengths],
            ['Weaknesses', data.weaknesses],
            ['Missing context', data.missing_context],
            ['Suggestions', data.suggestions]
        ].forEach(function (pair) {
            var block = listBlock(pair[0], pair[1]);

            if (block) {
                result.appendChild(block);
            }
        });
    }

    function renderImprovement(data, panel, result) {
        result.appendChild(el('h4', 'ai-result__label', 'Improved draft'));

        var pre = el('pre', 'prompt-text ai-result__draft', data.improved_prompt);
        result.appendChild(pre);

        var changes = listBlock('What changed', data.changes);

        if (changes) {
            result.appendChild(changes);
        }

        result.appendChild(el(
            'p',
            'ai-result__warning',
            'Check this draft before using it: AI output can be wrong or may drop detail you wanted. Your current text is unchanged until you apply it.'
        ));

        var actions = el('div', 'ai-result__actions');

        var use = el('button', 'btn btn--primary btn--sm', 'Use this draft');
        use.type = 'button';
        use.dataset.aiUse = '1';
        actions.appendChild(use);

        if (panel.dataset.versionUrl) {
            var save = el('button', 'btn btn--sm', 'Save as new version');
            save.type = 'button';
            save.dataset.aiSaveVersion = '1';
            actions.appendChild(save);
        }

        result.appendChild(actions);
    }

    function renderSuggestions(data, result) {
        result.appendChild(el('h4', 'ai-result__label', 'Suggested classification'));

        var list = el('ul', 'ai-result__list');
        list.appendChild(el('li', null, 'Category: ' + (data.category_name || data.category_slug)));

        if (data.tags && data.tags.length) {
            list.appendChild(el('li', null, 'Tags: ' + data.tags.join(', ')));
        }

        result.appendChild(list);
        result.appendChild(el(
            'p',
            'ai-result__warning',
            'These are suggestions only. Your current category and tags stay as they are until you press the button.'
        ));

        var actions = el('div', 'ai-result__actions');
        var accept = el('button', 'btn btn--primary btn--sm', 'Accept suggestions');
        accept.type = 'button';
        accept.dataset.aiAccept = '1';
        actions.appendChild(accept);
        result.appendChild(actions);

        // Carried on the button so the click handler needs no global state.
        accept.dataset.categoryId = String(data.category_id || '');
        accept.dataset.tags = (data.tags || []).join(', ');
    }

    function handleUseDraft(button) {
        var panel = panelFor(button);
        var status = panel ? panel.querySelector('[data-ai-status]') : null;
        var textarea = document.getElementById('prompt_text');
        var draft = panel ? panel.querySelector('.ai-result__draft') : null;

        if (!textarea || !draft) {
            return;
        }

        // Fills the form only. The user still has to save.
        textarea.value = draft.textContent;
        textarea.focus();
        setStatus(status, 'Draft copied into the form. Nothing is saved until you save the prompt.', 'success');
    }

    function handleAcceptSuggestions(button) {
        var panel = panelFor(button);
        var status = panel ? panel.querySelector('[data-ai-status]') : null;
        var categoryId = button.dataset.categoryId;
        var tags = button.dataset.tags || '';

        var select = document.getElementById('category_id');
        var tagInput = document.getElementById('tags');
        var applied = [];

        if (select && categoryId) {
            select.value = categoryId;

            // The option may not exist if the category was deactivated meanwhile.
            if (select.value === categoryId) {
                applied.push('category');
            }
        }

        if (tagInput && tags) {
            tagInput.value = tags;
            applied.push('tags');
        }

        setStatus(
            status,
            applied.length
                ? 'Applied to ' + applied.join(' and ') + ' in the form. Save the prompt to keep them.'
                : 'Nothing could be applied. Pick the values manually.',
            applied.length ? 'success' : 'error'
        );
    }

    function handleSaveVersion(button) {
        var panel = panelFor(button);
        var draft = panel ? panel.querySelector('.ai-result__draft') : null;
        var url = panel ? panel.dataset.versionUrl : null;

        if (!draft || !url) {
            return;
        }

        if (!window.confirm('Replace the current prompt text with this draft? The text being replaced is kept in the version history.')) {
            return;
        }

        /*
         * A real form submission rather than fetch, so the server's redirect and
         * flash message behave exactly as they do for every other write.
         */
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        [
            ['_token', csrfToken],
            ['prompt_text', draft.textContent],
            ['change_note', 'Replaced with an AI-improved draft.']
        ].forEach(function (pair) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = pair[0];
            input.value = pair[1];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    // --- duplicate check --------------------------------------------------

    /*
     * Advisory only. As the author types, the draft is compared against their own
     * prompts and any close matches are listed. It never prevents a submit, and a
     * failure here is silent on purpose: an unavailable convenience check must
     * not look like a broken form.
     */
    (function () {
        var panel = document.querySelector('[data-duplicate-check]');

        if (!panel) {
            return;
        }

        var timer = null;
        var controller = null;
        var lastSignature = '';

        function hide() {
            panel.hidden = true;
            panel.replaceChildren();
        }

        function render(list) {
            const wasHidden = panel.hidden;

            panel.replaceChildren();

            if (!list.length) {
                hide();

                return;
            }

            panel.appendChild(el(
                'strong',
                'duplicate-check__title',
                list.length === 1
                    ? 'One of your own prompts looks similar'
                    : list.length + ' of your own prompts look similar'
            ));

            var items = el('ul', 'duplicate-check__list');

            list.forEach(function (item) {
                var row = el('li', 'duplicate-check__item');

                var link = el('a', null, item.title);
                // Server-generated same-origin URL, and the label is set with
                // textContent so a title can never inject markup.
                link.href = item.url;
                row.appendChild(link);

                row.appendChild(el(
                    'span',
                    'duplicate-check__score',
                    item.reason === 'title'
                        ? item.title_similarity + '% title match'
                        : item.body_similarity + '% text match'
                ));

                items.appendChild(row);
            });

            panel.appendChild(items);
            panel.appendChild(el(
                'p',
                'duplicate-check__note',
                'This is a warning, not a block \u2014 you can still save this prompt.'
            ));

            panel.hidden = false;

            /*
             * Reveal it only on the transition from hidden to shown, and only as
             * far as needed. The form is taller than the viewport, so a warning
             * that appears silently above the fold is a warning nobody reads.
             */
            if (wasHidden) {
                var reduceMotion = window.matchMedia
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                panel.scrollIntoView({
                    block: 'nearest',
                    behavior: reduceMotion ? 'auto' : 'smooth'
                });
            }
        }

        function currentFields() {
            var title = document.getElementById('title');
            var textarea = document.getElementById('prompt_text');

            return {
                title: title ? title.value.trim() : '',
                body: textarea ? textarea.value.trim() : ''
            };
        }

        async function run() {
            var fields = currentFields();

            // Too little text to compare meaningfully.
            if (fields.title.length < 4 && fields.body.length < 20) {
                hide();

                return;
            }

            var signature = fields.title + '\u0000' + fields.body;

            if (signature === lastSignature) {
                return;
            }

            lastSignature = signature;

            if (controller) {
                controller.abort();
            }

            controller = new AbortController();

            try {
                var response = await fetch(panel.dataset.duplicateUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: controller.signal,
                    body: JSON.stringify({
                        title: fields.title,
                        prompt_text: fields.body,
                        ignore_id: panel.dataset.duplicateIgnore || null
                    })
                });

                if (!response.ok) {
                    throw new Error('status ' + response.status);
                }

                var payload = await response.json();
                render(payload.duplicates || []);
            } catch (error) {
                // Superseded by a newer keystroke: leave the last good result alone.
                if (error && error.name === 'AbortError') {
                    return;
                }

                hide();
            }
        }

        ['title', 'prompt_text'].forEach(function (id) {
            var field = document.getElementById(id);

            if (field) {
                field.addEventListener('input', function () {
                    window.clearTimeout(timer);
                    timer = window.setTimeout(run, 800);
                });
            }
        });
    })();
})();
