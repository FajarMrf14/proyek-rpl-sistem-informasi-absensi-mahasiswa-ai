<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Ambil seluruh data dari tabel
     */
    public function all(array $columns = ['*'], ?string $orderBy = null): array
    {
        $cols = implode(', ', $columns);
        $sql = "SELECT {$cols} FROM {$this->table}";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Cari data berdasarkan Primary Key ID
     */
    public function find(mixed $id, array $columns = ['*']): ?array
    {
        $cols = implode(', ', $columns);
        $sql = "SELECT {$cols} FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Ambil data berdasarkan kondisi WHERE
     */
    public function where(string $column, mixed $operator, mixed $value = null, array $columns = ['*']): array
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $cols = implode(', ', $columns);
        $sql = "SELECT {$cols} FROM {$this->table} WHERE {$column} {$operator} :val";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['val' => $value]);

        return $stmt->fetchAll();
    }

    /**
     * Ambil record pertama berdasarkan kondisi WHERE
     */
    public function firstWhere(string $column, mixed $operator, mixed $value = null, array $columns = ['*']): ?array
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $cols = implode(', ', $columns);
        $sql = "SELECT {$cols} FROM {$this->table} WHERE {$column} {$operator} :val LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['val' => $value]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Tambah record baru (Insert)
     */
    public function insert(array $data): string|int
    {
        $fields = array_keys($data);
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        return $this->db->lastInsertId();
    }

    /**
     * Perbarui record berdasarkan ID (Update)
     */
    public function update(mixed $id, array $data): bool
    {
        $setClauses = [];
        foreach (array_keys($data) as $field) {
            $setClauses[] = "{$field} = :{$field}";
        }
        $setString = implode(', ', $setClauses);

        $sql = "UPDATE {$this->table} SET {$setString} WHERE {$this->primaryKey} = :_primary_id";
        $stmt = $this->db->prepare($sql);

        $params = $data;
        $params['_primary_id'] = $id;

        return $stmt->execute($params);
    }

    /**
     * Hapus record berdasarkan ID (Delete)
     */
    public function delete(mixed $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Eksekusi custom query dengan prepared statement
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Hitung total baris
     */
    public function count(string $whereClause = '', array $params = []): int
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        if (!empty($whereClause)) {
            $sql .= " WHERE {$whereClause}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();

        return (int)($result['total'] ?? 0);
    }
}
