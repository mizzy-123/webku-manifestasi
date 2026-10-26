const buttons = document.querySelectorAll("[data-filter]");
const cards = document.querySelectorAll("[data-kind]");
const searchable = document.querySelectorAll("[data-search]");
const openSearch = document.querySelector("#open-search");
const closeSearch = document.querySelector("#close-search");
const panel = document.querySelector("#search-panel");
const input = document.querySelector("#search-input");
const list = document.querySelector(".search__list");
const hint = document.querySelector(".search__hint");
const empty = document.querySelector(".search__empty");
const form = document.querySelector(".search__form");
const openMenu = document.querySelector("#open-menu");
const drawer = document.querySelector("#site-drawer");

buttons.forEach((button) => {
    button.addEventListener("click", () => {
        applyFilter(button.getAttribute("data-filter"));
    });
});

function applyFilter(value) {
    buttons.forEach((item) => {
        item.classList.toggle("is-active", item.getAttribute("data-filter") === value);
    });

    cards.forEach((card) => {
        const match = value === "semua" || card.getAttribute("data-kind") === value;
        card.hidden = !match;
    });
}

function closeMenu() {
    drawer.hidden = true;
    openMenu.setAttribute("aria-expanded", "false");
}

function openMenuPanel() {
    closePanel();
    drawer.hidden = false;
    openMenu.setAttribute("aria-expanded", "true");
}

function openPanel() {
    closeMenu();
    panel.hidden = false;
    openSearch.setAttribute("aria-expanded", "true");
    input.focus();
    renderResults(input.value);
}

function closePanel() {
    panel.hidden = true;
    openSearch.setAttribute("aria-expanded", "false");
}

function labelOf(element) {
    const heading = element.querySelector("h2, h3");

    return heading ? heading.textContent.trim() : "";
}

function matches(element, query) {
    return element.textContent.toLowerCase().includes(query);
}

function renderResults(rawQuery) {
    const query = rawQuery.trim().toLowerCase();
    list.replaceChildren();

    if (query === "") {
        hint.hidden = false;
        empty.hidden = true;

        return;
    }

    hint.hidden = true;
    const found = [...searchable].filter((element) => matches(element, query));
    empty.hidden = found.length > 0;

    found.forEach((element) => {
        const item = document.createElement("li");
        const choice = document.createElement("button");
        choice.type = "button";
        choice.textContent = labelOf(element);
        choice.addEventListener("click", () => {
            goTo(element);
        });
        item.append(choice);
        list.append(item);
    });
}

function goTo(element) {
    applyFilter("semua");
    closePanel();
    element.scrollIntoView({ behavior: "smooth", block: "center" });
}

openSearch.addEventListener("click", () => {
    if (panel.hidden) {
        openPanel();
    } else {
        closePanel();
    }
});

closeSearch.addEventListener("click", closePanel);

input.addEventListener("input", () => {
    renderResults(input.value);
});

form.addEventListener("submit", (event) => {
    event.preventDefault();
    const query = input.value.trim().toLowerCase();
    const first = [...searchable].find((element) => query !== "" && matches(element, query));

    if (first) {
        goTo(first);
    }
});

openMenu.addEventListener("click", () => {
    if (drawer.hidden) {
        openMenuPanel();
    } else {
        closeMenu();
    }
});

drawer.addEventListener("click", (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    if (target.closest("[data-close-menu], a")) {
        closeMenu();
    }
});

document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") {
        return;
    }

    if (!panel.hidden) {
        closePanel();
    }

    if (!drawer.hidden) {
        closeMenu();
    }
});

document.addEventListener("click", (event) => {
    if (panel.hidden) {
        return;
    }

    const target = event.target;

    if (target instanceof Node && !panel.contains(target) && !openSearch.contains(target)) {
        closePanel();
    }
});
