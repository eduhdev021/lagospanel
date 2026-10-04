(() => {
    const brandInput = document.querySelector('#brand-color');
    const accentInput = document.querySelector('#accent-color');
    const colorPreview = document.querySelector('.brand-color-preview');

    const updateColors = () => {
        if (!colorPreview) return;
        if (brandInput) colorPreview.style.setProperty('--preview-brand', brandInput.value);
        if (accentInput) colorPreview.style.setProperty('--preview-accent', accentInput.value);
    };

    brandInput?.addEventListener('input', updateColors);
    accentInput?.addEventListener('input', updateColors);
    updateColors();

    const previewUpload = (fieldName, inputId, removeId) => {
        const input = document.querySelector(inputId);
        const image = document.querySelector(`[data-image-preview="${fieldName}"]`);
        const remove = removeId ? document.querySelector(`input[name="${removeId}"]`) : null;
        let objectUrl = null;

        const reset = () => {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            if (image) image.src = image.dataset.defaultSrc;
        };

        input?.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file || !image) return;
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = URL.createObjectURL(file);
            image.src = objectUrl;
            if (remove) remove.checked = false;
        });

        remove?.addEventListener('change', () => {
            if (remove.checked) reset();
        });
    };

    previewUpload('favicon', '#site-favicon', 'remove_favicon');
    previewUpload('og_image', '#og-image', 'remove_og_image');
})();
