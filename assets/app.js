/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

import './styles/theme-citrus.css'; // doit être importé APRES pour override


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
