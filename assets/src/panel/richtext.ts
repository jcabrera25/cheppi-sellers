/**
 * Minimal rich-text editor for descriptions: bold, italic, lists, link. The textarea stays the source
 * of truth (it is what the form posts); the server keeps only safe tags. Pasted content arrives as text.
 */
const BUTTONS: Array<[string, string, string]> = [
  ['bold', 'B', 'Negrita'],
  ['italic', 'I', 'Cursiva'],
  ['insertUnorderedList', '•', 'Lista'],
  ['insertOrderedList', '1.', 'Lista numerada'],
  ['createLink', '🔗', 'Enlace'],
  ['removeFormat', '⌫', 'Quitar formato'],
];

export function enhanceRichText(textarea: HTMLTextAreaElement): void {
  const wrap = document.createElement('div');
  wrap.className = 'ch-rte';
  const bar = document.createElement('div');
  bar.className = 'ch-rte__bar';
  bar.setAttribute('role', 'toolbar');
  const area = document.createElement('div');
  area.className = 'ch-input ch-rte__area';
  area.contentEditable = 'true';
  area.setAttribute('role', 'textbox');
  area.setAttribute('aria-multiline', 'true');
  area.setAttribute('aria-label', textarea.closest('.ch-field')?.querySelector('.ch-field__label')?.textContent?.trim() ?? '');
  area.style.minHeight = `${Math.max(3, textarea.rows) * 1.6}em`;
  const value = textarea.value.trim();
  area.innerHTML = value === '' ? '' : /<[a-z]/i.test(value) ? value : `<p>${value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>')}</p>`;
  if (textarea.getAttribute('aria-invalid')) area.setAttribute('aria-invalid', 'true');

  BUTTONS.forEach(([command, label, title]) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ch-rte__btn';
    button.textContent = label;
    button.title = title;
    button.setAttribute('aria-label', title);
    button.addEventListener('mousedown', (e) => e.preventDefault()); // keep the selection
    button.addEventListener('click', () => {
      area.focus();
      if (command === 'createLink') {
        const url = window.prompt('Dirección del enlace (https://…)');
        if (url && /^https?:\/\//i.test(url)) document.execCommand('createLink', false, url);
      } else {
        document.execCommand(command);
      }
      sync();
    });
    bar.append(button);
  });

  const sync = (): void => {
    const html = area.innerHTML.trim();
    textarea.value = html === '<br>' || area.textContent?.trim() === '' ? '' : html;
  };
  area.addEventListener('input', sync);
  area.addEventListener('blur', sync);
  area.addEventListener('paste', (e) => {
    e.preventDefault();
    document.execCommand('insertText', false, e.clipboardData?.getData('text/plain') ?? '');
  });
  textarea.form?.addEventListener('submit', sync);

  textarea.hidden = true;
  textarea.after(wrap);
  wrap.append(bar, area);
}
