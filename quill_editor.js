let quillInstance = null;

function initQuillEditor() {
    const container = document.getElementById('quill-editor');
    if (!container) return;

    quillInstance = new Quill('#quill-editor', {
        theme: 'snow',
        placeholder: 'Write something rich and detailed…',
        modules: {
            toolbar: ScholarSpaceToolbar
        }
    });
    const editorEl = document.querySelector('.ql-editor');
    const toolbarEl = document.querySelector('.ql-toolbar');
    const containerEl = document.querySelector('.ql-container');

    if (toolbarEl) {
        toolbarEl.style.background    = 'rgba(255,255,255,0.05)';
        toolbarEl.style.borderColor   = 'rgba(255,255,255,0.12)';
        toolbarEl.style.borderRadius  = '0';
    }
    if (containerEl) {
        containerEl.style.background  = 'transparent';
        containerEl.style.borderColor = 'rgba(255,255,255,0.12)';
        containerEl.style.minHeight   = '160px';
    }
    if (editorEl) {
        editorEl.style.color          = '#f0eff5';
        editorEl.style.fontSize       = '15px';
        editorEl.style.lineHeight     = '1.8';
        editorEl.style.minHeight      = '160px';
    }

    // Style toolbar buttons white
    document.querySelectorAll('.ql-toolbar button, .ql-toolbar .ql-picker-label').forEach(el => {
        el.style.color  = '#9b9ab0';
        el.style.stroke = '#9b9ab0';
        el.style.fill   = 'none';
    });
}

// Call init when DOM is ready
document.addEventListener('DOMContentLoaded', initQuillEditor);
