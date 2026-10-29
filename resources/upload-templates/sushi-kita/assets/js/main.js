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
        if (!header || !toggle) {
            return;
        }
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

    /* Hero 3-item carousel: rotates data-pos="left" | "center" | "right". */
    var heroTrack = document.querySelector('#hero-track');
    var heroPrev = document.querySelector('#hero-prev');
    var heroNext = document.querySelector('#hero-next');

    if (heroTrack && heroPrev && heroNext) {
        var order = ['left', 'center', 'right'];

        var rotateHero = function (dir) {
            var items = heroTrack.querySelectorAll('.hero-item');
            items.forEach(function (item) {
                var current = item.getAttribute('data-pos') || 'center';
                var idx = order.indexOf(current);
                if (idx === -1) {
                    idx = 1;
                }
                var nextIdx = (idx + dir + order.length) % order.length;
                var nextPos = order[nextIdx];

                if (
                    (current === 'left' && nextPos === 'right') ||
                    (current === 'right' && nextPos === 'left')
                ) {
                    item.classList.add('is-wrapping');
                    setTimeout(function () {
                        item.classList.remove('is-wrapping');
                    }, 50);
                }

                item.setAttribute('data-pos', nextPos);
            });
        };

        heroPrev.addEventListener('click', function () {
            rotateHero(-1);
        });

        heroNext.addEventListener('click', function () {
            rotateHero(1);
        });

        heroTrack.querySelectorAll('.hero-item').forEach(function (item) {
            item.addEventListener('click', function (e) {
                if (e.target.closest('[contenteditable="true"], [data-edit-type="image"]')) {
                    return;
                }
                var pos = item.getAttribute('data-pos');
                if (pos === 'left') {
                    rotateHero(1);
                } else if (pos === 'right') {
                    rotateHero(-1);
                }
            });
        });
    }

    /* Restaurants horizontal rail scroll. */
    var restoRail = document.querySelector('#resto-rail');
    var restoPrev = document.querySelector('#resto-prev');
    var restoNext = document.querySelector('#resto-next');

    if (restoRail && restoPrev && restoNext) {
        var stepSize = function () {
            var first = restoRail.querySelector('.resto-card');
            return first ? first.getBoundingClientRect().width : 280;
        };

        restoPrev.addEventListener('click', function () {
            restoRail.scrollBy({ left: -stepSize(), behavior: 'smooth' });
        });

        restoNext.addEventListener('click', function () {
            restoRail.scrollBy({ left: stepSize(), behavior: 'smooth' });
        });
    }

    /* Menu tabs filter + pagination (8 items per page). */
    var menuTabs = document.querySelector('#menu-tabs');
    var menuGrid = document.querySelector('#menu-grid');
    var menuPager = document.querySelector('#menu-pager');
    var menuPrev = document.querySelector('#menu-prev');
    var menuNext = document.querySelector('#menu-next');
    var menuPageLabel = document.querySelector('#menu-page');

    if (menuTabs && menuGrid) {
        var dishes = Array.prototype.slice.call(menuGrid.querySelectorAll('.dish'));
        var perPage = 8;
        var currentFilter = 'semua';
        var currentPage = 1;

        var matchingDishes = function () {
            return dishes.filter(function (dish) {
                return (
                    currentFilter === 'semua' ||
                    dish.getAttribute('data-category') === currentFilter
                );
            });
        };

        var renderMenu = function () {
            var matched = matchingDishes();
            var totalPages = Math.max(1, Math.ceil(matched.length / perPage));
            if (currentPage > totalPages) {
                currentPage = totalPages;
            }
            var start = (currentPage - 1) * perPage;
            var end = start + perPage;

            dishes.forEach(function (dish) {
                dish.hidden = true;
            });

            matched.slice(start, end).forEach(function (dish) {
                dish.hidden = false;
            });

            if (menuPager && menuPageLabel && menuPrev && menuNext) {
                menuPager.hidden = totalPages <= 1;
                menuPageLabel.textContent = currentPage + ' / ' + totalPages;
                menuPrev.disabled = currentPage <= 1;
                menuNext.disabled = currentPage >= totalPages;
            }
        };

        menuTabs.querySelectorAll('.tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                currentFilter = tab.getAttribute('data-filter') || 'semua';
                currentPage = 1;
                menuTabs.querySelectorAll('.tab').forEach(function (other) {
                    var active = other === tab;
                    other.classList.toggle('is-active', active);
                    other.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                renderMenu();
            });
        });

        if (menuPrev && menuNext) {
            menuPrev.addEventListener('click', function () {
                if (currentPage > 1) {
                    currentPage -= 1;
                    renderMenu();
                }
            });

            menuNext.addEventListener('click', function () {
                currentPage += 1;
                renderMenu();
            });
        }

        renderMenu();
    }

    /* FAQ accordion: open first item by default, toggle on click without breaking editor inline editing. */
    var faqList = document.querySelector('#faq-list');

    if (faqList) {
        var faqItems = faqList.querySelectorAll('.faq-item');

        var setFaqOpen = function (item, open) {
            item.classList.toggle('is-open', open);
            var btn = item.querySelector('.faq-toggle');
            if (btn) {
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
        };

        if (faqItems.length > 0) {
            setFaqOpen(faqItems[0], true);
        }

        faqItems.forEach(function (item) {
            var q = item.querySelector('.faq-q');
            var btn = item.querySelector('.faq-toggle');

            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var willOpen = !item.classList.contains('is-open');
                    setFaqOpen(item, willOpen);
                });
            }

            if (q) {
                q.addEventListener('click', function (e) {
                    if (e.target.closest('[contenteditable="true"]')) {
                        return;
                    }
                    var willOpen = !item.classList.contains('is-open');
                    setFaqOpen(item, willOpen);
                });
            }
        });
    }

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
            el.style.transitionDelay = (index % 4) * 70 + 'ms';
            observer.observe(el);
        });
    } else {
        reveals.forEach(function (el) {
            el.classList.add('is-visible');
        });
    }

    /* News list: filter sample cards on client. WordPress handles the same form on server. */
    var searchForm = document.querySelector('#blog-search[data-blog-filter="client"]');

    if (searchForm) {
        var searchList = document.querySelector('.news-grid');
        var searchEmpty = document.querySelector('#blog-search-empty');
        var searchInput = searchForm.querySelector('input[name="q"]');

        if (searchList && searchInput) {
            var applyNewsFilter = function () {
                var term = (searchInput.value || '').trim().toLowerCase();
                var visible = 0;

                searchList.querySelectorAll('article').forEach(function (article) {
                    var show =
                        term === '' ||
                        article.textContent.toLowerCase().indexOf(term) !== -1;
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
    }
})();
