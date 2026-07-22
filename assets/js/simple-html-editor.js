(function () {
  const styleId = 'simple-html-editor-style';
  const styleContent = `
.simple-html-editor-wrapper {
  border: 1px solid #ced4da;
  border-radius: 0.375rem;
  background: #fff;
  width: 100%;
  box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.02);
}
.simple-html-editor-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
  padding: 0.35rem 0.5rem;
  background: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
}
.simple-html-editor-button {
  border: 1px solid #ced4da;
  border-radius: 0.25rem;
  padding: 0.2rem 0.55rem;
  font-size: 0.85rem;
  background: #fff;
  color: #212529;
  cursor: pointer;
  transition: background 0.2s ease, border-color 0.2s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.25rem;
}
.simple-html-editor-button svg {
  height: 1rem;
  width: 1rem;
}
.simple-html-editor-button:hover,
.simple-html-editor-button:focus {
  background: #e9ecef;
  border-color: #adb5bd;
}
.simple-html-editor-content {
  min-height: 220px;
  padding: 0.75rem;
  font-size: 0.95rem;
  font-family: inherit;
  line-height: 1.45;
  color: #212529;
  outline: none;
  border: none;
  border-radius: 0 0 0.375rem 0.375rem;
}
.simple-html-editor-content[data-placeholder]:empty::before {
  content: attr(data-placeholder);
  color: #6c757d;
  pointer-events: none;
  display: block;
}
`; 

  function injectStyle() {
    if (document.getElementById(styleId)) {
      return;
    }
    const style = document.createElement('style');
    style.id = styleId;
    style.textContent = styleContent;
    document.head.appendChild(style);
  }

  const buttonConfigs = [
    { command: 'undo', label: '&larr;', title: 'Desfazer', value: null },
    { command: 'redo', label: '&rarr;', title: 'Refazer', value: null },
    { command: 'bold', label: '<strong>B</strong>', title: 'Negrito', value: null },
    { command: 'italic', label: '<em>I</em>', title: 'Itálico', value: null },
    { command: 'underline', label: '<span style="text-decoration:underline">S</span>', title: 'Sublinhar', value: null },
    { command: 'insertUnorderedList', label: '&bullet; Lista', title: 'Lista com marcadores', value: null },
    { command: 'insertOrderedList', label: '1. Lista', title: 'Lista numerada', value: null },
    { command: 'createLink', label: 'Link', title: 'Inserir link', value: null },
    { command: 'unlink', label: 'Limpar link', title: 'Remover link', value: null }
  ];

  function hideTextarea(textarea) {
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    textarea.style.width = '1px';
    textarea.style.height = '1px';
    textarea.style.margin = '0';
    textarea.style.padding = '0';
    textarea.style.border = '0';
    textarea.style.opacity = '0';
    textarea.style.pointerEvents = 'none';
  }

  function createButton(config, editor, sync) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'simple-html-editor-button';
    button.title = config.title;
    button.setAttribute('aria-label', config.title);
    button.innerHTML = config.label;
    button.addEventListener('click', event => {
      event.preventDefault();
      editor.focus();
      if (config.command === 'createLink') {
        const url = window.prompt('Informe a URL', 'https://');
        if (!url) {
          return;
        }
        document.execCommand('createLink', false, url.trim());
      } else {
        document.execCommand(config.command, false, config.value);
      }
      sync();
    });
    return button;
  }

  function init(textareaId) {
    const textarea = document.getElementById(textareaId);
    if (!textarea || textarea.dataset.simpleEditor === '1') {
      return;
    }
    textarea.dataset.simpleEditor = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'simple-html-editor-wrapper';

    const toolbar = document.createElement('div');
    toolbar.className = 'simple-html-editor-toolbar';

    const editor = document.createElement('div');
    editor.className = 'simple-html-editor-content';
    editor.contentEditable = 'true';
    const placeholder = textarea.getAttribute('data-placeholder') || textarea.getAttribute('placeholder') || 'Digite o texto';
    editor.setAttribute('data-placeholder', placeholder);
    editor.innerHTML = textarea.value;

    hideTextarea(textarea);

    textarea.parentNode.insertBefore(wrapper, textarea);
    wrapper.appendChild(toolbar);
    wrapper.appendChild(editor);
    wrapper.appendChild(textarea);

    const sync = () => {
      textarea.value = editor.innerHTML;
    };

    buttonConfigs.forEach(config => toolbar.appendChild(createButton(config, editor, sync)));

    editor.addEventListener('input', sync);

    const form = textarea.closest('form');
    if (form) {
      form.addEventListener('submit', sync);
    }
  }

  if (!window.SimpleHtmlEditor) {
    window.SimpleHtmlEditor = {};
  }
  window.SimpleHtmlEditor.init = init;
  injectStyle();
})();
