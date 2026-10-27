(function () {
    var root = document.documentElement;
    var header = document.querySelector('#site-header');
    var toggle = document.querySelector('#menu-toggle');

    root.classList.add('js');

    /* Header: solid background after scrolling, mobile menu toggle. */
    function syncHeader() {
        if (header) {
            header.classList.toggle('is-scrolled', window.scrollY > 40);
        }
    }

    function closeMenu() {
        header.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka menu');
    }

    window.addEventListener('scroll', syncHeader, { passive: true });
    syncHeader();

    if (header && toggle) {
        toggle.addEventListener('click', function () {
            var open = header.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
        });

        header.querySelectorAll('.site-nav a').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });
    }

    /*
     * Marquee: the visible loop needs a second copy of the text. The copy is stripped of
     * data-edit attributes (ids must stay unique) and rebuilt whenever the original changes,
     * so edits made in the editor show up in both halves.
     */
    var track = document.querySelector('#marquee-track');
    var group = document.querySelector('#marquee-group');

    if (track && group) {
        var copy = null;

        var rebuildCopy = function () {
            var fresh = group.cloneNode(true);
            fresh.removeAttribute('id');
            fresh.setAttribute('aria-hidden', 'true');
            fresh.querySelectorAll('[data-edit-id]').forEach(function (el) {
                el.removeAttribute('data-edit-id');
                el.removeAttribute('data-edit-type');
                el.removeAttribute('contenteditable');
                el.removeAttribute('spellcheck');
            });

            if (copy) {
                track.replaceChild(fresh, copy);
            } else {
                track.appendChild(fresh);
            }

            copy = fresh;
        };

        rebuildCopy();

        if ('MutationObserver' in window) {
            new MutationObserver(rebuildCopy).observe(group, {
                childList: true,
                characterData: true,
                subtree: true,
            });
        }
    }

    /*
     * Circular badge: the visible letters live on an SVG path, which the editor cannot type into.
     * The editable source is .spin-label; this copies it onto the ring whenever it changes.
     */
    document.querySelectorAll('.spin-badge').forEach(function (badge) {
        var label = badge.querySelector('.spin-label');
        var path = badge.querySelector('textPath');

        if (!label || !path) {
            return;
        }

        var paint = function () {
            var text = label.textContent.replace(/\s+/g, ' ').trim();

            path.textContent = text;
            path.setAttribute('textLength', '286');

            if (text) {
                badge.setAttribute('aria-label', text);
            }
        };

        paint();

        if ('MutationObserver' in window) {
            new MutationObserver(paint).observe(label, {
                childList: true,
                characterData: true,
                subtree: true,
            });
        }
    });

    /* Menu rows: highlight the row under the pointer, keep the last one active. */
    var rows = document.querySelectorAll('.menu-row');

    rows.forEach(function (row) {
        row.addEventListener('mouseenter', function () {
            rows.forEach(function (other) {
                other.classList.toggle('is-active', other === row);
            });
        });
    });

    /* Reveal on scroll. */
    var reveals = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(
            function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            },
            { threshold: 0.12, rootMargin: '0px 0px -40px 0px' },
        );

        reveals.forEach(function (el, index) {
            el.style.transitionDelay = (index % 4) * 80 + 'ms';
            observer.observe(el);
        });
    } else {
        reveals.forEach(function (el) {
            el.classList.add('is-visible');
        });
    }

    /* News list: filter the sample cards. WordPress handles the same form on the server. */
    var searchForm = document.querySelector('#blog-search[data-blog-filter="client"]');

    if (searchForm) {
        var searchList = document.querySelector('.blog-grid');
        var searchEmpty = document.querySelector('#blog-search-empty');
        var searchInput = searchForm.querySelector('input[name="q"]');

        if (!searchList || !searchInput) {
            searchForm = null;
        }
    }

    if (searchForm) {

        var applyNewsFilter = function () {
            var term = (searchInput.value || '').trim().toLowerCase();
            var visible = 0;

            searchList.querySelectorAll('article').forEach(function (article) {
                var show = term === '' || article.textContent.toLowerCase().indexOf(term) !== -1;
                article.hidden = !show;

                if (show) {
                    visible += 1;
                }
            });

            if (searchEmpty) {
                searchEmpty.hidden = term === '' || visible !== 0;
            }
        };

        var initialQuery = new URLSearchParams(window.location.search).get('q');

        if (initialQuery && searchInput.value === '') {
            searchInput.value = initialQuery;
        }

        searchInput.addEventListener('input', applyNewsFilter);
        searchForm.addEventListener('submit', function (event) {
            event.preventDefault();
            applyNewsFilter();
        });
        applyNewsFilter();
    }

    /* Newsletter: demo only, no request is sent. */
    var form = document.querySelector('#newsletter');
    var note = document.querySelector('#news-note');

    if (form && note) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            form.reset();
            note.hidden = false;
        });
    }
})();
