<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Request;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

final class SitemapController extends Controller
{
    private const STATIC_PATHS = [
        '/', '/shop', '/about', '/contact', '/faqs', '/privacy-policy', '/terms',
        '/shipping-returns', '/blog',
    ];

    public function index(Request $request): never
    {
        $urls = [];

        foreach (self::STATIC_PATHS as $path) {
            $urls[] = ['loc' => url($path), 'lastmod' => null];
        }

        foreach (Category::activeOrdered() as $category) {
            $urls[] = ['loc' => url('/shop/category/' . $category['slug']), 'lastmod' => null];
        }

        foreach (Brand::activeOrdered() as $brand) {
            $urls[] = ['loc' => url('/shop/brand/' . $brand['slug']), 'lastmod' => null];
        }

        foreach (Product::allActiveForSitemap() as $product) {
            $urls[] = ['loc' => url('/product/' . $product['slug']), 'lastmod' => self::toDate($product['updated_at'])];
        }

        foreach (BlogPost::allPublishedForSitemap() as $post) {
            $urls[] = ['loc' => url('/blog/' . $post['slug']), 'lastmod' => self::toDate($post['updated_at'])];
        }

        http_response_code(200);
        header('Content-Type: application/xml; charset=utf-8');
        echo self::renderXml($urls);
        exit;
    }

    /**
     * robots.txt is served dynamically (not a static public/ file) so
     * its Sitemap: directive can use the deployment's real APP_URL
     * rather than a value hardcoded for one specific environment.
     */
    public function robots(Request $request): never
    {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\n";
        echo "Disallow: /admin\n";
        echo "Disallow: /account\n";
        echo "Disallow: /cart\n";
        echo "Disallow: /checkout\n";
        echo "Allow: /\n\n";
        echo 'Sitemap: ' . url('/sitemap.xml') . "\n";
        exit;
    }

    private static function toDate(string $timestamp): string
    {
        return date('Y-m-d', strtotime($timestamp));
    }

    /**
     * @param array<int,array{loc:string,lastmod:?string}> $urls
     */
    private static function renderXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . "\n";

            if ($url['lastmod'] !== null) {
                $xml .= '    <lastmod>' . $url['lastmod'] . '</lastmod>' . "\n";
            }

            $xml .= '  </url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }
}
