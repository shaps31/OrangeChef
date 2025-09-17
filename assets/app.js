/*
 * Fichier JS principal (importé via {{ importmap('app') }})
 * - Charge le thème CSS
 * - Prévisualisation d'image lors d'un upload <input type="file">
 * - Aperçu "live" d'un profil (nom, email, rôles, vérification)
 * - Ouverture/fermeture du méga-menu du header
 */



console.log('✅ assets/app.js chargé — Bienvenue dans AssetMapper !');

/* --------------------------------------------------------------------------
   1) Prévisualisation d’image sur les <input type="file">
   - Ajoute une <img> juste après chaque input fichier
   - Affiche l’aperçu dès qu’un fichier image est choisi
--------------------------------------------------------------------------- */
function initImagePreviews() {
    const inputs = document.querySelectorAll('input[type="file"]');
    if (!inputs.length) return;

    inputs.forEach((input) => {
        // Image de preview (créée une seule fois)
        const preview = document.createElement('img');
        preview.style.maxWidth = '120px';
        preview.style.marginTop = '10px';
        preview.style.display = 'block';
        input.insertAdjacentElement('afterend', preview);

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file || !file.type?.startsWith('image/')) {
                preview.removeAttribute('src');
                return;
            }
            const reader = new FileReader();
            reader.onload = (e) => (preview.src = e.target?.result || '');
            reader.readAsDataURL(file);
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
