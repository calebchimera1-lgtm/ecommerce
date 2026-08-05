<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;

final class BlogController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'search' => trim((string) $request->query('q', '')),
            'category_id' => (string) $request->query('category_id', ''),
        ];

        $this->view('customer/blog/index', [
            'pageTitle' => 'Journal | Kymera Collection',
            'metaDescription' => 'Style notes, craftsmanship stories, and news from Kymera Collection.',
            'posts' => BlogPost::paginatePublic($page, self::PER_PAGE, $filters),
            'categories' => BlogCategory::all('name', 'ASC'),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => BlogPost::countPublic($filters),
        ], 'customer/layouts/site');
    }

    public function category(Request $request): void
    {
        $slug = (string) $request->route('slug');
        $category = BlogCategory::findBySlug($slug);

        if ($category === null) {
            Response::abort(404, 'Blog category not found.');
        }

        $page = max(1, (int) $request->query('page', 1));
        $filters = ['category_id' => (string) $category['id']];

        $this->view('customer/blog/index', [
            'pageTitle' => $category['name'] . ' | Kymera Collection Journal',
            'posts' => BlogPost::paginatePublic($page, self::PER_PAGE, $filters),
            'categories' => BlogCategory::all('name', 'ASC'),
            'filters' => ['search' => '', 'category_id' => (string) $category['id']],
            'activeCategory' => $category,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => BlogPost::countPublic($filters),
        ], 'customer/layouts/site');
    }

    public function show(Request $request): void
    {
        $slug = (string) $request->route('slug');
        $post = BlogPost::findPublishedBySlug($slug);

        if ($post === null) {
            Response::abort(404, 'Post not found.');
        }

        $this->view('customer/blog/show', [
            'pageTitle' => ($post['meta_title'] ?? $post['title']) . ' | Kymera Collection Journal',
            'metaDescription' => $post['meta_description'] ?? $post['excerpt'] ?? mb_substr(strip_tags($post['content']), 0, 160),
            'post' => $post,
            'comments' => BlogComment::approvedForPost((int) $post['id']),
            'relatedPosts' => BlogPost::recentPublishedExcluding((int) $post['id'], 3),
            'isLoggedIn' => Auth::check(),
        ], 'customer/layouts/site');
    }

    public function storeComment(Request $request): void
    {
        $slug = (string) $request->route('slug');
        $post = BlogPost::findPublishedBySlug($slug);

        if ($post === null) {
            Response::abort(404, 'Post not found.');
        }

        $user = Auth::user();

        if ($user === null) {
            Response::redirect('/login');
        }

        $data = $this->validate($request->all(), [
            'comment' => 'required|max:2000',
        ]);

        BlogComment::create([
            'post_id' => $post['id'],
            'user_id' => $user['id'],
            'comment' => $data['comment'],
            'is_approved' => 0,
        ]);

        Session::flash('success', 'Thank you! Your comment has been submitted and will appear once approved.');
        $this->redirect('/blog/' . $slug);
    }
}
