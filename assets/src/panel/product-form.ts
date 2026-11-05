/**
 * Product form: simple/variable switch, option rows, photo previews. No dependencies.
 */
let rowCounter = Date.now();

export function enhanceProductForm(form: HTMLFormElement): void {
  // Type switch: show the matching panel and keep hidden inputs out of native validation.
  const panels = [...form.querySelectorAll<HTMLElement>('[data-type-panel]')];
  const applyType = (): void => {
    const type = form.querySelector<HTMLInputElement>('[data-type-switch]:checked')?.value ?? 'simple';
    panels.forEach((panel) => {
      const active = panel.dataset.typePanel === type;
      panel.hidden = !active;
      panel.querySelectorAll<HTMLInputElement>('input').forEach((input) => (input.disabled = !active));
    });
  };
  form.addEventListener('change', (event) => {
    if ((event.target as HTMLElement).matches('[data-type-switch]')) applyType();
  });
  applyType();

  // Option rows.
  form.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;
    if (target.closest('[data-add-row]')) {
      const body = form.querySelector('[data-repeat="variations"] tbody');
      const template = form.querySelector<HTMLTemplateElement>('template[data-template="variations"]');
      if (!body || !template) return;
      const html = template.innerHTML.split('__i__').join(String(rowCounter++));
      body.insertAdjacentHTML('beforeend', html);
      body.lastElementChild?.querySelector<HTMLInputElement>('input:not([type=hidden])')?.focus();
    }
    const remove = target.closest('[data-remove-row]');
    if (remove) {
      const row = remove.closest('tr');
      if (row && row.parentElement && row.parentElement.children.length > 1) row.remove();
    }
  });

  // Photo previews (object URLs are revoked once loaded).
  const input = form.querySelector<HTMLInputElement>('[data-images] input[type="file"]');
  const previews = form.querySelector<HTMLElement>('[data-previews]');
  const name = form.querySelector<HTMLElement>('[data-images] [data-file-name]');
  if (input && previews && name) {
    const original = name.textContent ?? '';
    input.addEventListener('change', () => {
      previews.replaceChildren(
        ...[...(input.files ?? [])].map((file) => {
          const li = document.createElement('li');
          const img = document.createElement('img');
          img.alt = '';
          img.src = URL.createObjectURL(file);
          img.onload = () => URL.revokeObjectURL(img.src);
          li.append(img);
          return li;
        }),
      );
      const count = input.files?.length ?? 0;
      name.textContent = count ? `${count} foto${count === 1 ? '' : 's'} nueva${count === 1 ? '' : 's'}` : original;
    });
  }

  // Prevent double submits.
  form.addEventListener('submit', () => {
    window.setTimeout(() => form.querySelectorAll<HTMLButtonElement>('button[type="submit"]').forEach((b) => (b.disabled = true)), 0);
  });
}
