<?php

declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Models\Vendor;
use App\Services\Upload\ImageUploader;
use PDOException;
use RuntimeException;

/**
 * A vendor's own product catalog - every query and mutation here is
 * scoped to the logged-in vendor's own vendor_id, via
 * Product::findForVendor()/paginateForVendor(), so there is no code
 * path by which a vendor can read or modify another vendor's listing.
 *
 * Every create or content edit forces approval_status back to
 * 'pending' - Module 15's decision that vendor listings need admin
 * approval before going live means a listing a vendor has changed is,
 * by definition, unreviewed content again. The one exception is
 * restock() (stock_quantity only), a separate fast path for the
 * routine daily task of updating stock without pulling an
 * already-approved listing back into the review queue.
 */
final class ProductController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $vendor = self::currentVendor();
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'approval_status' => (string) $request->query('approval_status', ''),
        ];

        $this->view('vendor/products/index', [
            'pageTitle' => 'My Products | Kymera Collection Vendor Portal',
            'products' => Product::paginateForVendor((int) $vendor['id'], $page, self::PER_PAGE, $filters),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => Product::countForVendor((int) $vendor['id'], $filters),
        ], 'vendor/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('vendor/products/form', [
            'pageTitle' => 'New Product | Kymera Collection Vendor Portal',
            'product' => null,
            'images' => [],
            'attributes' => [],
            'specifications' => [],
            'categories' => self::categoriesWithCommission(),
        ], 'vendor/layouts/app');
    }

    public function store(Request $request): void
    {
        $vendor = self::currentVendor();
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

        $sku = trim((string) $request->input('sku', ''));

        if ($sku === '') {
            $sku = Product::generateSku();
        } elseif (Product::findBy('sku', $sku) !== null) {
            Session::flash('errors', ['sku' => ['This SKU is already in use.']]);
            $this->back();
        }

        try {
            $productId = Product::create([
                'sku' => $sku,
                'barcode' => self::nullable($request->input('barcode')),
                'name' => $data['name'],
                'slug' => Product::generateSlug($data['name']),
                'category_id' => (int) $data['category_id'],
                'brand_id' => null,
                'supplier_id' => null,
                'vendor_id' => (int) $vendor['id'],
                'short_description' => self::nullable($request->input('short_description')),
                'description' => self::nullable($request->input('description')),
                'specifications' => self::buildSpecifications($request),
                'price' => (string) $data['price'],
                'sale_price' => self::nullableDecimal($request->input('sale_price')),
                'cost_price' => self::nullableDecimal($request->input('cost_price')),
                'weight_grams' => self::nullableInt($request->input('weight_grams')),
                'stock_quantity' => (int) $data['stock_quantity'],
                'low_stock_threshold' => self::nullableInt($request->input('low_stock_threshold')) ?? 5,
                'is_featured' => 0,
                'is_active' => $request->input('is_active') !== null ? 1 : 0,
                'approval_status' => 'pending',
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

        Session::flash('success', 'Product submitted for review. It will appear on the storefront once approved.');
        $this->redirect('/vendor/products/' . $productId . '/edit');
    }

    public function edit(Request $request): void
    {
        $vendor = self::currentVendor();
        $product = self::loadOwnProduct($request, $vendor);

        $this->view('vendor/products/form', [
            'pageTitle' => 'Edit Product | Kymera Collection Vendor Portal',
            'product' => $product,
            'images' => ProductImage::forProduct((int) $product['id']),
            'attributes' => ProductAttribute::forProduct((int) $product['id']),
            'specifications' => $product['specifications'] !== null ? (json_decode($product['specifications'], true) ?? []) : [],
            'categories' => self::categoriesWithCommission(),
        ], 'vendor/layouts/app');
    }

    public function update(Request $request): void
    {
        $vendor = self::currentVendor();
        $product = self::loadOwnProduct($request, $vendor);
        $id = (int) $product['id'];

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

        Product::update($id, [
            'sku' => $sku,
            'barcode' => self::nullable($request->input('barcode')),
            'name' => $data['name'],
            'slug' => $slug,
            'category_id' => (int) $data['category_id'],
            'short_description' => self::nullable($request->input('short_description')),
            'description' => self::nullable($request->input('description')),
            'specifications' => self::buildSpecifications($request),
            'price' => (string) $data['price'],
            'sale_price' => self::nullableDecimal($request->input('sale_price')),
            'cost_price' => self::nullableDecimal($request->input('cost_price')),
            'weight_grams' => self::nullableInt($request->input('weight_grams')),
            'stock_quantity' => (int) $data['stock_quantity'],
            'low_stock_threshold' => self::nullableInt($request->input('low_stock_threshold')) ?? 5,
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
            'approval_status' => 'pending',
            'rejection_reason' => null,
            'meta_title' => self::nullable($request->input('meta_title')),
            'meta_description' => self::nullable($request->input('meta_description')),
        ]);

        self::saveAttributes($id, $request);

        try {
            self::saveImages($id, $request);
        } catch (RuntimeException $e) {
            Session::flash('errors', ['images' => [$e->getMessage()]]);
        }

        Session::flash('success', 'Product updated and resubmitted for review.');
        $this->redirect('/vendor/products/' . $id . '/edit');
    }

    /**
     * Stock-only update - deliberately the one product mutation that
     * does NOT reset approval_status, so a vendor can keep an
     * already-approved listing's stock count current without pulling
     * it back into the admin review queue every time inventory moves.
     */
    public function restock(Request $request): void
    {
        $vendor = self::currentVendor();
        $product = self::loadOwnProduct($request, $vendor);

        $data = $this->validate($request->all(), ['stock_quantity' => 'required|integer']);
        Product::update((int) $product['id'], ['stock_quantity' => (int) $data['stock_quantity']]);

        Session::flash('success', 'Stock updated.');
        $this->redirect('/vendor/products');
    }

    public function destroy(Request $request): void
    {
        $vendor = self::currentVendor();
        $product = self::loadOwnProduct($request, $vendor);

        Product::softDelete((int) $product['id']);
        Session::flash('success', 'Product deleted.');
        $this->redirect('/vendor/products');
    }

    public function deleteImage(Request $request): void
    {
        $vendor = self::currentVendor();
        $product = self::loadOwnProduct($request, $vendor);
        $imageId = (int) $request->route('imageId');
        $image = ProductImage::find($imageId);

        if ($image !== null && (int) $image['product_id'] === (int) $product['id']) {
            ImageUploader::delete($image['image_path']);
            ProductImage::delete($imageId);
        }

        Session::flash('success', 'Image removed.');
        $this->redirect('/vendor/products/' . (int) $product['id'] . '/edit');
    }

    public function setPrimaryImage(Request $request): void
    {
        $vendor = self::currentVendor();
        $product = self::loadOwnProduct($request, $vendor);
        $imageId = (int) $request->route('imageId');
        $image = ProductImage::find($imageId);

        if ($image !== null && (int) $image['product_id'] === (int) $product['id']) {
            ProductImage::clearPrimary((int) $product['id']);
            ProductImage::update($imageId, ['is_primary' => 1]);
        }

        Session::flash('success', 'Primary image updated.');
        $this->redirect('/vendor/products/' . (int) $product['id'] . '/edit');
    }

    private static function currentVendor(): array
    {
        $vendor = Vendor::findByUserId((int) Auth::id());

        if ($vendor === null) {
            Response::abort(403, 'No vendor profile is linked to this account.');
        }

        return $vendor;
    }

    private static function loadOwnProduct(Request $request, array $vendor): array
    {
        $product = Product::findForVendor((int) $request->route('id'), (int) $vendor['id']);

        if ($product === null) {
            Response::abort(404, 'Product not found.');
        }

        return $product;
    }

    /**
     * Category rows annotated with the commission rate that will
     * apply if a product is listed under them - the category's own
     * rate if set, otherwise the platform-wide default - so a vendor
     * can see their cut before choosing where to list.
     */
    private static function categoriesWithCommission(): array
    {
        $defaultRate = (float) Setting::get('default_commission_rate', 15.00);

        return array_map(static function (array $category) use ($defaultRate): array {
            $category['effective_commission_rate'] = $category['commission_rate'] !== null
                ? (float) $category['commission_rate']
                : $defaultRate;

            return $category;
        }, Category::tree());
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
