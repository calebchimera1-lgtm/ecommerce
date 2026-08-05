<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Brand;
use PDOException;

final class BrandController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('admin/brands/index', [
            'pageTitle' => 'Brands | Kymera Collection Admin',
            'brands' => Brand::all('name', 'ASC'),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/brands/form', [
            'pageTitle' => 'New Brand | Kymera Collection Admin',
            'brand' => null,
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), ['name' => 'required|max:150']);

        Brand::create([
            'name' => $data['name'],
            'slug' => Brand::generateSlug($data['name']),
            'description' => self::nullable($request->input('description')),
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
            'meta_title' => self::nullable($request->input('meta_title')),
            'meta_description' => self::nullable($request->input('meta_description')),
        ]);

        Session::flash('success', 'Brand created.');
        $this->redirect('/admin/brands');
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->route('id');
        $brand = Brand::find($id);

        if ($brand === null) {
            Response::abort(404, 'Brand not found.');
        }

        $this->view('admin/brands/form', [
            'pageTitle' => 'Edit Brand | Kymera Collection Admin',
            'brand' => $brand,
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->route('id');
        $brand = Brand::find($id);

        if ($brand === null) {
            Response::abort(404, 'Brand not found.');
        }

        $data = $this->validate($request->all(), ['name' => 'required|max:150']);

        $slug = $brand['slug'];

        if (mb_strtolower($data['name']) !== mb_strtolower($brand['name'])) {
            $slug = Brand::generateSlug($data['name'], $id);
        }

        Brand::update($id, [
            'name' => $data['name'],
            'slug' => $slug,
            'description' => self::nullable($request->input('description')),
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
            'meta_title' => self::nullable($request->input('meta_title')),
            'meta_description' => self::nullable($request->input('meta_description')),
        ]);

        Session::flash('success', 'Brand updated.');
        $this->redirect('/admin/brands');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->route('id');

        try {
            Brand::delete($id);
            Session::flash('success', 'Brand deleted.');
        } catch (PDOException) {
            Session::flash('errors', ['brand' => ['This brand could not be deleted.']]);
        }

        $this->redirect('/admin/brands');
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
