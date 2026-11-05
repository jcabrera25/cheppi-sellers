<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Application\ResubmitVendorDocuments;
use Cheppi\Sellers\Application\ReviewVendor;
use Cheppi\Sellers\Application\SubmitVendorApplication;
use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Vendor\Ruc;
use Cheppi\Sellers\Domain\Vendor\StoreSlug;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use Cheppi\Sellers\Tests\Fakes\FakeDocumentStore;
use Cheppi\Sellers\Tests\Fakes\FakeNotifier;
use Cheppi\Sellers\Tests\Fakes\FakeRules;
use Cheppi\Sellers\Tests\Fakes\FakeVendorRepository;
use Cheppi\Sellers\Tests\TestCase;
use DomainException;

final class VendorTest extends TestCase
{
    private FakeVendorRepository $repo;
    private FakeDocumentStore $docs;
    private FakeNotifier $notifier;

    private function submit(): SubmitVendorApplication
    {
        $this->repo ??= new FakeVendorRepository();
        $this->docs ??= new FakeDocumentStore();
        $this->notifier ??= new FakeNotifier();
        return new SubmitVendorApplication($this->repo, $this->docs, new FakeRules(), $this->notifier);
    }

    /** @return array<string, string> */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'store_name' => 'Belleza Ñusta & Co',
            'ruc' => '20100047218',
            'legal_name' => 'Belleza Ñusta SAC',
            'phone' => '987 654 321',
            'category' => 'maquillaje',
            'district' => 'san-isidro',
            'instagram' => '@bellezanusta',
            'website' => '',
        ];
    }

    /** @return array<string, UploadedFile> */
    private function files(string $mime = 'application/pdf', int $size = 2048): array
    {
        return [
            'dni' => new UploadedFile('/tmp/a', 'dni.pdf', $mime, $size),
            'bank_account' => new UploadedFile('/tmp/b', 'banco.pdf', $mime, $size),
        ];
    }

    public function testRucCheckDigit(): void
    {
        $this->assertTrue(Ruc::isValid('20100047218'));
        $this->assertFalse(Ruc::isValid('20100047219'));
        $this->assertFalse(Ruc::isValid('30100047218'));
        $this->assertSame('20100047218', Ruc::fromString('201-0004-7218')->value);
    }

    public function testSlugIsReadableAndUnique(): void
    {
        $this->assertSame('belleza-nusta-y-co', StoreSlug::fromName('Belleza Ñusta & Co'));
        $taken = ['mi-tienda', 'mi-tienda-2'];
        $this->assertSame('mi-tienda-3', StoreSlug::unique('Mi Tienda', fn (string $s): bool => in_array($s, $taken, true)));
    }

    public function testSubmitCreatesPendingVendorWithDefaultPlan(): void
    {
        $vendor = ($this->submit())(5, $this->input(), $this->files(), true);
        $this->assertSame(VendorStatus::Pending, $vendor->status);
        $this->assertSame('emprende', $vendor->planId);
        $this->assertSame('belleza-nusta-y-co', $vendor->storeSlug);
        $this->assertSame('987654321', $vendor->phone);
        $this->assertSame('bellezanusta', $vendor->instagram);
        $this->assertSame(2, count($this->docs->docs[(int) $vendor->id]));
        $this->assertSame(['received:belleza-nusta-y-co'], $this->notifier->sent);
    }

    public function testSubmitReportsAllErrorsAndWritesNothing(): void
    {
        $e = $this->assertThrows(VendorValidationException::class, fn () => ($this->submit())(
            5,
            $this->input(['ruc' => '123', 'phone' => '12345', 'district' => 'marte']),
            ['dni' => new UploadedFile('/tmp/a', 'x.exe', 'application/x-msdownload', 10)],
            false
        ));
        foreach (['ruc', 'phone', 'district', 'doc_dni', 'doc_bank_account', 'terms'] as $field) {
            $this->assertTrue(isset($e->errors[$field]), "falta error en $field");
        }
        $this->assertSame([], $this->repo->vendors);
        $this->assertSame([], $this->notifier->sent);
    }

    public function testDuplicateRucAndSecondStoreAreRejected(): void
    {
        ($this->submit())(5, $this->input(), $this->files(), true);
        $e = $this->assertThrows(VendorValidationException::class, fn () => ($this->submit())(6, $this->input(['store_name' => 'Otra']), $this->files(), true));
        $this->assertTrue(isset($e->errors['ruc']));
        $e = $this->assertThrows(VendorValidationException::class, fn () => ($this->submit())(5, $this->input(['ruc' => '10418213669']), $this->files(), true));
        $this->assertTrue(isset($e->errors['account']));
    }

    public function testOversizedFileIsRejected(): void
    {
        $e = $this->assertThrows(VendorValidationException::class, fn () => ($this->submit())(5, $this->input(), $this->files('application/pdf', 6 * 1024 * 1024), true));
        $this->assertTrue(isset($e->errors['doc_dni']));
    }

    public function testReviewFlowWithNotesAndResubmission(): void
    {
        $vendor = ($this->submit())(5, $this->input(), $this->files(), true);
        $review = new ReviewVendor($this->repo, new FakeRules(), $this->notifier);

        $this->assertThrows(DomainException::class, fn () => $review((int) $vendor->id, VendorStatus::NeedsInfo, '', 'emprende', null));

        $observed = $review((int) $vendor->id, VendorStatus::NeedsInfo, 'La constancia bancaria no se lee.', 'emprende', null);
        $this->assertSame(VendorStatus::NeedsInfo, $observed->status);

        $resubmit = new ResubmitVendorDocuments($this->repo, $this->docs, $this->notifier);
        $back = $resubmit(5, ['bank_account' => new UploadedFile('/tmp/c', 'banco2.pdf', 'application/pdf', 1000)]);
        $this->assertSame(VendorStatus::Pending, $back->status);
        $this->assertSame('', $back->statusNote);

        $approved = $review((int) $vendor->id, VendorStatus::Approved, '', 'pro', 450);
        $this->assertSame(VendorStatus::Approved, $approved->status);
        $this->assertSame('pro', $approved->planId);
        $this->assertSame(450, $approved->commissionOverrideBps);
        $this->assertTrue($approved->approvedAt !== null);
        $this->assertSame(
            ['received:belleza-nusta-y-co', 'status:needs_info', 'resubmitted:belleza-nusta-y-co', 'status:approved'],
            $this->notifier->sent
        );
    }

    public function testReviewRejectsUnknownPlanAndIllegalTransition(): void
    {
        $vendor = ($this->submit())(5, $this->input(), $this->files(), true);
        $review = new ReviewVendor($this->repo, new FakeRules(), $this->notifier);
        $this->assertThrows(DomainException::class, fn () => $review((int) $vendor->id, null, '', 'oro', null));
        $this->assertThrows(DomainException::class, fn () => $review((int) $vendor->id, VendorStatus::Suspended, 'x', 'emprende', null));
    }
}
