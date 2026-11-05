/**
 * Cheppi → Vendedores: review note rules, delete confirmation and file pickers.
 * Loaded only on this screen; no dependencies.
 */
import './vendors.css';

// Review: the note is required for decisions that need a reason; confirm destructive ones.
const review = document.querySelector<HTMLFormElement>('[data-review-form]');
const note = review?.querySelector<HTMLTextAreaElement>('[data-note]');
if (review && note) {
  const sync = (): void => {
    const chosen = review.querySelector<HTMLInputElement>('input[name="next_status"]:checked');
    note.required = chosen?.dataset.needsNote === 'true';
  };
  review.addEventListener('change', sync);
  sync();
  review.addEventListener('submit', (event) => {
    const chosen = review.querySelector<HTMLInputElement>('input[name="next_status"]:checked');
    if (chosen && ['rejected', 'suspended'].includes(chosen.value) && !window.confirm('El vendedor recibirá un correo con tu comentario. ¿Continuar?')) {
      event.preventDefault();
    }
  });
}

// Delete: enable the button only when the store name is typed exactly.
const del = document.querySelector<HTMLFormElement>('[data-delete-form]');
const confirmInput = del?.querySelector<HTMLInputElement>('[data-confirm-name]');
const confirmButton = del?.querySelector<HTMLButtonElement>('[data-confirm-submit]');
if (del && confirmInput && confirmButton) {
  const expected = (confirmInput.dataset.expected ?? '').trim().toLowerCase();
  confirmInput.addEventListener('input', () => {
    confirmButton.disabled = confirmInput.value.trim().toLowerCase() !== expected;
  });
}

// Document rows: show the chosen file name on the Subir/Reemplazar button.
document.querySelectorAll<HTMLInputElement>('[data-doc-input]').forEach((input) => {
  const holder = input.closest<HTMLElement>('.ch-upload-btn');
  const label = holder?.querySelector<HTMLElement>('[data-doc-label]');
  if (!holder || !label) return;
  const original = label.textContent ?? '';
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    holder.dataset.chosen = String(Boolean(file));
    label.textContent = file ? file.name : original;
  });
});

// File boxes on "Añadir vendedor".
document.querySelectorAll<HTMLElement>('[data-file]').forEach((box) => {
  const input = box.querySelector<HTMLInputElement>('input[type="file"]');
  const name = box.querySelector<HTMLElement>('[data-file-name]');
  if (!input || !name) return;
  const original = name.textContent ?? '';
  input.addEventListener('change', () => {
    const file = input.files?.[0];
    box.dataset.filled = String(Boolean(file));
    name.textContent = file ? file.name : original;
  });
});
