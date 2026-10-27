(function () {
    var header = document.querySelector('.site-header');
    var toggle = document.querySelector('#open-menu');
    var fab = document.querySelector('#reserve-fab');
    var fabCovered = false;

    function syncFab() {
        if (!fab) {
            return;
        }

        var menuOpen = header && header.classList.contains('is-open');
        fab.classList.toggle('is-hidden', fabCovered || menuOpen);
    }

    if (header && toggle) {
        toggle.addEventListener('click', function () {
            var open = header.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            syncFab();
        });

        header.querySelectorAll('a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', function () {
                header.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.setAttribute('aria-label', 'Buka menu');
                syncFab();
            });
        });
    }

    if (fab && 'IntersectionObserver' in window) {
        var covered = document.querySelectorAll('#pesan-meja, #kontak');

        var updateFab = function () {
            fabCovered = Array.prototype.some.call(covered, function (section) {
                var box = section.getBoundingClientRect();
                return box.top < window.innerHeight * 0.72 && box.bottom > 80;
            });
            syncFab();
        };

        var observer = new IntersectionObserver(updateFab, {
            threshold: [0, 0.2, 0.4],
        });

        covered.forEach(function (section) {
            observer.observe(section);
        });
        window.addEventListener('scroll', updateFab, { passive: true });
        updateFab();
    }

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
