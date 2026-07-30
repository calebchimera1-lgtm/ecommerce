<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Product;
use App\Models\Wishlist;

final class WishlistController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('customer/wishlist/index', [
            'pageTitle' => 'Your Wishlist | Kymera Collection',
            'items' => Wishlist::forUser((int) Auth::id()),
        ], 'customer/layouts/site');
    }

    public function toggle(Request $request): void
    {
        $slug = (string) $request->input('slug');
        $product = Product::findActiveBySlug($slug);

        if ($product === null) {
            Response::abort(404, 'Product not found.');
        }

        $added = Wishlist::toggle((int) Auth::id(), (int) $product['id']);

        Session::flash('success', $added ? 'Added to your wishlist.' : 'Removed from your wishlist.');
        $this->redirect('/product/' . $slug);
    }
}
