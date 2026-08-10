(function() {
    bindImagePreview('profileImageInput', 'profileImagePreview', 'profileImagePlaceholder');
    bindImagePreview('ktpImageInput', 'ktpImagePreview', 'ktpImagePlaceholder');
    bindImagePreview('businessImageInput', 'businessImagePreview', 'businessImagePlaceholder');
    bindFileName('nibDocumentInput', 'nibDocumentPlaceholder');
})();

function bindFileName(inputId, placeholderId) {
    const input = document.getElementById(inputId);
    const placeholder = document.getElementById(placeholderId);

    if (!input || !placeholder) return;

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];
        if (file) placeholder.textContent = file.name;
    });
}

function bindImagePreview(inputId, previewId, placeholderId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const placeholder = document.getElementById(placeholderId);

    if (!input || !preview || !placeholder) {
        return;
    }

    input.addEventListener('change', function () {
        const file = input.files && input.files[0];

        if (!file) {
            return;
        }

        const reader = new FileReader();

        reader.onload = function (event) {
            preview.src = event.target.result;
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        };

        reader.readAsDataURL(file);
    });
}
