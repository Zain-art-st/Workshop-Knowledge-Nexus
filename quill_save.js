document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('postForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        const activeTab = document.getElementById('activeTabInput');
        if (!activeTab || activeTab.value !== 'rich') return;

        if (!quillInstance) return;

        const html   = quillInstance.root.innerHTML;
        const text   = quillInstance.getText().trim();
        const hidden = document.getElementById('quill-content');

        // Block submission if editor is empty
        if (!text || text.length === 0) {
            e.preventDefault();
            const errEl = document.getElementById('quill-error');
            if (errEl) {
                errEl.textContent = 'Please write something in the editor.';
                errEl.style.display = 'block';
            }
            return;
        }

        if (hidden) {
            hidden.value = html;
        }
    });
});
