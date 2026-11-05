/**
 * Business rules screen: add/remove repeatable rows (plans, categories).
 * Plain DOM, no dependencies. Loaded as a deferred ES module only on this screen.
 */
import './rules.css';

let counter = Date.now();

function addRow(form: HTMLElement, group: string): void {
  const table = form.querySelector<HTMLTableElement>(`[data-repeat="${group}"] tbody`);
  const template = form.querySelector<HTMLTemplateElement>(`template[data-template="${group}"]`);
  if (!table || !template) return;

  const row = template.content.firstElementChild?.cloneNode(true) as HTMLElement | null;
  if (!row) return;

  const index = String(counter++);
  row.querySelectorAll<HTMLInputElement | HTMLSelectElement>('[data-name]').forEach((field) => {
    field.name = (field.dataset.name ?? '').replace('__i__', index);
    field.removeAttribute('data-name');
  });
  table.append(row);
  row.querySelector<HTMLElement>('input, select')?.focus();
}

function init(): void {
  const root = document.querySelector<HTMLElement>('.cheppi-admin');
  if (!root) return;

  // One delegated listener for every button on the page.
  root.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;

    const add = target.closest<HTMLElement>('[data-add-row]');
    if (add) {
      addRow(root, add.dataset.addRow ?? '');
      return;
    }

    const remove = target.closest<HTMLElement>('[data-remove-row]');
    if (remove) {
      const row = remove.closest('tr');
      const body = row?.parentElement;
      // Keep at least one plan row so the form never submits zero plans by accident.
      if (row && body && !(body.closest('[data-repeat="plans"]') && body.children.length === 1)) {
        row.remove();
      }
    }
  });
}

init();
