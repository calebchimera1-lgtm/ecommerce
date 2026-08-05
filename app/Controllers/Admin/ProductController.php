<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\Supplier;
use App\Services\Upload\ImageUploader;
use PDOException;
use RuntimeException;

final class ProductController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category_id' => (string) $request->query('category_id', ''),
            'brand_id' => (string) $request->query('brand_id', ''),
        ];

        $this->view('admin/products/index', [
            'pageTitle' => 'Products | Kymera Collection Admin',
            'products' => Product::paginateWithFilters($page, self::PER_PAGE, $filters),
            'categories' => Category::tree(),
            'brands' => Brand::all('name', 'ASC'),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => Product::countWithFilters($filters),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/products/form', [
            'pageTitle' => 'New Product | Kymera Collection Admin',
            'product' => null,
            'images' => [],
            'attributes' => [],
            'specifications' => [],
            'categories' => Category::tree(),
            'brands' => Brand::all('name', 'ASC'),
            'suppliers' => Supplier::active(),
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'name' => 'required|max:200',
            'category_id' => 'required|integer',
            'price' => 'required|numeric',
            'stock_quantity' => 'required|integer',
        ]);

        if (Category::find((int) $data['category_id']) === null) {
            Session::flash('errors', ['category_id' => ['Selected category does not exist.']]);
            $this->back();
        }

        $brandId = self::nullableInt($request->input('brand_id'));

        if ($brandId !== null && Brand::find($brandId) === null) {
            Session::flash('errors', ['brand_id' => ['Selected brand does not exist.']]);
            $this->back();
        }

        $sku = trim((string) $request->input('sku', ''));

        if ($sku === '') {
            $sku = Product::generateSku();
        } elseif (Product::findBy('sku', $sku) !== null) {
            Session::flash('errors', ['sku' => ['This SKU is already in use.']]);
            $this->back();
        }

        $supplierId = self::nullableInt($request->input('supplier_id'));

        if ($supplierId !== null && Supplier::find($supplierId) === null) {
            Session::flash('errors', ['supplier_id' => ['Selected supplier does not exist.']]);
            $this->back();
        }

        try {
            $productId = Product::create([
                'sku' => $sku,
                'barcode' => self::nullable($request->input('barcode')),
                'name' => $data['name'],
                'slug' => Product::generateSlug($data['name']),
                'category_id' => (int) $data['category_id'],
                'brand_id' => $brandId,
                'supplier_id' => $supplierId,
                'short_description' => self::nullable($request->input('short_description')),
                'description' => self::nullable($request->input('description')),
                'specifications' => self::buildSpecifications($request),
                'price' => (string) $data['price'],
                'sale_price' => self::nullableDecimal($request->input('sale_price')),
                'cost_price' => self::nullableDecimal($request->input('cost_price')),
                'weight_grams' => self::nullableInt($request->input('weight_grams')),
                'stock_quantity' => (int) $data['stock_quantity'],
                'low_stock_threshold' => self::nullableInt($request->input('low_stock_threshold')) ?? 5,
                'is_featured' => $request->input('is_featured') !== null ? 1 : 0,
                'is_active' => $request->input('is_active') !== null ? 1 : 0,
                'meta_title' => self::nullable($request->input('meta_title')),
                'meta_description' => self::nullable($request->input('meta_description')),
            ]);
        } catch (PDOException) {
            Session::flash('errors', ['name' => ['Could not save the product. Please check the form and try again.']]);
            $this->back();
        }

        self::saveAttributes($productId, $request);

        try {
            self::saveImages($productId, $request);
        } catch (RuntimeException $e) {
            Session::flash('errors', ['images' => [$e->getMessage()]]);
        }

        Session::flash('success', 'Product created.');
        $this->redirect('/admin/products/' . $productId . '/edit');
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->route('id');
        $product = Product::findWithRelations($id);

        if ($product === null) {
            Response::abort(404, 'Product not found.');
        }

        $this->view('admin/products/form', [
            'pageTitle' => 'Edit Product | Kymera Collection Admin',
            'product' => $product,
            'images' => ProductImage::forProduct($id),
            'attributes' => ProductAttribute::forProduct($id),
            'specifications' => $product['specifications'] !== null ? (json_decode($product['specifications'], true) ?? []) : [],
            'categories' => Category::tree(),
            'brands' => Brand::all('name', 'ASC'),
            'suppliers' => Supplier::active(),
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->route('id');
        $product = Product::find($id);

        if ($product === null || $product['deleted_at'] !== null) {
            Response::abort(404, 'Product not found.');
        }

        $data = $this->validate($request->all(), [
            'name' => 'required|max:200',
            'category_id' => 'required|integer',
            'price' => 'required|numeric',
            'stock_quantity' => 'required|integer',
        ]);

        if (Category::find((int) $data['category_id']) === null) {
            Session::flash('errors', ['category_id' => ['Selected category does not exist.']]);
            $this->back();
        }

        $brandId = self::nullableInt($request->input('brand_id'));

        if ($brandId !== null && Brand::find($brandId) === null) {
            Session::flash('errors', ['brand_id' => ['Selected brand does not exist.']]);
            $this->back();
        }

        $sku = trim((string) $request->input('sku', ''));

        if ($sku === '') {
            Session::flash('errors', ['sku' => ['SKU is required.']]);
            $this->back();
        }

        $existingSkuOwner = Product::findBy('sku', $sku);

        if ($existingSkuOwner !== null && (int) $existingSkuOwner['id'] !== $id) {
            Session::flash('errors', ['sku' => ['This SKU is already in use by another product.']]);
            $this->back();
        }

        $slug = $product['slug'];

        if (mb_strtolower($data['name']) !== mb_strtolower($product['name'])) {
            $slug = Product::generateSlug($data['name'], $id);
        }

        $supplierId = self::nullableInt($request->input('supplier_id'));

        if ($supplierId !== null && Supplier::find($supplierId) === null) {
            Session::flash('errors', ['supplier_id' => ['Selected supplier does not exist.']]);
            $this->back();
        }

        Product::update($id, [
            'sku' => $sku,
            'barcode' => self::nullable($request->input('barcode')),
            'name' => $data['name'],
            'slug' => $slug,
            'category_id' => (int) $data['category_id'],
            'brand_id' => $brandId,
            'supplier_id' => $supplierId,
            'short_description' => self::nullable($request->input('short_description')),
            'description' => self::nullable($request->input('description')),
            'specifications' => self::buildSpecifications($request),
            'price' => (string) $data['price'],
            'sale_price' => self::nullableDecimal($request->input('sale_price')),
            'cost_price' => self::nullableDecimal($request->input('cost_price')),
            'weight_grams' => self::nullableInt($request->input('weight_grams')),
            'stock_quantity' => (int) $data['stock_quantity'],
            'low_stock_threshold' => self::nullableInt($request->input('low_stock_threshold')) ?? 5,
            'is_featured' => $request->input('is_featured') !== null ? 1 : 0,
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
            'meta_title' => self::nullable($request->input('meta_title')),
            'meta_description' => self::nullable($request->input('meta_description')),
        ]);

        self::saveAttributes($id, $request);

        try {
            self::saveImages($id, $request);
        } catch (RuntimeException $e) {
            Session::flash('errors', ['images' => [$e->getMessage()]]);
        }

        Session::flash('success', 'Product updated.');
        $this->redirect('/admin/products/' . $id . '/edit');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->route('id');
        Product::softDelete($id);
        Session::flash('success', 'Product deleted.');
        $this->redirect('/admin/products');
    }

    public function deleteImage(Request $request): void
    {
        $productId = (int) $request->route('id');
        $imageId = (int) $request->route('imageId');
        $image = ProductImage::find($imageId);

        if ($image !== null && (int) $image['product_id'] === $productId) {
            ImageUploader::delete($image['image_path']);
            ProductImage::delete($imageId);
        }

        Session::flash('success', 'Image removed.');
        $this->redirect('/admin/products/' . $productId . '/edit');
    }

    public function setPrimaryImage(Request $request): void
    {
        $productId = (int) $request->route('id');
        $imageId = (int) $request->route('imageId');
        $image = ProductImage::find($imageId);

        if ($image !== null && (int) $image['product_id'] === $productId) {
            ProductImage::clearPrimary($productId);
            ProductImage::update($imageId, ['is_primary' => 1]);
        }

        Session::flash('success', 'Primary image updated.');
        $this->redirect('/admin/products/' . $productId . '/edit');
    }

    private static function buildSpecifications(Request $request): ?string
    {
        $keys = (array) $request->input('spec_keys', []);
        $values = (array) $request->input('spec_values', []);
        $specs = [];

        foreach ($keys as $i => $key) {
            $key = trim((string) $key);
            $value = trim((string) ($values[$i] ?? ''));

            if ($key !== '' && $value !== '') {
                $specs[$key] = $value;
            }
        }

        return $specs === [] ? null : json_encode($specs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private static function saveAttributes(int $productId, Request $request): void
    {
        ProductAttribute::deleteForProduct($productId);

        $names = (array) $request->input('attr_name', []);
        $values = (array) $request->input('attr_value', []);
        $priceModifiers = (array) $request->input('attr_price_modifier', []);
        $stocks = (array) $request->input('attr_stock', []);
        $skuSuffixes = (array) $request->input('attr_sku_suffix', []);

        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            $value = trim((string) ($values[$i] ?? ''));

            if ($name === '' || $value === '') {
                continue;
            }

            $priceModifier = trim((string) ($priceModifiers[$i] ?? ''));
            $stock = trim((string) ($stocks[$i] ?? ''));
            $skuSuffix = trim((string) ($skuSuffixes[$i] ?? ''));

            ProductAttribute::create([
                'product_id' => $productId,
                'attribute_name' => $name,
                'attribute_value' => $value,
                'price_modifier' => $priceModifier !== '' ? $priceModifier : '0.00',
                'stock_quantity' => $stock !== '' ? (int) $stock : 0,
                'sku_suffix' => $skuSuffix !== '' ? $skuSuffix : null,
            ]);
        }
    }

    private static function saveImages(int $productId, Request $request): void
    {
        $file = $request->file('images');

        if ($file === null || !isset($file['name']) || count(array_filter((array) $file['name'])) === 0) {
            return;
        }

        $paths = ImageUploader::storeMany($file, 'products');
        $hasPrimary = ProductImage::count('product_id = :product_id AND is_primary = 1', ['product_id' => $productId]) > 0;

        foreach ($paths as $index => $path) {
            ProductImage::create([
                'product_id' => $productId,
                'image_path' => $path,
                'alt_text' => null,
                'is_primary' => (!$hasPrimary && $index === 0) ? 1 : 0,
                'sort_order' => 0,
            ]);
        }
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function nullableDecimal(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        $value = trim((string) $value);

        return $value === '' ? null : (int) $value;
    }
}
