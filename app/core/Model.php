<?php

namespace App\Core;

use PDO;

/**
 * Modelo base con operaciones CRUD genéricas.
 */
abstract class Model
{
    protected PDO $db;
    protected string $table;
    /** Columnas que se permiten en insert/update */
    protected array $fillable = [];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function all(string $order = 'id DESC'): array
    {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY {$order}")->fetchAll();
    }

    public function create(array $data): int
    {
        $data = $this->filter($data);
        $cols = implode(', ', array_keys($data));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        $st = $this->db->prepare("INSERT INTO {$this->table} ({$cols}) VALUES ({$marks})");
        $st->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filter($data);
        if (!$data) {
            return false;
        }
        $set = implode(', ', array_map(fn ($c) => "{$c} = ?", array_keys($data)));
        $st = $this->db->prepare("UPDATE {$this->table} SET {$set} WHERE id = ?");
        return $st->execute([...array_values($data), $id]);
    }

    public function delete(int $id): bool
    {
        $st = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $st->execute([$id]);
    }

    protected function filter(array $data): array
    {
        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetch() ?: null;
    }

    protected function fetchValue(string $sql, array $params = []): mixed
    {
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }
}
