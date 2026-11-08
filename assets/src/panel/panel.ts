/**
 * Seller panel enhancements (no dependencies). Loaded as a deferred ES module on /vendedor pages only.
 * - File inputs show the chosen file.
 * - The registration form becomes a 4-step wizard with inline validation and a fetch() submit.
 * - The product form gets option rows, a type switch and photo previews.
 */
import './panel.css';
import { isValidRuc } from './ruc';
import { enhanceProductForm } from './product-form';

const MAX_BYTES = 5 * 1024 * 1024;

function enhanceFileInputs(root: ParentNode): void {
  root.querySelectorAll<HTMLElement>('[data-file]').forEach((box) => {
    const input = box.querySelector<HTMLInputElement>('input[type="file"]');
    const name = box.querySelector<HTMLElement>('[data-file-name]');
    const help = box.querySelector<HTMLElement>('[data-file-help]');
    if (!input || !name || !help) return;
    const originalName = name.innerHTML;
    const originalHelp = help.textContent ?? '';

    input.addEventListener('change', () => {
      const file = input.files?.[0];
      if (!file) {
        box.dataset.filled = 'false';
        name.innerHTML = originalName;
        help.textContent = originalHelp;
        return;
      }
      box.dataset.filled = 'true';
      name.textContent = file.name;
      help.textContent = `${(file.size / 1024 / 1024).toFixed(1)} MB`;
      setError(input.name, file.size > MAX_BYTES ? 'El archivo debe pesar menos de 5 MB.' : '');
    });
  });
}

function setError(field: string, message: string): void {
  const slot = document.querySelector<HTMLElement>(`[data-error-for="${field}"]`);
  if (slot) slot.textContent = message;
  document.querySelectorAll<HTMLElement>(`[name="${field}"]`).forEach((el) => {
    if (message) el.setAttribute('aria-invalid', 'true');
    else el.removeAttribute('aria-invalid');
  });
}

/** Native validity plus rules the browser cannot check (RUC check digit, file size). */
function validateStep(step: HTMLFieldSetElement): boolean {
  let firstInvalid: HTMLElement | null = null;
  step.querySelectorAll<HTMLInputElement | HTMLSelectElement>('input, select').forEach((el) => {
    if (el.type === 'hidden' || el.closest('.ch-honeypot')) return;
    let message = '';
    if (!el.checkValidity()) {
      message = el.validationMessage;
      if (el.validity.valueMissing) {
        message = el.type === 'file' ? 'Sube este documento.' : el.type === 'checkbox' ? 'Debes aceptar para continuar.' : 'Completa este campo.';
      }
    } else if (el.matches('[data-ruc]') && !isValidRuc(el.value)) {
      message = 'El RUC no es válido. Revisa los 11 dígitos.';
    } else if (el instanceof HTMLInputElement && el.type === 'file' && (el.files?.[0]?.size ?? 0) > MAX_BYTES) {
      message = 'El archivo debe pesar menos de 5 MB.';
    }
    setError(el.name, message);
    if (message && !firstInvalid) firstInvalid = el;
  });
  (firstInvalid as HTMLElement | null)?.focus();
  return firstInvalid === null;
}

function buildSummary(form: HTMLFormElement): void {
  const list = form.querySelector<HTMLElement>('[data-summary-list]');
  const card = form.querySelector<HTMLElement>('[data-summary]');
  if (!list || !card) return;
  const data = new FormData(form);
  const label = (name: string): string => {
    const el = form.querySelector<HTMLSelectElement>(`select[name="${name}"]`);
    return el?.selectedOptions[0]?.textContent ?? String(data.get(name) ?? '');
  };
  const rows: Array<[string, string]> = [
    ['Tienda', String(data.get('store_name') ?? '')],
    ['Categoría', label('category')],
    ['RUC', String(data.get('ruc') ?? '')],
    ['Titular', String(data.get('legal_name') ?? '')],
    ['Celular', String(data.get('phone') ?? '')],
    ['Distrito', label('district')],
  ];
  const email = data.get('email');
  if (email) rows.unshift(['Correo', String(email)]);
  const docs = [...form.querySelectorAll<HTMLInputElement>('input[type="file"]')].filter((i) => i.files?.length).length;
  rows.push(['Documentos', `${docs} adjunto${docs === 1 ? '' : 's'}`]);

  list.replaceChildren(
    ...rows.flatMap(([dt, dd]) => {
      const t = document.createElement('dt');
      t.textContent = dt;
      const d = document.createElement('dd');
      d.textContent = dd || '—';
      return [t, d];
    }),
  );
  card.hidden = false;
}

function enhanceRegisterForm(form: HTMLFormElement): void {
  const steps = [...form.querySelectorAll<HTMLFieldSetElement>('fieldset[data-step]')];
  const labels = [...form.querySelectorAll<HTMLElement>('[data-step-label]')];
  const formError = document.querySelector<HTMLElement>('[data-form-error]');
  let current = 0;

  const show = (index: number, focus = true): void => {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((s, i) => (s.dataset.active = String(i === current)));
    labels.forEach((l, i) => {
      if (i === current) l.setAttribute('aria-current', 'step');
      else l.removeAttribute('aria-current');
      l.dataset.done = String(i < current);
    });
    if (current === steps.length - 1) buildSummary(form);
    if (focus) {
      steps[current].querySelector<HTMLElement>('legend')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      steps[current].querySelector<HTMLElement>('input:not([type=hidden]), select')?.focus({ preventScroll: true });
    }
  };

  // Server-side errors (no-JS fallback re-render): open the first step that has one.
  const firstServerError = steps.findIndex((s) => [...s.querySelectorAll('[data-error-for]')].some((e) => e.textContent?.trim()));
  form.dataset.enhanced = 'true';
  show(firstServerError >= 0 ? firstServerError : 0, false);

  form.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;
    if (target.closest('[data-next]')) {
      if (validateStep(steps[current])) show(current + 1);
    } else if (target.closest('[data-prev]')) {
      show(current - 1);
    }
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!validateStep(steps[current])) return;
    const button = form.querySelector<HTMLButtonElement>('[data-submit]');
    button?.setAttribute('aria-busy', 'true');
    if (button) {
      button.disabled = true;
      button.textContent = 'Enviando…';
    }
    if (formError) formError.hidden = true;

    const showGeneral = (message: string): void => {
      if (!formError) return;
      formError.hidden = false;
      formError.querySelector('p')!.textContent = message;
      formError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    let response: Response;
    try {
      // getAttribute, not form.action: the hidden <input name="action"> (needed by admin-post.php) shadows that
      // property, which turned the URL into "[object HTMLInputElement]" and answered 404.
      // Same origin always (the page may be open on www., http or a preview domain): avoids CORS failures.
      const target = new URL(form.getAttribute('action') ?? '', window.location.href);
      response = await fetch(target.pathname + target.search, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
    } catch {
      showGeneral('No pudimos conectar con Cheppi. Revisa tu internet e inténtalo otra vez.');
      resetButton();
      return;
    }

    try {
      let result: { ok: boolean; redirect?: string; errors?: Record<string, string> };
      try {
        result = (await response.json()) as typeof result;
      } catch {
        // The server answered with something that is not our JSON (firewall, timeout, PHP error page).
        showGeneral(
          response.status === 413
            ? 'Tus archivos pesan demasiado. Sube cada documento de menos de 5 MB.'
            : `No pudimos procesar tu solicitud (error ${response.status}). Inténtalo de nuevo en unos minutos; si se repite, escríbenos.`,
        );
        return;
      }
      if (result.ok && result.redirect) {
        window.location.assign(result.redirect);
        return;
      }
      const errors = result.errors ?? {};
      Object.entries(errors).forEach(([field, message]) => setError(field, message));
      const general = errors.form ?? errors.account;
      if (formError) {
        formError.hidden = false;
        formError.querySelector('p')!.textContent = general ?? 'Revisa los campos marcados.';
      }
      const errorStep = steps.findIndex((s) => Object.keys(errors).some((f) => s.querySelector(`[data-error-for="${f}"]`)));
      if (errorStep >= 0) show(errorStep);
      else formError?.scrollIntoView({ behavior: 'smooth' });
    } finally {
      resetButton();
    }

    function resetButton(): void {
      button?.removeAttribute('aria-busy');
      if (button) {
        button.disabled = false;
        button.textContent = 'Enviar solicitud';
      }
    }
  });

  // Clear a field's error as soon as the person edits it.
  form.addEventListener('input', (event) => {
    const el = event.target as HTMLInputElement;
    if (el.name && el.getAttribute('aria-invalid') === 'true' && el.type !== 'file') setError(el.name, '');
  });
}

enhanceFileInputs(document);
const registerForm = document.querySelector<HTMLFormElement>('[data-register-form]');
if (registerForm) enhanceRegisterForm(registerForm);
const productForm = document.querySelector<HTMLFormElement>('[data-product-form]');
if (productForm) enhanceProductForm(productForm);

// Forms that ask before running (delete product).
document.querySelectorAll<HTMLFormElement>('form[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm ?? '¿Continuar?')) event.preventDefault();
  });
});
