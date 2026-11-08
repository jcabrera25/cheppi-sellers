<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests\Unit\Domain;

use Cheppi\Sellers\Application\SaveVendorProduct;
use Cheppi\Sellers\Application\UploadedFile;
use Cheppi\Sellers\Domain\Catalog\ProductChoices;
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
        return $o + ['type' => 'simple', 'name' => 'Labial mate', 'description' => 'Larga duración', 'categories' => ['15'], 'price' => '39.90', 'sale_price' => '', 'sku' => 'LAB-01', 'manage_stock' => '1', 'stock' => '12'];
    }

    private function choices(): ProductChoices
    {
        return new ProductChoices([15 => 'Maquillaje', 16 => 'Fragancias'], [40 => 'Lumi Skin'], [200 => 'Rubor', 201 => 'Base']);
    }

    public function testSimpleProductWithAllOptions(): void
    {
        $d = ProductDraft::fromArray($this->simple([
            'sale_price' => '29,90', 'sale_from' => '2026-11-10', 'sale_to' => '2026-11-20',
            'short_description' => '<p>Mate</p>', 'categories' => ['15', '16', '99'], 'brands' => ['40', '41'],
            'tags' => 'vegano, cruelty free | vegano', 'stock_status' => 'onbackorder', 'backorders' => 'notify', 'low_stock' => '3',
            'sold_individually' => '1', 'weight' => '0,15', 'length' => '10', 'width' => '2', 'height' => '2',
            'upsells' => ['200', '999'], 'cross_sells' => ['201'], 'purchase_note' => 'Gracias',
            'attributes' => [['name' => 'Acabado', 'options' => 'Mate | Satinado', 'visible' => '1']],
        ]), $this->choices());
        $this->assertSame(3990, $d->price?->cents);
        $this->assertSame(2990, $d->salePrice?->cents);
        $this->assertSame('2026-11-20', $d->saleTo?->format('Y-m-d'));
        $this->assertSame(12, $d->stock);
        $this->assertSame([15, 16], $d->categoryIds);
        $this->assertSame([40], $d->brandIds);
        $this->assertSame(['vegano', 'cruelty free'], $d->tags);
        $this->assertSame('notify', $d->backorders);
        $this->assertSame(3, $d->lowStock);
        $this->assertTrue($d->soldIndividually);
        $this->assertSame('0.15', $d->shipping['weight']);
        $this->assertSame([200], $d->upsellIds);
        $this->assertSame(['Mate', 'Satinado'], $d->attributes[0]->options);
        $this->assertFalse($d->attributes[0]->forVariations, 'simple products never use variations');
        $this->assertFalse($d->isVariable());
    }

    public function testSimpleProductErrors(): void
    {
        $e = $this->assertThrows(ProductValidationException::class, fn () => ProductDraft::fromArray(
            $this->simple(['name' => 'X', 'categories' => ['99'], 'price' => '10', 'sale_price' => '12', 'sku' => 'a b', 'stock' => '-1', 'weight' => 'abc', 'sale_from' => '2026-11-10']),
            $this->choices()
        ));
        foreach (['name', 'categories', 'sale_price', 'sku', 'stock', 'weight', 'sale_from'] as $f) {
            $this->assertTrue(isset($e->errors[$f]), "falta $f");
        }
    }

    public function testVariableProductWithTwoAttributes(): void
    {
        $d = ProductDraft::fromArray([
            'type' => 'variable', 'name' => 'Base líquida', 'categories' => ['15'],
            'attributes' => [
                ['name' => 'Tono', 'options' => 'Claro | Medio', 'visible' => '1', 'variation' => '1'],
                ['name' => 'Tamaño', 'options' => '30 ml, 50 ml', 'visible' => '1', 'variation' => '1'],
                ['name' => 'Ingrediente', 'options' => 'Ácido hialurónico', 'visible' => '1', 'variation' => '0'],
            ],
            'variations' => [
                ['attributes' => ['Claro', '30 ml'], 'price' => '59', 'manage_stock' => '1', 'stock' => '5', 'id' => '301', 'image_id' => '77'],
                ['attributes' => ['medio', '50 ML'], 'price' => '69', 'sale_price' => '49', 'manage_stock' => '0', 'stock_status' => 'outofstock', 'enabled' => '0'],
            ],
        ], $this->choices());
        $this->assertTrue($d->isVariable());
        $this->assertSame(2, count($d->variations));
        $this->assertSame(['Tono' => 'Claro', 'Tamaño' => '30 ml'], $d->variations[0]->attributes);
        $this->assertSame('Medio / 50 ml', $d->variations[1]->label());
        $this->assertSame(301, $d->variations[0]->id);
        $this->assertSame(77, $d->variations[0]->imageId);
        $this->assertSame(null, $d->variations[1]->stock);
        $this->assertFalse($d->variations[1]->enabled);
        $this->assertSame('outofstock', $d->variations[1]->stockStatus);
        $this->assertFalse($d->attributes[2]->forVariations);
    }

    public function testVariableProductErrors(): void
    {
        $base = ['type' => 'variable', 'name' => 'Base', 'categories' => ['15']];
        $e = $this->assertThrows(ProductValidationException::class, fn () => ProductDraft::fromArray($base + [
            'attributes' => [['name' => 'Tono', 'options' => 'Claro', 'variation' => '1'], ['name' => 'tono', 'options' => '']],
            'variations' => [['attributes' => ['Claro'], 'price' => '0'], ['attributes' => ['claro'], 'price' => '10'], ['attributes' => ['Oscuro'], 'price' => '10']],
        ], $this->choices()));
        foreach (['attributes.1.name', 'attributes.1.options', 'variations.0.price', 'variations.1.attributes', 'variations.2.attributes'] as $f) {
            $this->assertTrue(isset($e->errors[$f]), "falta $f");
        }
        $e = $this->assertThrows(ProductValidationException::class, fn () => ProductDraft::fromArray($base, $this->choices()));
        $this->assertTrue(isset($e->errors['attributes']));
        $e = $this->assertThrows(ProductValidationException::class, fn () => ProductDraft::fromArray($base + ['attributes' => [['name' => 'Tono', 'options' => 'A|B', 'variation' => '1']]], $this->choices()));
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
            $catalog->products[1000 + $i] = ['owner' => 5, 'draft' => ProductDraft::fromArray($this->simple(), $this->choices()), 'status' => ProductStatus::Published, 'images' => 1];
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
        $catalog->products[77] = ['owner' => 9, 'draft' => ProductDraft::fromArray($this->simple(), $this->choices()), 'status' => ProductStatus::Published, 'images' => 1];
        $save = new SaveVendorProduct($this->vendor(), $catalog, new FakeRules());
        $e = $this->assertThrows(ProductValidationException::class, fn () => $save(5, 77, $this->simple(), [], [], false));
        $this->assertSame('Producto no encontrado.', $e->errors['form']);
        $this->assertFalse($catalog->trash(5, 77));
    }
}
