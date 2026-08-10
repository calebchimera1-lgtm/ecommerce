<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Currency;
use Tests\TestCase;

/**
 * Exercises the seeded currencies rows from database/kymera_collection.sql
 * (USD/KES/GBP/EUR) - a small, manually-maintained reference table, same
 * reasoning as TaxRateTest for reading real seed data over factories.
 */
final class CurrencyTest extends TestCase
{
    public function test_active_lists_every_seeded_currency(): void
    {
        $codes = array_column(Currency::active(), 'code');

        foreach (['USD', 'KES', 'GBP', 'EUR'] as $expected) {
            $this->assertContains($expected, $codes);
        }
    }

    public function test_default_currency_is_usd_with_a_rate_of_one(): void
    {
        $default = Currency::defaultCurrency();

        $this->assertSame('USD', $default['code']);
        $this->assertSame('1.000000', $default['exchange_rate']);
    }

    public function test_find_returns_the_stored_exchange_rate(): void
    {
        $kes = Currency::find('KES');

        $this->assertNotNull($kes);
        $this->assertSame('129.500000', $kes['exchange_rate']);
    }

    public function test_an_inactive_currency_is_excluded_from_active(): void
    {
        // Currency's primary key is `code`, a string, not an
        // auto-increment id - Model::create() returns lastInsertId(),
        // which MySQL never populates for a table with no AUTO_INCREMENT
        // column, so the code itself (not the return value) is what
        // identifies the row afterward.
        Currency::create([
            'code' => 'ZZZ', 'name' => 'Test Currency', 'symbol' => 'Z',
            'exchange_rate' => '2.000000', 'is_active' => 0, 'is_default' => 0,
        ]);

        $codes = array_column(Currency::active(), 'code');

        $this->assertNotContains('ZZZ', $codes);
        $this->assertNotNull(Currency::find('ZZZ'));
    }
}
