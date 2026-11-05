<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Application\CreateVendorByAdmin;
use Cheppi\Sellers\Application\DeleteVendor;
use Cheppi\Sellers\Application\UpdateVendorProfile;
use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Domain\Vendor\VendorValidationException;
use Cheppi\Sellers\Tests\Fakes\FakeActivity;
use Cheppi\Sellers\Tests\Fakes\FakeDocumentStore;
use Cheppi\Sellers\Tests\Fakes\FakeRules;
use Cheppi\Sellers\Tests\Fakes\FakeVendorRepository;
use Cheppi\Sellers\Tests\TestCase;
use DomainException;

final class AdminVendorTest extends TestCase
{
    private FakeVendorRepository $repo;
    private FakeDocumentStore $docs;

    private function create(): CreateVendorByAdmin
    {
        $this->repo ??= new FakeVendorRepository();
        $this->docs ??= new FakeDocumentStore();
        return new CreateVendorByAdmin($this->repo, $this->docs, new FakeRules());
    }

    /** @return array<string, string> */
    private function input(array $overrides = []): array
    {
        return $overrides + [
            'store_name' => 'Yanbal Lima Centro',
            'ruc' => '20100047218',
            'legal_name' => 'Distribuidora Centro SAC',
            'phone' => '987654321',
            'category' => 'maquillaje',
            'district' => 'lima',
            'instagram' => '',
            'website' => '',
        ];
    }

    public function testAdminCreatesApprovedVendorWithoutDocuments(): void
    {
        $v = ($this->create())(9, $this->input(), [], VendorStatus::Approved, 'pro', 400);
        $this->assertSame(VendorStatus::Approved, $v->status);
        $this->assertSame('pro', $v->planId);
        $this->assertSame(400, $v->commissionOverrideBps);
        $this->assertTrue($v->approvedAt !== null);
        $this->assertSame('yanbal-lima-centro', $v->storeSlug);
    }

    public function testAdminCreateStoresOptionalDocumentsAndValidatesEverything(): void
    {
        $v = ($this->create())(9, $this->input(), ['dni' => new UploadedFile('/tmp/a', 'dni.pdf', 'application/pdf', 100)], VendorStatus::Pending, 'emprende', null);
        $this->assertSame(1, count($this->docs->docs[(int) $v->id]));

        $e = $this->assertThrows(VendorValidationException::class, fn () => ($this->create())(
            9,
            $this->input(['ruc' => '20100047218', 'phone' => '1']),
            [],
            VendorStatus::Suspended,
            'oro',
            20000
        ));
        foreach (['email', 'status', 'plan_id', 'commission_override', 'phone'] as $field) {
            $this->assertTrue(isset($e->errors[$field]), "falta $field");
        }
    }

    public function testUpdateProfileKeepsStatusAndChecksUniqueness(): void
    {
        $a = ($this->create())(1, $this->input(), [], VendorStatus::Approved, 'emprende', null);
        $b = ($this->create())(2, $this->input(['store_name' => 'Otra Marca', 'ruc' => '10418213669']), [], VendorStatus::Pending, 'emprende', null);
        $update = new UpdateVendorProfile($this->repo);

        $edited = $update((int) $a->id, $this->input(['store_name' => 'Yanbal Centro', 'store_slug' => 'Yanbal Centro']));
        $this->assertSame('Yanbal Centro', $edited->storeName);
        $this->assertSame('yanbal-centro', $edited->storeSlug);
        $this->assertSame(VendorStatus::Approved, $edited->status);

        $e = $this->assertThrows(VendorValidationException::class, fn () => $update((int) $b->id, $this->input(['store_slug' => 'yanbal-centro'])));
        $this->assertTrue(isset($e->errors['ruc']));
        $this->assertTrue(isset($e->errors['store_slug']));
    }

    public function testDeleteNeedsConfirmationAndNoFinancialHistory(): void
    {
        $v = ($this->create())(1, $this->input(), ['dni' => new UploadedFile('/tmp/a', 'dni.pdf', 'application/pdf', 100)], VendorStatus::Approved, 'emprende', null);
        $other = ($this->create())(2, $this->input(['store_name' => 'Con Ventas', 'ruc' => '10418213669']), [], VendorStatus::Approved, 'emprende', null);
        $delete = new DeleteVendor($this->repo, $this->docs, new FakeActivity([(int) $other->id]));

        $this->assertThrows(DomainException::class, fn () => $delete((int) $v->id, 'otra cosa'));
        $this->assertThrows(DomainException::class, fn () => $delete((int) $other->id, 'Con Ventas'));

        $delete((int) $v->id, 'yanbal lima centro');
        $this->assertSame(null, $this->repo->find((int) $v->id));
        $this->assertFalse(isset($this->docs->docs[(int) $v->id]));
        $this->assertTrue($this->repo->find((int) $other->id) !== null);
    }
}
