<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Testimonial;
use App\Services\Upload\ImageUploader;
use RuntimeException;

/**
 * Testimonials are marketing content shown on the storefront homepage
 * (Module 5), the same trust level as blog content - managed under
 * the existing 'blog.manage' permission rather than a new dedicated
 * one, since both are public-facing copy an admin curates, not a
 * distinct financial/account-level action the way Module 12's
 * expenses.manage was.
 */
final class TestimonialController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('admin/testimonials/index', [
            'pageTitle' => 'Testimonials | Kymera Collection Admin',
            'testimonials' => Testimonial::all('sort_order', 'ASC'),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/testimonials/form', [
            'pageTitle' => 'New Testimonial | Kymera Collection Admin',
            'testimonial' => null,
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'customer_name' => 'required|max:150',
            'message' => 'required|max:500',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        try {
            $photo = ImageUploader::store($request->file('customer_photo') ?? [], 'testimonials');
        } catch (RuntimeException $e) {
            Session::flash('errors', ['customer_photo' => [$e->getMessage()]]);
            $this->back();
        }

        Testimonial::create([
            'customer_name' => $data['customer_name'],
            'customer_photo' => $photo,
            'rating' => (int) $data['rating'],
            'message' => $data['message'],
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);

        Session::flash('success', 'Testimonial created.');
        $this->redirect('/admin/testimonials');
    }

    public function edit(Request $request): void
    {
        $testimonial = $this->loadTestimonial($request);

        $this->view('admin/testimonials/form', [
            'pageTitle' => 'Edit Testimonial | Kymera Collection Admin',
            'testimonial' => $testimonial,
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $testimonial = $this->loadTestimonial($request);

        $data = $this->validate($request->all(), [
            'customer_name' => 'required|max:150',
            'message' => 'required|max:500',
            'rating' => 'required|integer|min:1|max:5',
        ]);

        try {
            $photo = ImageUploader::store($request->file('customer_photo') ?? [], 'testimonials');
        } catch (RuntimeException $e) {
            Session::flash('errors', ['customer_photo' => [$e->getMessage()]]);
            $this->back();
        }

        if ($photo !== null && $testimonial['customer_photo'] !== null) {
            ImageUploader::delete($testimonial['customer_photo']);
        }

        Testimonial::update((int) $testimonial['id'], [
            'customer_name' => $data['customer_name'],
            'customer_photo' => $photo ?? $testimonial['customer_photo'],
            'rating' => (int) $data['rating'],
            'message' => $data['message'],
            'is_active' => $request->input('is_active') !== null ? 1 : 0,
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);

        Session::flash('success', 'Testimonial updated.');
        $this->redirect('/admin/testimonials');
    }

    public function destroy(Request $request): void
    {
        $testimonial = $this->loadTestimonial($request);

        if ($testimonial['customer_photo'] !== null) {
            ImageUploader::delete($testimonial['customer_photo']);
        }

        Testimonial::delete((int) $testimonial['id']);

        Session::flash('success', 'Testimonial deleted.');
        $this->redirect('/admin/testimonials');
    }

    private function loadTestimonial(Request $request): array
    {
        $id = (int) $request->route('id');
        $testimonial = Testimonial::find($id);

        if ($testimonial === null) {
            Response::abort(404, 'Testimonial not found.');
        }

        return $testimonial;
    }
}
