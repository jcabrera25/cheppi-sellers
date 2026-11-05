/** Same rule as Domain\Vendor\Ruc (PHP): 11 digits, valid prefix, module-11 check digit. */
const PREFIXES = ['10', '15', '16', '17', '20'];
const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

export function isValidRuc(raw: string): boolean {
  const digits = raw.replace(/\D/g, '');
  if (!/^\d{11}$/.test(digits) || !PREFIXES.includes(digits.slice(0, 2))) return false;
  const sum = WEIGHTS.reduce((acc, w, i) => acc + Number(digits[i]) * w, 0);
  let check = 11 - (sum % 11);
  if (check === 10) check = 0;
  else if (check === 11) check = 1;
  return check === Number(digits[10]);
}
