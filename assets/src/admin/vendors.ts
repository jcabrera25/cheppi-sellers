/**
 * Vendor review form: makes the note required when the chosen decision needs one,
 * and confirms destructive decisions. Loaded only on Cheppi → Vendedores.
 */
import './vendors.css';

const form = document.querySelector<HTMLFormElement>('[data-review-form]');
const note = form?.querySelector<HTMLTextAreaElement>('[data-note]');

if (form && note) {
  const sync = (): void => {
    const chosen = form.querySelector<HTMLInputElement>('input[name="next_status"]:checked');
    note.required = chosen?.dataset.needsNote === 'true';
  };
  form.addEventListener('change', sync);
  sync();

  form.addEventListener('submit', (event) => {
    const chosen = form.querySelector<HTMLInputElement>('input[name="next_status"]:checked');
    if (chosen && ['rejected', 'suspended'].includes(chosen.value)) {
      const ok = window.confirm('El vendedor recibirá un correo con tu comentario. ¿Continuar?');
      if (!ok) event.preventDefault();
    }
  });
}
