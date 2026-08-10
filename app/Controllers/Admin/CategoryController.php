<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Category;
use PDOException;

final class CategoryController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('admin/categories/index', [
            'pageTitle' => 'Categories | Kymera Collection Admin',
            'categories' => Category::tree(),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/categories/form', [
            'pageTitle' => 'New Category | Kymera Collection Admin',
            'category' => null,
            'parentOptions' => Category::tree(),
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), ['name' => 'required|max:150']);

        $parentId = self::nullableInt($request->input('parent_id'));

        if ($parentId !== null && Category::find($parentId) === null) {
            Session::flash('errors', ['parent_id' => ['Selected parent category does not exist.']]);
            $this->back();
        }

        Category::create([
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => Category::generateSlug($data['name']),
            'description' => self::nullable($request->input('description')),
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
            'sort_order' => self::nullableInt($request->input('sort_order')) ?? 0,
            'meta_title' => self::nullable($request->input('meta_title')),
            'meta_description' => self::nullable($request->input('meta_description')),
        ]);

        Category::invalidateCache();
        Session::flash('success', 'Category created.');
        $this->redirect('/admin/categories');
    }

    public function edit(Request $request): void
    {
        $id = (int) $request->route('id');
        $category = Category::find($id);

        if ($category === null) {
            Response::abort(404, 'Category not found.');
        }

        $excluded = Category::selfAndDescendantIds($id);
        $parentOptions = array_values(array_filter(
            Category::tree(),
            static fn (array $row): bool => !in_array((int) $row['id'], $excluded, true)
        ));

        $this->view('admin/categories/form', [
            'pageTitle' => 'Edit Category | Kymera Collection Admin',
            'category' => $category,
            'parentOptions' => $parentOptions,
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->route('id');
        $category = Category::find($id);

        if ($category === null) {
            Response::abort(404, 'Category not found.');
        }

        $data = $this->validate($request->all(), ['name' => 'required|max:150']);

        $parentId = self::nullableInt($request->input('parent_id'));

        if ($parentId !== null) {
            if (in_array($parentId, Category::selfAndDescendantIds($id), true)) {
                Session::flash('errors', ['parent_id' => ['A category cannot be its own parent or descendant.']]);
                $this->back();
            }

            if (Category::find($parentId) === null) {
                Session::flash('errors', ['parent_id' => ['Selected parent category does not exist.']]);
                $this->back();
            }
        }

        $slug = $category['slug'];

        if (mb_strtolower($data['name']) !== mb_strtolower($category['name'])) {
            $slug = Category::generateSlug($data['name'], $id);
        }

        Category::update($id, [
            'parent_id' => $parentId,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => self::nullable($request->input('description')),
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
            'sort_order' => self::nullableInt($request->input('sort_order')) ?? 0,
            'meta_title' => self::nullable($request->input('meta_title')),
            'meta_description' => self::nullable($request->input('meta_description')),
        ]);

        Category::invalidateCache();
        Session::flash('success', 'Category updated.');
        $this->redirect('/admin/categories');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->route('id');

        try {
            Category::delete($id);
            Category::invalidateCache();
            Session::flash('success', 'Category deleted.');
        } catch (PDOException) {
            Session::flash('errors', ['category' => ['This category cannot be deleted while it still has products assigned to it.']]);
        }

        $this->redirect('/admin/categories');
    }

    private static function nullable(mixed $value): ?string
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
