/*
 * Fichier JS principal (importé via {{ importmap('app') }})
 * - Charge le thème CSS
 * - Prévisualisation d'image lors d'un upload <input type="file">
 * - Aperçu "live" d'un profil (nom, email, rôles, vérification)
 * - Ouverture/fermeture du méga-menu du header
 */


/* --------------------------------------------------------------------------
   1) Prévisualisation d’image sur les <input type="file">
   - Ajoute une <img> juste après chaque input fichier
   - Affiche l’aperçu dès qu’un fichier image est choisi
--------------------------------------------------------------------------- */
function initImagePreviews() {
    document.addEventListener("DOMContentLoaded", () => {
        const input = document.querySelector('input[type="file"][name$="[avatar]"]');
        if (!input) return;

        let preview = input.parentElement.querySelector('.upload-preview');
        if (!preview) {
            preview = document.createElement("img");
            preview.className = "upload-preview";
            input.parentElement.appendChild(preview);
        }

        input.addEventListener("change", () => {
            const file = input.files?.[0];
            if (file && file.type.startsWith("image/")) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                preview.removeAttribute('src');
            }
        });
    });
}

    /* --------------------------------------------------------------------------
       2) Aperçu "live" d’un formulaire de profil (facultatif)
       - Met à jour des badges/labels en même temps que l’utilisateur tape
       - S’active uniquement si on trouve des éléments #pvName/#pvEmail/...
    --------------------------------------------------------------------------- */
function initProfilePreview() {
    const form = document.querySelector('form');
    // On ne fait rien si pas de form ou si aucun bloc de preview
    const pvName = document.getElementById('pvName');
    const pvEmail = document.getElementById('pvEmail');
    const pvVerified = document.getElementById('pvVerified');
    const pvRoles = document.getElementById('pvRoles');
    if (!form || !(pvName || pvEmail || pvVerified || pvRoles)) return;

    const $ = (sel) => form.querySelector(sel);
    const pick = (...sels) => sels.map($).find(Boolean);
    const roleLabel = (v) => ({ ROLE_ADMIN: 'Admin', ROLE_USER: 'Membre' }[v] || v);

    const elName = pick('[name$="[displayName]"]', '[name$="[prenom]"]', '[name$="[name]"]');
    const elEmail = $('[name$="[email]"]');
    const elVerified = pick('[name$="[isVerified]"]', '[name$="[verified]"]');
    const rolesSelect = pick('select[name$="[roles][]"]', 'select[name$="[roles]"]');
    const rolesChecks = form.querySelectorAll('input[type="checkbox"][name$="[roles][]"]');

    function readRoles() {
        if (!pvRoles) return;
        let values = [];
        if (rolesSelect) {
            values = Array.from(rolesSelect.selectedOptions || []).map((o) => o.value);
        } else if (rolesChecks?.length) {
            values = Array.from(rolesChecks).filter((c) => c.checked).map((c) => c.value);
        }
        if (!values.length) values = ['ROLE_USER'];
        pvRoles.innerHTML = values
            .map(
                (v) =>
                    `<span class="badge ${v === 'ROLE_ADMIN' ? 'bg-danger' : 'bg-secondary'}">${roleLabel(
                        v
                    )}</span>`
            )
            .join(' ');
    }

    function updatePreview() {
        if (pvName && elName) pvName.textContent = elName.value || 'Nom affiché';
        if (pvEmail && elEmail) pvEmail.textContent = elEmail.value || 'email@example.com';

        if (pvVerified && elVerified) {
            const isChecked =
                elVerified.type === 'checkbox' ? elVerified.checked : Boolean(elVerified.value);
            pvVerified.className = 'badge ' + (isChecked ? 'bg-success' : 'bg-warning text-dark');
            pvVerified.textContent = isChecked ? 'E-mail vérifié' : 'E-mail non vérifié';
            // Masque le badge si pas d’email renseigné
            const hasEmail = elEmail && elEmail.value.trim().length > 0;
            pvVerified.style.visibility = hasEmail ? 'visible' : 'hidden';
        }

        readRoles();
    }

    form.addEventListener('input', updatePreview, true);
    form.addEventListener('change', updatePreview, true);
    updatePreview();
}

/* --------------------------------------------------------------------------
   3) Toggle du méga-menu (header)
   - Ouvre/ferme sur clic du bouton
   - Ferme au clic à l’extérieur ou sur Échap
--------------------------------------------------------------------------- */
function initHeaderMenuToggle() {
    const toggle = document.getElementById('ocMenuToggle');
    const panel = document.getElementById('ocMenuPanel');
    if (!toggle || !panel) return;

    const open = () => {
        panel.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
    };
    const close = () => {
        panel.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
    };

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
}

/* --------------------------------------------------------------------------
   Lancement au chargement du DOM
--------------------------------------------------------------------------- */
document.addEventListener('DOMContentLoaded', () => {
    initImagePreviews();
    initProfilePreview();
    initHeaderMenuToggle();
});
// Inventaire : filtres par onglets
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-inventory]');
    if (!root) return;

    const tabs = root.querySelectorAll('.tab');
    const rows = root.querySelectorAll('tbody tr');

    const apply = (filter) => {
        rows.forEach(r => {
            const st = r.getAttribute('data-status');
            r.style.display = (filter === 'all' || filter === st) ? '' : 'none';
        });
    };

    tabs.forEach(btn => btn.addEventListener('click', () => {
        tabs.forEach(b => b.classList.remove('is-active'));
        btn.classList.add('is-active');
        apply(btn.dataset.filter);
    }));
});


window.HELP_KB_URL = window.HELP_KB_URL || document.body?.dataset?.helpKbUrl || '';
