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
     * Get codes with pagination, optionally filtered by reward type and search.
     *
     * @return array{data: list<array<string, mixed>>, total: int}
     */
    public static function getPaginatedByRewardType(
        string $rewardType,
        int $page = 1,
        int $limit = 20,
        string $search = '',
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;
        $pdo = Database::getPdoConnection();
        $where = 'reward_type = :reward_type';
        $params = ['reward_type' => $rewardType];

        if ($search !== '') {
            $where .= ' AND code LIKE :search';
            $params['search'] = '%' . strtoupper(trim($search)) . '%';
        }

        $countStmt = $pdo->prepare('SELECT COUNT(*) AS count FROM ' . self::$table . ' WHERE ' . $where);
        $countStmt->execute($params);
        $total = (int) ($countStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);

        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' WHERE ' . $where . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset',
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'total' => $total,
        ];
    }

    /**
     * Get a code by its code string (case-insensitive).
     */
    public static function getByCode(string $code): ?array
    {
        if (empty($code)) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare('SELECT * FROM ' . self::$table . ' WHERE UPPER(code) = UPPER(:code) LIMIT 1');
        $stmt->execute(['code' => trim($code)]);
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
                'INSERT INTO ' . self::$table . ' (code, amount, max_uses, expires_at, reward_type, plan_id, free_period_days, discount_percent, discount_credits, coupon_scope) 
                VALUES (:code, :amount, :max_uses, :expires_at, :reward_type, :plan_id, :free_period_days, :discount_percent, :discount_credits, :coupon_scope)'
            );
            $stmt->execute([
                'code' => strtoupper(trim((string) $data['code'])),
                'amount' => (int) $data['amount'],
                'max_uses' => isset($data['max_uses']) ? (int) $data['max_uses'] : 1,
                'expires_at' => !empty($data['expires_at']) ? $data['expires_at'] : null,
                'reward_type' => self::normalizeRewardType($data['reward_type'] ?? 'credits'),
                'plan_id' => isset($data['plan_id']) && (int) $data['plan_id'] > 0 ? (int) $data['plan_id'] : null,
                'free_period_days' => isset($data['free_period_days']) && (int) $data['free_period_days'] > 0 ? (int) $data['free_period_days'] : null,
                'discount_percent' => isset($data['discount_percent']) ? self::normalizePercent($data['discount_percent']) : null,
                'discount_credits' => isset($data['discount_credits']) ? max(0, (int) $data['discount_credits']) : null,
                'coupon_scope' => self::normalizeCouponScope($data['coupon_scope'] ?? null),
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
            $params['code'] = strtoupper(trim((string) $data['code']));
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
        if (isset($data['reward_type'])) {
            $updates[] = 'reward_type = :reward_type';
            $params['reward_type'] = self::normalizeRewardType($data['reward_type']);
        }
        if (array_key_exists('plan_id', $data)) {
            $updates[] = 'plan_id = :plan_id';
            $params['plan_id'] = isset($data['plan_id']) && (int) $data['plan_id'] > 0 ? (int) $data['plan_id'] : null;
        }
        if (array_key_exists('free_period_days', $data)) {
            $updates[] = 'free_period_days = :free_period_days';
            $params['free_period_days'] = isset($data['free_period_days']) && (int) $data['free_period_days'] > 0 ? (int) $data['free_period_days'] : null;
        }
        if (array_key_exists('discount_percent', $data)) {
            $updates[] = 'discount_percent = :discount_percent';
            $params['discount_percent'] = $data['discount_percent'] !== null ? self::normalizePercent($data['discount_percent']) : null;
        }
        if (array_key_exists('discount_credits', $data)) {
            $updates[] = 'discount_credits = :discount_credits';
            $params['discount_credits'] = $data['discount_credits'] !== null ? max(0, (int) $data['discount_credits']) : null;
        }
        if (array_key_exists('coupon_scope', $data)) {
            $updates[] = 'coupon_scope = :coupon_scope';
            $params['coupon_scope'] = self::normalizeCouponScope($data['coupon_scope']);
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

    private static function normalizeRewardType(mixed $value): string
    {
        $rewardType = is_string($value) ? trim($value) : 'credits';

        return match ($rewardType) {
            'billing_plan_trial', 'billing_plan_coupon' => $rewardType,
            default => 'credits',
        };
    }

    private static function normalizeCouponScope(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $scope = trim($value);
        if (!in_array($scope, ['initial', 'renewal', 'both'], true)) {
            return null;
        }

        return $scope;
    }

    private static function normalizePercent(mixed $value): float
    {
        $percent = is_numeric($value) ? (float) $value : 0.0;
        if ($percent < 0) {
            $percent = 0.0;
        }
        if ($percent > 100) {
            $percent = 100.0;
        }

        return round($percent, 2);
    }
}
