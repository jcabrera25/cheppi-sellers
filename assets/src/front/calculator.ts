/**
 * "Vende en Cheppi" earnings calculator. Works on cents to avoid float rounding; same rounding as Money::percentage().
 */
import './calculator.css';

interface Plan {
  id: string;
  name: string;
  fee: string;
  bps: number;
}

const soles = (cents: number): string =>
  'S/ ' + (cents / 100).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const commission = (cents: number, bps: number): number => Math.floor((cents * bps + 5000) / 10000);

document.querySelectorAll<HTMLElement>('[data-cheppi-calc]').forEach((root) => {
  let plans: Plan[] = [];
  try {
    plans = JSON.parse(root.dataset.plans ?? '[]') as Plan[];
  } catch {
    return;
  }
  const price = root.querySelector<HTMLInputElement>('[data-calc-price]');
  const qty = root.querySelector<HTMLInputElement>('[data-calc-qty]');
  if (!price || !qty) return;

  const render = (): void => {
    const cents = Math.max(0, Math.round(Number(price.value) * 100) || 0);
    const count = Math.max(0, Math.floor(Number(qty.value)) || 0);
    plans.forEach((plan) => {
      const card = root.querySelector<HTMLElement>(`[data-calc-plan="${CSS.escape(plan.id)}"]`);
      if (!card) return;
      const net = cents - commission(cents, plan.bps);
      const monthly = net * count - Math.round(Number(plan.fee) * 100);
      card.querySelector('[data-calc-net]')!.textContent = soles(net);
      card.querySelector('[data-calc-month]')!.textContent = `${soles(monthly)} al mes con ${count} ventas`;
    });
    // Highlight the plan that leaves more money at this volume.
    let best: string | null = null;
    let bestValue = -Infinity;
    plans.forEach((plan) => {
      const value = (cents - commission(cents, plan.bps)) * count - Math.round(Number(plan.fee) * 100);
      if (value > bestValue) {
        bestValue = value;
        best = plan.id;
      }
    });
    root.querySelectorAll<HTMLElement>('[data-calc-plan]').forEach((card) => {
      card.dataset.best = String(card.dataset.calcPlan === best);
    });
  };

  root.addEventListener('input', render);
  render();
});
