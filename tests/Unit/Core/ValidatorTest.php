<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Validator;
use Tests\Support\Factory;
use Tests\TestCase;

final class ValidatorTest extends TestCase
{
    public function test_required_rejects_empty_and_whitespace(): void
    {
        $v = new Validator(['name' => '   '], ['name' => 'required']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('name', $v->errors());
    }

    public function test_required_accepts_non_empty(): void
    {
        $v = new Validator(['name' => 'Kymera'], ['name' => 'required']);
        $this->assertTrue($v->passes());
    }

    public function test_email_rejects_invalid_address(): void
    {
        $v = new Validator(['email' => 'not-an-email'], ['email' => 'email']);
        $this->assertTrue($v->fails());
    }

    public function test_email_accepts_valid_address(): void
    {
        $v = new Validator(['email' => 'jane@example.com'], ['email' => 'email']);
        $this->assertTrue($v->passes());
    }

    public function test_min_and_max_measure_string_length_by_default(): void
    {
        $v = new Validator(['password' => '1234567'], ['password' => 'min:8']);
        $this->assertTrue($v->fails(), 'A 7-char value should fail min:8 as a length check.');

        $v = new Validator(['password' => '12345678'], ['password' => 'min:8']);
        $this->assertTrue($v->passes());
    }

    public function test_min_and_max_measure_numeric_range_when_field_is_numeric(): void
    {
        // "1234567" is a 7-digit string but numerically 1234567 >= 8,
        // so declaring the field numeric must switch min/max to range
        // comparison instead of string length.
        $v = new Validator(['stock' => '1234567'], ['stock' => 'numeric|min:8']);
        $this->assertTrue($v->passes());

        $v = new Validator(['stock' => '3'], ['stock' => 'integer|min:8']);
        $this->assertTrue($v->fails());
    }

    public function test_confirmed_requires_matching_confirmation_field(): void
    {
        $v = new Validator(
            ['password' => 'secret123', 'password_confirmation' => 'different'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->fails());

        $v = new Validator(
            ['password' => 'secret123', 'password_confirmation' => 'secret123'],
            ['password' => 'confirmed']
        );
        $this->assertTrue($v->passes());
    }

    public function test_in_restricts_to_an_allowed_set(): void
    {
        $v = new Validator(['status' => 'archived'], ['status' => 'in:pending,approved,rejected']);
        $this->assertTrue($v->fails());

        $v = new Validator(['status' => 'approved'], ['status' => 'in:pending,approved,rejected']);
        $this->assertTrue($v->passes());
    }

    public function test_unique_rejects_an_existing_value_and_accepts_a_fresh_one(): void
    {
        $customer = Factory::customer();

        $v = new Validator(['email' => $customer['email']], ['email' => 'unique:users,email']);
        $this->assertTrue($v->fails(), 'An already-registered email should fail unique.');

        $v = new Validator(['email' => 'brand-new@example.test'], ['email' => 'unique:users,email']);
        $this->assertTrue($v->passes());
    }

    public function test_unique_ignores_the_given_id(): void
    {
        $customer = Factory::customer();

        // Simulates an edit form: the record's own row shouldn't fail
        // uniqueness against itself.
        $v = new Validator(
            ['email' => $customer['email']],
            ['email' => 'unique:users,email,' . $customer['id']]
        );
        $this->assertTrue($v->passes());
    }

    public function test_validated_only_contains_fields_that_passed(): void
    {
        $v = new Validator(
            ['name' => 'Kymera', 'email' => 'bad-email'],
            ['name' => 'required', 'email' => 'email']
        );

        $validated = $v->validated();
        $this->assertArrayHasKey('name', $validated);
        $this->assertArrayNotHasKey('email', $validated);
    }
}
