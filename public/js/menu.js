// assets/menu.js
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('ocMenuToggle');
    const panel  = document.getElementById('ocMenuPanel');
    if (!toggle || !panel) return;

    const open  = () => { panel.hidden = false;  toggle.setAttribute('aria-expanded', 'true');  };
    const close = () => { panel.hidden = true;   toggle.setAttribute('aria-expanded', 'false'); };

    toggle.addEventListener('click', (e) => {
        e.preventDefault();
        panel.hidden ? open() : close();
    });

    document.addEventListener('click', (e) => {
        if (panel.hidden) return;
        if (!panel.contains(e.target) && !toggle.contains(e.target)) close();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
});
