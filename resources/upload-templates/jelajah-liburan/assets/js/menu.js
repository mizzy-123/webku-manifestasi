const openMenu = document.querySelector("#open-menu");
const drawer = document.querySelector("#site-drawer");

function closeMenu() {
    drawer.hidden = true;
    openMenu.setAttribute("aria-expanded", "false");
}

function openMenuPanel() {
    drawer.hidden = false;
    openMenu.setAttribute("aria-expanded", "true");
}

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
    if (event.key === "Escape" && !drawer.hidden) {
        closeMenu();
    }
});
