<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Str;
use PHPUnit\Framework\TestCase;

final class StrTest extends TestCase
{
    public function test_slug_lowercases_and_hyphenates(): void
    {
        $this->assertSame('hello-world', Str::slug('Hello World'));
    }

    public function test_slug_strips_punctuation(): void
    {
        // The apostrophe isn't stripped invisibly - it's treated as a
        // separator like any other non-alphanumeric character, same as
        // the space around it.
        $this->assertSame('men-s-leather-wallet', Str::slug("Men's Leather Wallet!"));
    }

    public function test_slug_collapses_repeated_separators(): void
    {
        $this->assertSame('a-b', Str::slug('A   ---   B'));
    }

    public function test_slug_trims_leading_and_trailing_hyphens(): void
    {
        $this->assertSame('milano-leather', Str::slug('--Milano Leather--'));
    }

    public function test_slug_transliterates_accented_characters(): void
    {
        $this->assertSame('cafe-noel', Str::slug('Café Noël'));
    }

    public function test_slug_falls_back_to_item_for_empty_result(): void
    {
        $this->assertSame('item', Str::slug('!!!'));
        $this->assertSame('item', Str::slug(''));
    }
}
