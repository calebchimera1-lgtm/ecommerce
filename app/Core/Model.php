<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Base Active-Record-style model. All queries use PDO prepared
 * statements with bound parameters - never raw string concatenation
 * of user-supplied values.
 */
abstract class Model
{
    protected static string $table;
    protected static string $primaryKey = 'id';

    protected static function db(): PDO
    {
        return Database::connection();
    }

    public static function find(int|string $id): ?array
    {
        $stmt = self::db()->prepare(
            sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', static::$table, static::$primaryKey)
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        $stmt = self::db()->prepare(
            sprintf('SELECT * FROM %s WHERE %s = :value LIMIT 1', static::$table, self::assertSafeColumn($column))
        );
        $stmt->execute(['value' => $value]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public static function all(string $orderBy = '', string $direction = 'ASC'): array
    {
        $sql = sprintf('SELECT * FROM %s', static::$table);

        if ($orderBy !== '') {
            $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
            $sql .= sprintf(' ORDER BY %s %s', self::assertSafeColumn($orderBy), $direction);
        }

        return self::db()->query($sql)->fetchAll();
    }

    public static function paginate(int $page = 1, int $perPage = 20, string $where = '', array $bindings = [], string $orderBy = ''): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $sql = sprintf('SELECT * FROM %s', static::$table);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy;
        }
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = self::db()->prepare($sql);
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function count(string $where = '', array $bindings = []): int
    {
        $sql = sprintf('SELECT COUNT(*) AS total FROM %s', static::$table);
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $stmt = self::db()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'];
    }

    public static function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $c): string => ':' . $c, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            static::$table,
            implode(', ', array_map([self::class, 'assertSafeColumn'], $columns)),
            implode(', ', $placeholders)
        );

        $stmt = self::db()->prepare($sql);
        $stmt->execute($data);

        return (int) self::db()->lastInsertId();
    }

    public static function update(int|string $id, array $data): bool
    {
        $assignments = implode(', ', array_map(
            static fn (string $c): string => sprintf('%s = :%s', self::assertSafeColumn($c), $c),
            array_keys($data)
        ));

        $sql = sprintf('UPDATE %s SET %s WHERE %s = :id', static::$table, $assignments, static::$primaryKey);

        $data['id'] = $id;
        $stmt = self::db()->prepare($sql);

        return $stmt->execute($data);
    }

    public static function delete(int|string $id): bool
    {
        $stmt = self::db()->prepare(
            sprintf('DELETE FROM %s WHERE %s = :id', static::$table, static::$primaryKey)
        );

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Append -2, -3, ... to $base until it's unique in this table's
     * `slug` column. Assumes the model has a `slug` column - true for
     * every model that currently uses this helper (categories, brands,
     * products).
     */
    protected static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = $base;
        $suffix = 2;

        while (self::slugTaken($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function slugTaken(string $slug, ?int $ignoreId): bool
    {
        $sql = sprintf('SELECT COUNT(*) AS total FROM %s WHERE slug = :slug', static::$table);
        $bindings = ['slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= sprintf(' AND %s != :ignore_id', static::$primaryKey);
            $bindings['ignore_id'] = $ignoreId;
        }

        $stmt = self::db()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetch()['total'] > 0;
    }

    /**
     * Allow only safe identifier characters in dynamically built column
     * names, since PDO cannot bind identifiers as parameters.
     */
    protected static function assertSafeColumn(string $column): string
    {
        if (!preg_match('/^[a-zA-Z0-9_.]+$/', $column)) {
            throw new \InvalidArgumentException("Unsafe column name: {$column}");
        }

        return $column;
    }
}
