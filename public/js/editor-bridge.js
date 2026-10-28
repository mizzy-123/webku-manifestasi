/**
 * Editor bridge: runs inside the template <iframe> and talks to the React editor via postMessage.
 *
 * iframe -> parent: IFRAME_READY, TEXT_UPDATED, IMAGE_CLICKED, LINK_CLICKED, NAVIGATE_PAGE
 * parent -> iframe: APPLY_CUSTOM_DATA, SET_EDIT_MODE
 */
(function () {
    'use strict';

    var ORIGIN = window.location.origin;
    var inFrame = window.parent !== window;
    var state = { editMode: inFrame };
    var badge = null;

    var PAGE_LINK =
        /^(?![a-z][a-z0-9+.-]*:|\/\/|#|\/)[^?#]*\.html?(?:[?#].*)?$/i;
    var BADGE_LABELS = {
        text: 'Edit Teks',
        image: 'Ganti Gambar',
        link: 'Edit Tombol',
    };

    function post(message) {
        if (inFrame) {
            window.parent.postMessage(message, ORIGIN);
        }
    }

    function selectorFor(id) {
        var escaped =
            window.CSS && CSS.escape
                ? CSS.escape(id)
                : String(id).replace(/"/g, '\\"');

        return '[data-edit-id="' + escaped + '"]';
    }

    function scopeOf(el) {
        return el.dataset.editScope || 'page';
    }

    function injectStyles() {
        var style = document.createElement('style');
        style.setAttribute('data-editor-bridge', '');
        style.textContent =
            'html.__ed-edit [data-edit-id]{cursor:pointer}' +
            'html.__ed-edit [data-edit-id]:hover{outline:2px dashed #9275f7;outline-offset:3px}' +
            'html.__ed-edit [data-edit-type="text"]{cursor:text}' +
            'html.__ed-edit [data-edit-type="text"]:focus{outline:2px solid #9275f7;outline-offset:3px}' +
            '#__ed-badge{position:fixed;z-index:2147483647;pointer-events:none;display:none;' +
            'background:#9275f7;color:#fff;font:600 11px/1 system-ui,sans-serif;padding:5px 8px;border-radius:6px;white-space:nowrap}';
        document.head.appendChild(style);

        badge = document.createElement('div');
        badge.id = '__ed-badge';
        document.body.appendChild(badge);
    }

    function hideBadge() {
        if (badge) {
            badge.style.display = 'none';
        }
    }

    function showBadge(el) {
        var label = BADGE_LABELS[el.dataset.editType];

        if (!label || !badge) {
            return hideBadge();
        }

        var rect = el.getBoundingClientRect();
        badge.textContent = label;
        badge.style.display = 'block';
        badge.style.left = Math.max(4, rect.left) + 'px';
        badge.style.top = Math.max(4, rect.top - 24) + 'px';
    }

    function setEditMode(enabled) {
        state.editMode = Boolean(enabled) && inFrame;
        document.documentElement.classList.toggle('__ed-edit', state.editMode);

        document
            .querySelectorAll('[data-edit-type="text"]')
            .forEach(function (el) {
                if (state.editMode) {
                    el.setAttribute('contenteditable', 'true');
                    el.setAttribute('spellcheck', 'false');
                } else {
                    el.removeAttribute('contenteditable');
                    el.removeAttribute('spellcheck');
                }
            });

        if (!state.editMode) {
            hideBadge();
        }
    }

    function isFocusedWithin(el) {
        var active = document.activeElement;

        return active === el || (active && el.contains(active));
    }

    function applyGroup(group) {
        if (!group) {
            return;
        }

        Object.keys(group.texts || {}).forEach(function (id) {
            document.querySelectorAll(selectorFor(id)).forEach(function (el) {
                // Never overwrite an element the visitor is typing in: the caret would jump to the start.
                if (!isFocusedWithin(el) && el.innerHTML !== group.texts[id]) {
                    el.innerHTML = group.texts[id];
                }
            });
        });

        Object.keys(group.images || {}).forEach(function (id) {
            document.querySelectorAll(selectorFor(id)).forEach(function (el) {
                el.removeAttribute('srcset');
                el.setAttribute('src', group.images[id]);
            });
        });

        Object.keys(group.links || {}).forEach(function (id) {
            var link = group.links[id] || {};

            document.querySelectorAll(selectorFor(id)).forEach(function (el) {
                if (typeof link.href === 'string') {
                    el.setAttribute('href', link.href);
                }

                if (typeof link.text !== 'string') {
                    return;
                }

                var ring = el.querySelector('textPath, [data-link-label]');

                if (ring) {
                    if (ring.textContent !== link.text) {
                        ring.textContent = link.text;
                    }

                    return;
                }

                if (el.children.length === 0 && el.textContent !== link.text) {
                    el.textContent = link.text;
                }
            });
        });
    }

    function applyCustomData(payload, currentPage) {
        if (!payload) {
            return;
        }

        Object.keys(payload.colors || {}).forEach(function (variable) {
            document.documentElement.style.setProperty(
                variable,
                payload.colors[variable],
            );
        });

        applyGroup(payload.global);
        applyGroup((payload.pages || {})[currentPage]);
    }

    function pageFileFrom(href) {
        return href.replace(/^\.\//, '').replace(/[?#].*$/, '');
    }

    function navigateToPage(file) {
        if (inFrame) {
            post({ type: 'NAVIGATE_PAGE', file: file });

            return;
        }

        var base = document.querySelector('base');
        var match =
            base && base.getAttribute('href').match(/\/templates\/([^/]+)\//);

        if (match) {
            window.location.href = '/render-template/' + match[1] + '/' + file;
        }
    }

    function onClick(event) {
        var target = event.target;

        if (!target || !target.closest) {
            return;
        }

        if (state.editMode) {
            var linkEl = target.closest('[data-edit-type="link"]');
            var imageEl = target.closest('[data-edit-type="image"]');

            if (linkEl) {
                event.preventDefault();
                event.stopPropagation();
                var ring = linkEl.querySelector('textPath, [data-link-label]');
                post({
                    type: 'LINK_CLICKED',
                    id: linkEl.dataset.editId,
                    scope: scopeOf(linkEl),
                    text: ring
                        ? ring.textContent.replace(/\s+/g, ' ').trim()
                        : linkEl.textContent.trim(),
                    href: linkEl.getAttribute('href') || '',
                });

                return;
            }

            if (imageEl) {
                event.preventDefault();
                event.stopPropagation();
                post({
                    type: 'IMAGE_CLICKED',
                    id: imageEl.dataset.editId,
                    scope: scopeOf(imageEl),
                    src: imageEl.getAttribute('src') || '',
                });

                return;
            }

            if (
                target.closest('[data-edit-type="text"]') &&
                target.closest('a')
            ) {
                event.preventDefault();
                event.stopPropagation();

                return;
            }

            var hashLink = target.closest('a[href^="#"]');

            if (hashLink) {
                event.preventDefault();
                event.stopPropagation();

                return;
            }
        }

        var anchor = target.closest('a[href]');

        if (!anchor) {
            return;
        }

        var href = anchor.getAttribute('href');

        if (PAGE_LINK.test(href)) {
            event.preventDefault();
            navigateToPage(pageFileFrom(href));

            return;
        }

        if (href.charAt(0) === '#') {
            return;
        }

        event.preventDefault();

        if (!state.editMode && /^https?:/i.test(anchor.href)) {
            window.open(anchor.href, '_blank', 'noopener');
        }
    }

    function onInput(event) {
        var el =
            event.target.closest &&
            event.target.closest('[data-edit-type="text"]');

        if (el && state.editMode) {
            post({
                type: 'TEXT_UPDATED',
                id: el.dataset.editId,
                scope: scopeOf(el),
                value: el.innerHTML,
            });
        }
    }

    function onPaste(event) {
        var el =
            event.target.closest &&
            event.target.closest('[data-edit-type="text"]');

        if (!el || !state.editMode) {
            return;
        }

        event.preventDefault();
        document.execCommand(
            'insertText',
            false,
            (event.clipboardData || window.clipboardData).getData('text/plain'),
        );
    }

    function onMouseOver(event) {
        if (!state.editMode) {
            return;
        }

        var el = event.target.closest && event.target.closest('[data-edit-id]');

        if (el) {
            showBadge(el);
        } else {
            hideBadge();
        }
    }

    function onMessage(event) {
        if (event.origin !== ORIGIN || event.source !== window.parent) {
            return;
        }

        var data = event.data || {};

        if (data.type === 'SET_EDIT_MODE') {
            setEditMode(data.value);
        } else if (data.type === 'APPLY_CUSTOM_DATA') {
            applyCustomData(data.payload, data.currentPage);
        }
    }

    function init() {
        injectStyles();

        document.addEventListener('click', onClick, true);
        document.addEventListener('input', onInput, true);
        document.addEventListener('focusout', onInput, true);
        document.addEventListener('paste', onPaste, true);
        document.addEventListener('mouseover', onMouseOver, true);
        window.addEventListener('scroll', hideBadge, true);
        window.addEventListener('message', onMessage);

        setEditMode(state.editMode);
        post({ type: 'IFRAME_READY' });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
