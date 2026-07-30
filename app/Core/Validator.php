<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight rule-based input validator.
 *
 * Supported rules: required, email, min:N, max:N, numeric, integer,
 * alpha_num, confirmed, in:a,b,c, unique:table,column[,ignoreId]
 */
final class Validator
{
    private array $errors = [];
    private array $validated = [];

    public function __construct(private readonly array $data, private readonly array $rules)
    {
        $this->run();
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule): void
    {
        [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

        $fails = match ($name) {
            'required' => $value === null || trim((string) $value) === '',
            'email' => $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false,
            'numeric' => $value !== null && $value !== '' && !is_numeric($value),
            'integer' => $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false,
            'alpha_num' => $value !== null && $value !== '' && !ctype_alnum((string) $value),
            'min' => $value !== null && $value !== '' && (is_numeric($value) ? (float) $value < (float) $param : mb_strlen((string) $value) < (int) $param),
            'max' => $value !== null && $value !== '' && (is_numeric($value) ? (float) $value > (float) $param : mb_strlen((string) $value) > (int) $param),
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) !== $value,
            'in' => $value !== null && $value !== '' && !in_array($value, explode(',', (string) $param), true),
            'unique' => $this->failsUnique($value, $param),
            default => false,
        };

        if ($fails) {
            $this->errors[$field][] = $this->message($field, $name, $param);
        }
    }

    private function failsUnique(mixed $value, ?string $param): bool
    {
        if ($value === null || $value === '' || $param === null) {
            return false;
        }

        [$table, $column, $ignoreId] = array_pad(explode(',', $param), 3, null);
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);

        $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE {$column} = :value";
        $bindings = ['value' => $value];

        if ($ignoreId !== null) {
            $sql .= ' AND id != :ignore_id';
            $bindings['ignore_id'] = $ignoreId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'] > 0;
    }

    private function message(string $field, string $rule, ?string $param): string
    {
        $label = ucwords(str_replace('_', ' ', $field));

        return match ($rule) {
            'required' => "{$label} is required.",
            'email' => "{$label} must be a valid email address.",
            'numeric' => "{$label} must be a number.",
            'integer' => "{$label} must be an integer.",
            'alpha_num' => "{$label} must only contain letters and numbers.",
            'min' => "{$label} must be at least {$param}.",
            'max' => "{$label} must not exceed {$param}.",
            'confirmed' => "{$label} confirmation does not match.",
            'in' => "{$label} is not a valid selection.",
            'unique' => "{$label} is already taken.",
            default => "{$label} is invalid.",
        };
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }
}
