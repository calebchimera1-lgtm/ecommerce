<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Services\Upload\ImageUploader;
use RuntimeException;

final class BlogPostController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): void
    {
        $page = max(1, (int) $request->query('page', 1));
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category_id' => (string) $request->query('category_id', ''),
            'status' => (string) $request->query('status', ''),
        ];

        $this->view('admin/blog-posts/index', [
            'pageTitle' => 'Blog Posts | Kymera Collection Admin',
            'posts' => BlogPost::paginateAdmin($page, self::PER_PAGE, $filters),
            'categories' => BlogCategory::all('name', 'ASC'),
            'filters' => $filters,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => BlogPost::countAdmin($filters),
        ], 'admin/layouts/app');
    }

    public function create(Request $request): void
    {
        $this->view('admin/blog-posts/form', [
            'pageTitle' => 'New Blog Post | Kymera Collection Admin',
            'post' => null,
            'comments' => [],
            'categories' => BlogCategory::all('name', 'ASC'),
        ], 'admin/layouts/app');
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request->all(), [
            'title' => 'required|max:200',
            'content' => 'required',
        ]);

        $categoryId = self::nullableInt($request->input('category_id'));

        if ($categoryId !== null && BlogCategory::find($categoryId) === null) {
            Session::flash('errors', ['category_id' => ['Selected category does not exist.']]);
            $this->back();
        }

        $isPublished = $request->input('is_published') !== null;

        try {
            $featuredImage = ImageUploader::store($request->file('featured_image') ?? [], 'blog');
        } catch (RuntimeException $e) {
            Session::flash('errors', ['featured_image' => [$e->getMessage()]]);
            $this->back();
        }

        $postId = BlogPost::create([
            'category_id' => $categoryId,
            'author_id' => Auth::id(),
            'title' => $data['title'],
            'slug' => BlogPost::generateSlug($data['title']),
            'excerpt' => self::nullable($request->input('excerpt')),
            'content' => $data['content'],
            'featured_image' => $featuredImage,
            'is_published' => $isPublished ? 1 : 0,
            'published_at' => $isPublished ? date('Y-m-d H:i:s') : null,
            'meta_title' => self::nullable($request->input('meta_title')),
            'meta_description' => self::nullable($request->input('meta_description')),
        ]);

        AuditLog::record(Auth::id(), 'blog_post.created', 'blog_post', $postId, null, [
            'title' => $data['title'],
            'is_published' => $isPublished,
        ]);

        Session::flash('success', 'Blog post created.');
        $this->redirect('/admin/blog-posts/' . $postId . '/edit');
    }

    public function edit(Request $request): void
    {
        $post = $this->loadPost($request);

        $this->view('admin/blog-posts/form', [
            'pageTitle' => 'Edit Blog Post | Kymera Collection Admin',
            'post' => $post,
            'comments' => BlogComment::forPostAdmin((int) $post['id']),
            'categories' => BlogCategory::all('name', 'ASC'),
        ], 'admin/layouts/app');
    }

    public function update(Request $request): void
    {
        $post = $this->loadPost($request);

        $data = $this->validate($request->all(), [
            'title' => 'required|max:200',
            'content' => 'required',
        ]);

        $categoryId = self::nullableInt($request->input('category_id'));

        if ($categoryId !== null && BlogCategory::find($categoryId) === null) {
            Session::flash('errors', ['category_id' => ['Selected category does not exist.']]);
            $this->back();
        }

        $wasPublished = (int) $post['is_published'] === 1;
        $isPublished = $request->input('is_published') !== null;

        try {
            $featuredImage = ImageUploader::store($request->file('featured_image') ?? [], 'blog');
        } catch (RuntimeException $e) {
            Session::flash('errors', ['featured_image' => [$e->getMessage()]]);
            $this->back();
        }

        if ($featuredImage !== null && $post['featured_image'] !== null) {
            ImageUploader::delete($post['featured_image']);
        }

        $slug = $post['slug'];

        if (mb_strtolower($data['title']) !== mb_strtolower($post['title'])) {
            $slug = BlogPost::generateSlug($data['title'], (int) $post['id']);
        }

        BlogPost::update((int) $post['id'], [
            'category_id' => $categoryId,
            'title' => $data['title'],
            'slug' => $slug,
            'excerpt' => self::nullable($request->input('excerpt')),
            'content' => $data['content'],
            'featured_image' => $featuredImage ?? $post['featured_image'],
            'is_published' => $isPublished ? 1 : 0,
            // Publishing for the first time stamps published_at now;
            // once set, later edits (including unpublishing) don't
            // move it, so "published_at" keeps meaning "first went
            // live" rather than "last saved".
            'published_at' => (!$wasPublished && $isPublished) ? date('Y-m-d H:i:s') : $post['published_at'],
            'meta_title' => self::nullable($request->input('meta_title')),
            'meta_description' => self::nullable($request->input('meta_description')),
        ]);

        if ($wasPublished !== $isPublished) {
            AuditLog::record(Auth::id(), $isPublished ? 'blog_post.published' : 'blog_post.unpublished', 'blog_post', (int) $post['id'], [
                'is_published' => $wasPublished,
            ], [
                'is_published' => $isPublished,
            ]);
        }

        Session::flash('success', 'Blog post updated.');
        $this->redirect('/admin/blog-posts/' . (int) $post['id'] . '/edit');
    }

    public function destroy(Request $request): void
    {
        $post = $this->loadPost($request);

        if ($post['featured_image'] !== null) {
            ImageUploader::delete($post['featured_image']);
        }

        BlogPost::delete((int) $post['id']);

        AuditLog::record(Auth::id(), 'blog_post.deleted', 'blog_post', (int) $post['id'], [
            'title' => $post['title'],
        ], null);

        Session::flash('success', 'Blog post deleted.');
        $this->redirect('/admin/blog-posts');
    }

    public function approveComment(Request $request): void
    {
        $post = $this->loadPost($request);
        $comment = $this->loadComment($request, (int) $post['id']);

        BlogComment::approve((int) $comment['id']);

        AuditLog::record(Auth::id(), 'blog_comment.approved', 'blog_comment', (int) $comment['id'], null, null);

        Session::flash('success', 'Comment approved.');
        $this->redirect('/admin/blog-posts/' . (int) $post['id'] . '/edit');
    }

    public function destroyComment(Request $request): void
    {
        $post = $this->loadPost($request);
        $comment = $this->loadComment($request, (int) $post['id']);

        BlogComment::delete((int) $comment['id']);

        AuditLog::record(Auth::id(), 'blog_comment.deleted', 'blog_comment', (int) $comment['id'], null, null);

        Session::flash('success', 'Comment removed.');
        $this->redirect('/admin/blog-posts/' . (int) $post['id'] . '/edit');
    }

    private function loadPost(Request $request): array
    {
        $id = (int) $request->route('id');
        $post = BlogPost::findWithRelations($id);

        if ($post === null) {
            Response::abort(404, 'Blog post not found.');
        }

        return $post;
    }

    private function loadComment(Request $request, int $postId): array
    {
        $commentId = (int) $request->route('commentId');
        $comment = BlogComment::find($commentId);

        if ($comment === null || (int) $comment['post_id'] !== $postId) {
            Response::abort(404, 'Comment not found.');
        }

        return $comment;
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
