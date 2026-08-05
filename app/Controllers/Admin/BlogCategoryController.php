<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\BlogCategory;
use PDOException;

final class BlogCategoryController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('admin/blog-categories/index', [
            'pageTitle' => 'Blog Categories | Kymera Collection Admin',
            'categories' => BlogCategory::all('name', 'ASC'),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/blog-categories/form', [
            'pageTitle' => 'New Blog Category | Kymera Collection Admin',
            'category' => null,
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), ['name' => 'required|max:100']);

        BlogCategory::create([
            'name' => $data['name'],
            'slug' => BlogCategory::generateSlug($data['name']),
        ]);

        Session::flash('success', 'Blog category created.');
        $this->redirect('/admin/blog-categories');
    }

    public function edit(Request $request): void
    {
        $category = $this->loadCategory($request);

        $this->view('admin/blog-categories/form', [
            'pageTitle' => 'Edit Blog Category | Kymera Collection Admin',
            'category' => $category,
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $category = $this->loadCategory($request);
        $data = $this->validate($request->all(), ['name' => 'required|max:100']);

        $slug = $category['slug'];

        if (mb_strtolower($data['name']) !== mb_strtolower($category['name'])) {
            $slug = BlogCategory::generateSlug($data['name'], (int) $category['id']);
        }

        BlogCategory::update((int) $category['id'], [
            'name' => $data['name'],
            'slug' => $slug,
        ]);

        Session::flash('success', 'Blog category updated.');
        $this->redirect('/admin/blog-categories');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->route('id');

        try {
            BlogCategory::delete($id);
            Session::flash('success', 'Blog category deleted.');
        } catch (PDOException) {
            // blog_posts.category_id is ON DELETE SET NULL, so this
            // shouldn't actually throw for "still has posts" - kept
            // for the same defensive reason Brand's identical delete
            // path does, in case of an unexpected DB-level error.
            Session::flash('errors', ['category' => ['This category could not be deleted.']]);
        }

        $this->redirect('/admin/blog-categories');
    }

    private function loadCategory(Request $request): array
    {
        $id = (int) $request->route('id');
        $category = BlogCategory::find($id);

        if ($category === null) {
            Response::abort(404, 'Blog category not found.');
        }

        return $category;
    }
}
