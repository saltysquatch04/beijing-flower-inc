document.addEventListener("DOMContentLoaded", () => {
    const editItems = document.querySelectorAll('.edit-item');
    const panels = document.querySelectorAll('.edit-panel');

    if (typeof activePanel !== 'undefined') {
        panels.forEach(panel => panel.classList.remove('active'));
        editItems.forEach(i => i.classList.remove('active'));

        const targetPanel = document.getElementById(activePanel);
        if (targetPanel) targetPanel.classList.add('active');

        editItems.forEach(item => {
            if (item.getAttribute('data-target') === '#' + activePanel) {
                item.classList.add('active');
            }
        });
    }

    editItems.forEach(item => {
        item.addEventListener('click', () => {

            // Highlight selected item
            editItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            // Show associated panel
            const target = item.getAttribute('data-target');

            panels.forEach(panel => {
                panel.classList.remove('active');
            });

            document.querySelector(target).classList.add('active');
        });
    });
});

document.addEventListener("DOMContentLoaded", () => {
    const toggle = document.getElementById("themeToggle");

    // Load saved theme
    const savedTheme = localStorage.getItem("theme") || "dark";
    document.body.classList.add(savedTheme + "-mode");

    // Set toggle position
    toggle.checked = savedTheme === "light";

    toggle.addEventListener("change", () => {
        if (toggle.checked) {
            document.body.classList.remove("dark-mode");
            document.body.classList.add("light-mode");
            localStorage.setItem("theme", "light");
        } else {
            document.body.classList.remove("light-mode");
            document.body.classList.add("dark-mode");
            localStorage.setItem("theme", "dark");
        }
    });
});