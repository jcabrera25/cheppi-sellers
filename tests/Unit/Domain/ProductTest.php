<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Application\SaveVendorProduct;
use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Catalog\ProductDraft;
use Cheppi\Sellers\Domain\Catalog\ProductStatus;
use Cheppi\Sellers\Domain\Catalog\ProductValidationException;
use Cheppi\Sellers\Domain\Rules\BusinessRules;
use Cheppi\Sellers\Domain\Vendor\Vendor;
use Cheppi\Sellers\Domain\Vendor\VendorStatus;
use Cheppi\Sellers\Tests\Fakes\FakeCatalog;
use Cheppi\Sellers\Tests\Fakes\FakeRules;
use Cheppi\Sellers\Tests\Fakes\FakeVendorRepository;
use Cheppi\Sellers\Tests\TestCase;
use DateTimeImmutable;

final class ProductTest extends TestCase
{
    private function vendor(VendorStatus $status = VendorStatus::Approved, string $plan = 'emprende'): FakeVendorRepository
    {
        $repo = new FakeVendorRepository();
        $now = new DateTimeImmutable();
        $repo->save(new Vendor(null, 5, 'Marca', 'marca', $status, $plan, null, '20100047218', 'Marca SAC', '987654321', 'maquillaje', 'lima', '', '', '', $now, $now));
        return $repo;
    }

    private function img(string $mime = 'image/jpeg', int $size = 1000): UploadedFile
    {
        return new UploadedFile('/tmp/x', 'foto.jpg', $mime, $size);
    }

    /** @return array<string, mixed> */
    private function simple(array $o = []): array
    {
        return $o + ['type' => 'simple', 'name' => 'Labial mate', 'description' => 'Larga duración', 'category' => '15', 'price' => '39.90', 'sale_price' => '', 'sku' => 'LAB-01', 'stock' => '12'];
    }

    public function testSimpleProductParses(): void
    {
        $d = ProductDraft::fromArray($this->simple(['sale_price' => '29,90']), [15]);
        $this->assertSame(3990, $d->price?->cents);
        $this->assertSame(2990, $d->salePrice?->cents);
        $this->assertSame(12, $d->stock);
        $this->assertFalse($d->isVariable());
    }

    public function testSimpleProductErrors(): void
    {
        $e = $this->assertThrows(ProductValidationException::class, fn () => ProductDraft::fromArray(
            $this->simple(['name' => 'X', 'category' => '99', 'price' => '10', 'sale_price' => '12', 'sku' => 'a b', 'stock' => '-1']),
            [15]
        ));
        foreach (['name', 'category', 'sale_price', 'sku', 'stock'] as $f) {
            $this->assertTrue(isset($e->errors[$f]), "falta $f");
        }
    }

    public function testVariableProductWithOptions(): void
    {
        $d = ProductDraft::fromArray([
            'type' => 'variable', 'name' => 'Base líquida', 'category' => '15', 'attribute_name' => 'Tono',
            'variations' => [
                ['option' => 'Claro', 'price' => '59', 'stock' => '5', 'id' => '301'],
                ['option' => '', 'price' => ''],
                ['option' => 'Medio', 'price' => '59', 'sale_price' => '49', 'stock' => ''],
            ],
        ], [15]);
        $this->assertTrue($d->isVariable());
        $this->assertSame(2, count($d->variations));
        $this->assertSame(301, $d->variations[0]->id);
        $this->assertSame(0, $d->variations[1]->stock);
        $this->assertSame(4900, $d->variations[1]->salePrice?->cents);
    }

    public function testVariableProductErrors(): void
    {
        $e = $this->assertThrows(ProductValidationException::class, fn () => ProductDraft::fromArray([
            'type' => 'variable', 'name' => 'Base', 'category' => '15', 'attribute_name' => '',
            'variations' => [['option' => 'Claro', 'price' => '0'], ['option' => 'claro', 'price' => '10']],
        ], [15]));
        $this->assertTrue(isset($e->errors['attribute_name']));
        $this->assertTrue(isset($e->errors['variations.0.price']));
        $this->assertTrue(isset($e->errors['variations.1.option']));
        $e = $this->assertThrows(ProductValidationException::class, fn () => ProductDraft::fromArray(['type' => 'variable', 'name' => 'Base', 'category' => '15', 'attribute_name' => 'Tono'], [15]));
        $this->assertTrue(isset($e->errors['variations']));
    }

    public function testStatusPolicy(): void
    {
        $moderated = BusinessRules::defaults();
        $data = $moderated->toArray();
        $data['moderate_products'] = false;
        $open = BusinessRules::fromArray($data);
        $this->assertSame(ProductStatus::Pending, ProductStatus::afterSave(null, false, $moderated));
        $this->assertSame(ProductStatus::Published, ProductStatus::afterSave(null, false, $open));
        $this->assertSame(ProductStatus::Draft, ProductStatus::afterSave(ProductStatus::Published, true, $moderated));
        $this->assertSame(ProductStatus::Published, ProductStatus::afterSave(ProductStatus::Published, false, $moderated));
        $this->assertSame(ProductStatus::Pending, ProductStatus::afterSave(ProductStatus::Draft, false, $moderated));
    }

    public function testSaveRequiresApprovedVendorAndPhoto(): void
    {
        $catalog = new FakeCatalog();
        $pending = new SaveVendorProduct($this->vendor(VendorStatus::Pending), $catalog, new FakeRules());
        $e = $this->assertThrows(ProductValidationException::class, fn () => $pending(5, null, $this->simple(), [$this->img()], [], false));
        $this->assertTrue(isset($e->errors['form']));

        $save = new SaveVendorProduct($this->vendor(), $catalog, new FakeRules());
        $e = $this->assertThrows(ProductValidationException::class, fn () => $save(5, null, $this->simple(), [], [], false));
        $this->assertTrue(isset($e->errors['images']));
        $draft = $save(5, null, $this->simple(), [], [], true);
        $this->assertSame(ProductStatus::Draft, $draft['status']);
        $sent = $save(5, null, $this->simple(), [$this->img()], [], false);
        $this->assertSame(ProductStatus::Pending, $sent['status']);
    }

    public function testImageRules(): void
    {
        $catalog = new FakeCatalog();
        $save = new SaveVendorProduct($this->vendor(), $catalog, new FakeRules());
        $e = $this->assertThrows(ProductValidationException::class, fn () => $save(5, null, $this->simple(), [$this->img('application/pdf')], [], false));
        $this->assertTrue(isset($e->errors['images']));
        $nine = array_fill(0, 9, $this->img());
        $e = $this->assertThrows(ProductValidationException::class, fn () => $save(5, null, $this->simple(), $nine, [], false));
        $this->assertTrue(str_contains($e->errors['images'], 'Máximo 8'));

        $first = $save(5, null, $this->simple(), array_fill(0, 8, $this->img()), [], false);
        $this->assertThrows(ProductValidationException::class, fn () => $save(5, $first['id'], $this->simple(), [$this->img()], [], false));
        $ok = $save(5, $first['id'], $this->simple(), [$this->img()], [11], false);
        $this->assertSame(8, $catalog->products[$ok['id']]['images']);
    }

    public function testEditCannotRemoveLastPhotoAndErrorsComeTogether(): void
    {
        $catalog = new FakeCatalog();
        $save = new SaveVendorProduct($this->vendor(), $catalog, new FakeRules());
        $first = $save(5, null, $this->simple(), [$this->img()], [], false);
        $e = $this->assertThrows(ProductValidationException::class, fn () => $save(5, $first['id'], $this->simple(), [], [21], false));
        $this->assertTrue(isset($e->errors['images']));
        $this->assertSame(ProductStatus::Draft, $save(5, $first['id'], $this->simple(), [], [21], true)['status']);

        $e = $this->assertThrows(ProductValidationException::class, fn () => $save(5, null, $this->simple(['name' => 'X']), [], [], false));
        $this->assertTrue(isset($e->errors['images'], $e->errors['name']));
    }

    public function testPlanLimitOnlyOnCreate(): void
    {
        $catalog = new FakeCatalog();
        $save = new SaveVendorProduct($this->vendor(), $catalog, new FakeRules()); // Emprende: 50
        for ($i = 0; $i < 50; $i++) {
            $catalog->products[1000 + $i] = ['owner' => 5, 'draft' => ProductDraft::fromArray($this->simple(), [15]), 'status' => ProductStatus::Published, 'images' => 1];
        }
        $e = $this->assertThrows(ProductValidationException::class, fn () => $save(5, null, $this->simple(), [$this->img()], [], false));
        $this->assertTrue(str_contains($e->errors['form'], '50 productos'));
        $edit = $save(5, 1000, $this->simple(['name' => 'Labial editado']), [], [], false);
        $this->assertSame(ProductStatus::Published, $edit['status']);

        $pro = new SaveVendorProduct($this->vendor(VendorStatus::Approved, 'pro'), $catalog, new FakeRules()); // unlimited
        $this->assertSame(ProductStatus::Pending, $pro(5, null, $this->simple(), [$this->img()], [], false)['status']);
    }

    public function testCannotEditSomeoneElsesProduct(): void
    {
        $catalog = new FakeCatalog();
        $catalog->products[77] = ['owner' => 9, 'draft' => ProductDraft::fromArray($this->simple(), [15]), 'status' => ProductStatus::Published, 'images' => 1];
        $save = new SaveVendorProduct($this->vendor(), $catalog, new FakeRules());
        $e = $this->assertThrows(ProductValidationException::class, fn () => $save(5, 77, $this->simple(), [], [], false));
        $this->assertSame('Producto no encontrado.', $e->errors['form']);
        $this->assertFalse($catalog->trash(5, 77));
    }
}
