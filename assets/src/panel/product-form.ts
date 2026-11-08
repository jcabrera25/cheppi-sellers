/**
 * Product form: simple/variable switch, inventory toggles, attributes, "generate variations",
 * bulk price/stock, photo previews and a light rich-text editor. No dependencies.
 */
import { enhanceRichText } from './richtext';

const MAX_VARIATIONS = 60;
let counter = Date.now();

type Attr = { name: string; options: string[] };

const splitOptions = (raw: string): string[] => {
  const seen = new Set<string>();
  return raw
    .split(/\s*[|,\n]\s*/)
    .map((o) => o.trim())
    .filter((o) => o !== '' && !seen.has(o.toLowerCase()) && (seen.add(o.toLowerCase()), true));
};

export function enhanceProductForm(form: HTMLFormElement): void {
  const q = <T extends Element>(sel: string, root: ParentNode = form): T | null => root.querySelector<T>(sel);
  const qa = <T extends Element>(sel: string, root: ParentNode = form): T[] => [...root.querySelectorAll<T>(sel)];
  const attrList = q<HTMLElement>('[data-attr-list]');
  const variationList = q<HTMLElement>('[data-variation-list]');

  form.querySelectorAll<HTMLTextAreaElement>('textarea[data-richtext]').forEach(enhanceRichText);

  // Type switch and "virtual".
  const type = (): string => q<HTMLInputElement>('[data-type-switch]:checked')?.value ?? 'simple';
  const applyType = (): void => {
    qa<HTMLElement>('[data-type-only]').forEach((el) => (el.hidden = el.dataset.typeOnly !== type()));
    const virtual = type() === 'simple' && (q<HTMLInputElement>('[data-virtual]')?.checked ?? false);
    const shipping = q<HTMLElement>('[data-shipping-card]');
    if (shipping) shipping.hidden = virtual;
  };

  // "Manage stock" shows quantity fields, otherwise the stock status.
  const applyStock = (box: HTMLInputElement): void => {
    const scope = box.closest('.ch-variation__body, section') ?? form;
    qa<HTMLElement>('[data-when-manage]', scope).forEach((el) => {
      if (el.closest('.ch-variation__body, section') === scope) el.hidden = !box.checked || (el.dataset.typeOnly !== undefined && el.dataset.typeOnly !== type());
    });
    qa<HTMLElement>('[data-when-no-manage]', scope).forEach((el) => {
      if (el.closest('.ch-variation__body, section') === scope) el.hidden = box.checked || (el.dataset.typeOnly !== undefined && el.dataset.typeOnly !== type());
    });
  };

  // Attributes used for variations, in order (the index is the key of each variation select).
  const variationAttrs = (): Attr[] =>
    qa<HTMLElement>('[data-attr-row]', attrList ?? form)
      .filter((row) => q<HTMLInputElement>('[data-attr-variation]', row)?.checked)
      .map((row) => ({
        name: q<HTMLInputElement>('[data-attr-name]', row)?.value.trim() ?? '',
        options: splitOptions(q<HTMLInputElement>('[data-attr-options]', row)?.value ?? ''),
      }))
      .filter((a) => a.name !== '' && a.options.length > 0);

  const rowIndex = (row: HTMLElement): string => {
    const named = q<HTMLInputElement>('input[name^="variations["]', row);
    return named?.name.match(/^variations\[([^\]]+)\]/)?.[1] ?? String(counter++);
  };

  // Rebuild the attribute selects of one variation, keeping the chosen values when they still exist.
  const syncSelects = (row: HTMLElement, attrs: Attr[], preset?: string[]): void => {
    const combo = q<HTMLElement>('.ch-variation__combo', row);
    if (!combo) return;
    const current = qa<HTMLSelectElement>('select', combo).map((s) => s.value);
    const i = rowIndex(row);
    combo.replaceChildren(
      ...attrs.map((attr, k) => {
        const select = document.createElement('select');
        select.className = 'ch-input';
        select.name = `variations[${i}][attributes][${k}]`;
        select.setAttribute('aria-label', attr.name);
        select.dataset.variationAttr = String(k);
        select.add(new Option(`${attr.name}…`, ''));
        attr.options.forEach((o) => select.add(new Option(o, o)));
        const wanted = (preset?.[k] ?? current[k] ?? '').toLowerCase();
        const match = attr.options.find((o) => o.toLowerCase() === wanted);
        select.value = match ?? '';
        return select;
      }),
    );
  };
  const syncAllVariations = (): void => {
    const attrs = variationAttrs();
    qa<HTMLElement>('[data-variation-row]', variationList ?? form).forEach((row) => syncSelects(row, attrs));
  };

  const addVariation = (preset?: string[]): HTMLElement | null => {
    const template = q<HTMLTemplateElement>('template[data-template="variation"]');
    if (!template || !variationList) return null;
    if (qa('[data-variation-row]', variationList).length >= MAX_VARIATIONS) {
      window.alert(`Máximo ${MAX_VARIATIONS} variaciones por producto.`);
      return null;
    }
    variationList.insertAdjacentHTML('beforeend', template.innerHTML.split('__i__').join(String(counter++)));
    const row = variationList.lastElementChild as HTMLElement;
    syncSelects(row, variationAttrs(), preset);
    applyStock(q<HTMLInputElement>('[data-toggle-next]', row)!);
    return row;
  };

  const generate = (): void => {
    const attrs = variationAttrs();
    if (attrs.length === 0) {
      window.alert('Primero agrega un atributo con valores y marca «Usar para variaciones».');
      return;
    }
    const combos = attrs.reduce<string[][]>((acc, attr) => acc.flatMap((c) => attr.options.map((o) => [...c, o])), [[]]);
    const existing = new Set(
      qa<HTMLElement>('[data-variation-row]', variationList ?? form).map((row) =>
        qa<HTMLSelectElement>('select[data-variation-attr]', row).map((s) => s.value.toLowerCase()).join('|'),
      ),
    );
    let added = 0;
    for (const combo of combos) {
      if (existing.has(combo.map((c) => c.toLowerCase()).join('|'))) continue;
      if (!addVariation(combo)) break;
      added++;
    }
    if (added === 0) window.alert('Ya existen todas las combinaciones.');
  };

  form.addEventListener('change', (event) => {
    const target = event.target as HTMLElement;
    if (target.matches('[data-type-switch], [data-virtual]')) applyType();
    if (target.matches('[data-type-switch]')) qa<HTMLInputElement>('[data-toggle-next]').forEach(applyStock);
    if (target.matches('[data-toggle-next]')) applyStock(target as HTMLInputElement);
    if (target.matches('[data-attr-name], [data-attr-options], [data-attr-variation]')) syncAllVariations();
    if (target.matches('input[name$="[price]"]')) {
      const row = target.closest<HTMLElement>('[data-variation-row]');
      const summary = row && q<HTMLElement>('[data-variation-summary]', row);
      if (summary) summary.textContent = (target as HTMLInputElement).value ? `S/ ${(target as HTMLInputElement).value}` : 'Sin precio';
    }
  });

  form.addEventListener('click', (event) => {
    const target = event.target as HTMLElement;
    if (target.closest('[data-add-attr]')) {
      const template = q<HTMLTemplateElement>('template[data-template="attribute"]');
      if (template && attrList) {
        attrList.insertAdjacentHTML('beforeend', template.innerHTML.split('__i__').join(String(counter++)));
        q<HTMLInputElement>('[data-attr-name]', attrList.lastElementChild!)?.focus();
        applyType();
      }
    } else if (target.closest('[data-remove-attr]')) {
      const row = target.closest('[data-attr-row]');
      if (row && attrList && attrList.children.length > 1) row.remove();
      else row?.querySelectorAll<HTMLInputElement>('input:not([type=hidden]):not([type=checkbox])').forEach((i) => (i.value = ''));
      syncAllVariations();
    } else if (target.closest('[data-generate-variations]')) {
      generate();
    } else if (target.closest('[data-add-variation]')) {
      addVariation()?.setAttribute('open', '');
    } else if (target.closest('[data-remove-variation]')) {
      event.preventDefault();
      const row = target.closest<HTMLElement>('[data-variation-row]');
      const saved = row && q<HTMLInputElement>('input[name$="[id]"]', row)?.value;
      if (row && (!saved || window.confirm('¿Quitar esta variación? Se eliminará al guardar.'))) row.remove();
    } else if (target.closest('[data-bulk-apply]')) {
      const price = q<HTMLInputElement>('[data-bulk-price]')?.value.trim() ?? '';
      const stock = q<HTMLInputElement>('[data-bulk-stock]')?.value.trim() ?? '';
      qa<HTMLElement>('[data-variation-row]', variationList ?? form).forEach((row) => {
        if (price) {
          const input = q<HTMLInputElement>('input[name$="[price]"]', row)!;
          input.value = price;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (stock) q<HTMLInputElement>('input[name$="[stock]"]', row)!.value = stock;
      });
    }
  });

  applyType();
  qa<HTMLInputElement>('[data-toggle-next]').forEach(applyStock);

  // Photo previews (object URLs are revoked once loaded).
  const input = q<HTMLInputElement>('[data-images] input[type="file"]');
  const previews = q<HTMLElement>('[data-previews]');
  const name = q<HTMLElement>('[data-images] [data-file-name]');
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
    window.setTimeout(() => qa<HTMLButtonElement>('button[type="submit"]').forEach((b) => (b.disabled = true)), 0);
  });
}
