<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Domain\Payout;

use Cheppi\Sellers\Domain\Vendor\VendorValidationException;

/** Where the vendor gets paid. Peruvian banks; the CCI (20 digits) is what interbank transfers need. */
final class BankDetails
{
    public const BANKS = [
        'bcp' => 'BCP', 'interbank' => 'Interbank', 'bbva' => 'BBVA', 'scotiabank' => 'Scotiabank',
        'banbif' => 'BanBif', 'pichincha' => 'Banco Pichincha', 'nacion' => 'Banco de la Nación',
        'gnb' => 'Banco GNB', 'falabella' => 'Banco Falabella', 'ripley' => 'Banco Ripley',
        'caja' => 'Caja municipal', 'otro' => 'Otro',
    ];

    public function __construct(
        public readonly string $bank,
        public readonly string $holder,
        public readonly string $account,
        public readonly string $cci
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @throws VendorValidationException
     */
    public static function fromInput(array $input): self
    {
        $bank = (string) ($input['bank'] ?? '');
        $holder = trim((string) ($input['holder'] ?? ''));
        $account = preg_replace('/\D+/', '', (string) ($input['account'] ?? '')) ?? '';
        $cci = preg_replace('/\D+/', '', (string) ($input['cci'] ?? '')) ?? '';
        $errors = [];
        if (!isset(self::BANKS[$bank])) {
            $errors['bank'] = 'Elige tu banco.';
        }
        if (mb_strlen($holder) < 3 || mb_strlen($holder) > 120) {
            $errors['holder'] = 'Escribe el titular tal como figura en el banco.';
        }
        if ($account !== '' && (strlen($account) < 8 || strlen($account) > 20)) {
            $errors['account'] = 'El número de cuenta tiene entre 8 y 20 dígitos.';
        }
        if (strlen($cci) !== 20) {
            $errors['cci'] = 'El CCI tiene 20 dígitos.';
        }
        if ($errors !== []) {
            throw new VendorValidationException($errors);
        }
        return new self($bank, $holder, $account, $cci);
    }

    public function bankLabel(): string
    {
        return self::BANKS[$this->bank] ?? $this->bank;
    }

    /** CCI with only the last 4 digits visible, for screens. */
    public function maskedCci(): string
    {
        return str_repeat('•', 16) . substr($this->cci, -4);
    }

    /** @return array{bank: string, holder: string, account: string, cci: string} */
    public function toArray(): array
    {
        return ['bank' => $this->bank, 'holder' => $this->holder, 'account' => $this->account, 'cci' => $this->cci];
    }

    /** @param array<string, mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self((string) ($row['bank'] ?? ''), (string) ($row['holder'] ?? ''), (string) ($row['account'] ?? ''), (string) ($row['cci'] ?? ''));
    }
}
