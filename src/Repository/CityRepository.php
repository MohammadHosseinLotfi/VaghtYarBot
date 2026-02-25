<?php

namespace App\Repository;

class CityRepository
{
    public function __construct(private \PDO $db) {}

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id,
                   c.name_fa                 AS name,
                   c.name_en,
                   c.lat                     AS latitude,
                   c.lon                     AS longitude,
                   COALESCE(p.name_fa, '')   AS province_name
            FROM   cities     c
            LEFT JOIN provinces p ON p.id = c.p_id
            WHERE  c.name_fa = ?
            LIMIT  1
        ");
        $stmt->execute([$this->normalize($name)]);
        return $stmt->fetch() ?: null;
    }

    public function findAllByExactName(string $name): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id,
                c.name_fa                AS name,
                c.name_en,
                c.lat                    AS latitude,
                c.lon                    AS longitude,
                COALESCE(p.name_fa, '') AS province_name
            FROM   cities    c
            LEFT JOIN provinces p ON p.id = c.p_id
            WHERE  c.name_normalized = ?
            LIMIT  10
        ");
        $stmt->execute([$this->normalize($name)]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id,
                   c.name_fa                 AS name,
                   c.name_en,
                   c.lat                     AS latitude,
                   c.lon                     AS longitude,
                   COALESCE(p.name_fa, '')   AS province_name
            FROM   cities     c
            LEFT JOIN provinces p ON p.id = c.p_id
            WHERE  c.id = ?
            LIMIT  1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function searchByName(string $name): array
    {
        $keyword = '%' . $this->normalize($name) . '%';
        $stmt = $this->db->prepare("
            SELECT c.id,
                c.name_fa                AS name,
                c.name_en,
                c.lat                    AS latitude,
                c.lon                    AS longitude,
                COALESCE(p.name_fa, '') AS province_name
            FROM   cities    c
            LEFT JOIN provinces p ON p.id = c.p_id
            WHERE  c.name_normalized LIKE ?
            OR  c.name_en        LIKE ?
            LIMIT  8
        ");
        $stmt->execute([$keyword, $keyword]);
        return $stmt->fetchAll();
    }

    public function findCapitalByProvinceName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id,
                c.name_fa  AS name,
                c.name_en,
                c.lat      AS latitude,
                c.lon      AS longitude,
                p.name_fa  AS province_name
            FROM   cities    c
            JOIN   provinces p ON p.id = c.p_id
            WHERE  p.name_normalized = ?
            AND  c.is_capital = 1
            LIMIT  1
        ");
        $stmt->execute([$this->normalize($name)]);
        return $stmt->fetch() ?: null;
    }

    public function findNearest(float $lat, float $lng): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id,
                   c.name_fa                 AS name,
                   c.name_en,
                   c.lat                     AS latitude,
                   c.lon                     AS longitude,
                   COALESCE(p.name_fa, '')   AS province_name,
                   ROUND(
                       6371 * ACOS(
                           COS(RADIANS(:lat))  * COS(RADIANS(c.lat))
                           * COS(RADIANS(c.lon) - RADIANS(:lng))
                           + SIN(RADIANS(:lat)) * SIN(RADIANS(c.lat))
                       ), 1
                   ) AS distance
            FROM   cities     c
            LEFT JOIN provinces p ON p.id = c.p_id
            ORDER  BY distance ASC
            LIMIT  1
        ");
        $stmt->execute(['lat' => $lat, 'lng' => $lng]);
        return $stmt->fetch() ?: null;
    }

    private function normalize(string $s): string
    {
        $s = str_replace("\u{200C}", ' ', $s);
        $s = strtr($s, [
            'آ' => 'ا', 'أ' => 'ا', 'إ' => 'ا',
            'ي' => 'ی', 'ى' => 'ی',
            'ك' => 'ک',
            'ة' => 'ه',
        ]);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
