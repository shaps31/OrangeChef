/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import './styles/theme-citrus.css';


console.log('This log comes from assets/app.js - welcome to AssetMapper! 🎉');

// 📸 Prévisualisation d’image à l’upload
document.addEventListener("DOMContentLoaded", () => {
    const input = document.querySelector('input[type="file"]');
    const preview = document.createElement("img");
    preview.style.maxWidth = "100px";
    preview.style.marginTop = "10px";

    if (input) {
        input.parentElement.appendChild(preview);
        input.addEventListener("change", () => {
            const file = input.files[0];
            if (file && file.type.startsWith("image/")) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                preview.src = "";
            }
        });
    }
});
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form'); if (!form) return;

    const $ = (sel) => form.querySelector(sel);
    const pick = (...sels) => sels.map($).find(el => !!el);

    const elName = pick('[name$="[displayName]"]', '[name$="[prenom]"]', '[name$="[name]"]');
    const elEmail = $('[name$="[email]"]');
    const elVerified = pick('[name$="[isVerified]"]', '[name$="[verified]"]');
    const rolesSelect = pick('select[name$="[roles][]"]', 'select[name$="[roles]"]');
    const rolesChecks = form.querySelectorAll('input[type="checkbox"][name$="[roles][]"]');

    const pvName = document.getElementById('pvName');
    const pvEmail = document.getElementById('pvEmail');
    const pvVerified = document.getElementById('pvVerified');
    const pvRoles = document.getElementById('pvRoles');

    const roleLabel = (v) => ({'ROLE_ADMIN':'Admin','ROLE_USER':'Membre'}[v] || v);

    function readRoles() {
        let values = [];
        if (rolesSelect) {
            values = Array.from(rolesSelect.selectedOptions || []).map(o => o.value);
        } else if (rolesChecks && rolesChecks.length) {
            values = Array.from(rolesChecks).filter(c => c.checked).map(c => c.value);
        }
        if (values.length === 0) values = ['ROLE_USER'];
        pvRoles.innerHTML = values.map(v =>
            `<span class="badge ${v==='ROLE_ADMIN'?'bg-danger':'bg-secondary'}">${roleLabel(v)}</span>`
        ).join(' ');
    }

    function update() {
        if (elName && pvName) pvName.textContent = elName.value || 'Nom affiché';
        if (elEmail && pvEmail) pvEmail.textContent = elEmail.value || 'email@example.com';
        if (elVerified && pvVerified) {
            const checked = elVerified.type === 'checkbox' ? elVerified.checked : !!elVerified.value;
            pvVerified.className = 'badge ' + (checked ? 'bg-success' : 'bg-warning text-dark');
            pvVerified.textContent = checked ? 'E-mail vérifié' : 'E-mail non vérifié';
            const hasEmail = elEmail && elEmail.value.trim().length > 0;
            if (pvVerified) pvVerified.style.visibility = hasEmail ? 'visible' : 'hidden';
        }
        readRoles();
    }

    form.addEventListener('input', update, true);
    form.addEventListener('change', update, true);
    update();
});
/* --- JS: Toggle menu global + fermeture hors-clic/Échap --- */


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


