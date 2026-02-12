<?php

/*
 * This file is part of FeatherPanel.
 *
 * Copyright (C) 2025 MythicalSystems Studios
 * Copyright (C) 2025 FeatherPanel Contributors
 * Copyright (C) 2025 Cassian Gherman (aka NaysKutzu)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See the LICENSE file or <https://www.gnu.org/licenses/>.
 */

namespace App\Addons\billingredeem\Chat;

use App\App;
use App\Chat\Database;

/**
 * Redeem Code chat model for managing redemption codes.
 */
class RedeemCode
{
    private static string $table = 'featherpanel_billingredeem_codes';

    /**
     * Get a code by its code string.
     */
    public static function getByCode(string $code): ?array
    {
        if (empty($code)) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE code = :code LIMIT 1');
        $stmt->execute(['code' => $code]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * Get a code by its ID.
     */
    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * Check if a code is valid (not expired, not maxed out).
     */
    public static function isValid(array $code): bool
    {
        if (empty($code)) {
            return false;
        }

        // Check if code has expired
        if (!empty($code['expires_at'])) {
            $expiresAt = strtotime($code['expires_at']);
            if ($expiresAt !== false && $expiresAt < time()) {
                return false;
            }
        }

        // Check if code has reached max uses
        $uses = (int) ($code['uses'] ?? 0);
        $maxUses = (int) ($code['max_uses'] ?? 1);
        if ($maxUses > 0 && $uses >= $maxUses) {
            return false;
        }

        return true;
    }

    /**
     * Increment the uses count for a code.
     *
     * @param \PDO|null $pdo Optional PDO connection to use (for transactions)
     */
    public static function incrementUses(int $codeId, ?\PDO $pdo = null): bool
    {
        if ($codeId <= 0) {
            return false;
        }

        if ($pdo === null) {
            $pdo = Database::getPdoConnection();
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE ' . self::$table . ' SET uses = uses + 1 WHERE id = :id'
            );
            $stmt->execute(['id' => $codeId]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to increment code uses: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Create a new redemption code.
     */
    public static function create(array $data): ?array
    {
        if (empty($data['code']) || !isset($data['amount'])) {
            return null;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (code, amount, max_uses, expires_at) 
                VALUES (:code, :amount, :max_uses, :expires_at)'
            );
            $stmt->execute([
                'code' => $data['code'],
                'amount' => (int) $data['amount'],
                'max_uses' => isset($data['max_uses']) ? (int) $data['max_uses'] : 1,
                'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
            ]);

            return self::getById((int) $pdo->lastInsertId());
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to create redemption code: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Update a redemption code.
     */
    public static function update(int $id, array $data): bool
    {
        if ($id <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $updates = [];
        $params = ['id' => $id];

        if (isset($data['code'])) {
            $updates[] = 'code = :code';
            $params['code'] = $data['code'];
        }
        if (isset($data['amount'])) {
            $updates[] = 'amount = :amount';
            $params['amount'] = (int) $data['amount'];
        }
        if (isset($data['max_uses'])) {
            $updates[] = 'max_uses = :max_uses';
            $params['max_uses'] = (int) $data['max_uses'];
        }
        if (isset($data['expires_at'])) {
            $updates[] = 'expires_at = :expires_at';
            $params['expires_at'] = !empty($data['expires_at']) ? $data['expires_at'] : null;
        }

        if (empty($updates)) {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE ' . self::$table . ' SET ' . implode(', ', $updates) . ' WHERE id = :id'
            );
            $stmt->execute($params);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to update redemption code: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete a redemption code.
     */
    public static function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE id = :id');
            $stmt->execute(['id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete redemption code: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get all codes with pagination.
     */
    public static function getAll(int $limit = 50, int $offset = 0): array
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get total count of codes.
     */
    public static function getCount(): int
    {
        $pdo = Database::getPdoConnection();
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM ' . self::$table);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (int) $result['count'] : 0;
    }
}
