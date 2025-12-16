<?php

/*
 * This file is part of FeatherPanel.
 *
 * MIT License
 *
 * Copyright (c) 2025 MythicalSystems
 * Copyright (c) 2025 Cassian Gherman (NaysKutzu)
 * Copyright (c) 2018 - 2021 Dane Everitt <dane@daneeveritt.com> and Contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace App\Addons\billingredeem\Chat;

use App\App;
use App\Chat\Database;
use App\Chat\User;

/**
 * Redeem Usage chat model for tracking code usage.
 */
class RedeemUsage
{
    private static string $table = 'featherpanel_billingredeem_usage';

    /**
     * Check if a user has already used a code.
     */
    public static function hasUserUsedCode(int $userId, int $codeId): bool
    {
        if ($userId <= 0 || $codeId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT id FROM ' . self::$table . ' WHERE user_id = :user_id AND code_id = :code_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'code_id' => $codeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false;
    }

    /**
     * Record that a user has used a code.
     */
    public static function recordUsage(int $userId, int $codeId): bool
    {
        if ($userId <= 0 || $codeId <= 0) {
            return false;
        }

        if (!self::assertUserExists($userId)) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO ' . self::$table . ' (code_id, user_id, used_at) 
                VALUES (:code_id, :user_id, NOW())'
            );
            $stmt->execute([
                'code_id' => $codeId,
                'user_id' => $userId,
            ]);

            return true;
        } catch (\PDOException $e) {
            // Handle duplicate key (user already used this code)
            if ($e->getCode() === '23000') {
                return false;
            }
            App::getInstance(true)->getLogger()->error('Failed to record code usage: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Get usage record by user and code.
     */
    public static function getUsage(int $userId, int $codeId): ?array
    {
        if ($userId <= 0 || $codeId <= 0) {
            return null;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM ' . self::$table . ' WHERE user_id = :user_id AND code_id = :code_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'code_id' => $codeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result !== false ? $result : null;
    }

    /**
     * Get all codes used by a user.
     */
    public static function getUserUsage(int $userId, int $limit = 50, int $offset = 0): array
    {
        if ($userId <= 0) {
            return [];
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT u.*, c.code, c.amount 
            FROM ' . self::$table . ' u
            INNER JOIN featherpanel_billingredeem_codes c ON u.code_id = c.id
            WHERE u.user_id = :user_id 
            ORDER BY u.used_at DESC 
            LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all users who used a specific code.
     */
    public static function getCodeUsage(int $codeId, int $limit = 50, int $offset = 0): array
    {
        if ($codeId <= 0) {
            return [];
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT u.*, usr.email, usr.username 
            FROM ' . self::$table . ' u
            INNER JOIN featherpanel_users usr ON u.user_id = usr.id
            WHERE u.code_id = :code_id 
            ORDER BY u.used_at DESC 
            LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':code_id', $codeId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get total usage count for a code.
     */
    public static function getCodeUsageCount(int $codeId): int
    {
        if ($codeId <= 0) {
            return 0;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM ' . self::$table . ' WHERE code_id = :code_id'
        );
        $stmt->execute(['code_id' => $codeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Get total usage count for a user.
     */
    public static function getUserUsageCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $pdo = Database::getPdoConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) as count FROM ' . self::$table . ' WHERE user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Delete usage records for a code (when code is deleted).
     */
    public static function deleteByCodeId(int $codeId): bool
    {
        if ($codeId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE code_id = :code_id');
            $stmt->execute(['code_id' => $codeId]);

            return true;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete code usage: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Delete usage records for a user (when user is deleted).
     */
    public static function deleteByUserId(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $pdo = Database::getPdoConnection();

        try {
            $stmt = $pdo->prepare('DELETE FROM ' . self::$table . ' WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $userId]);

            return true;
        } catch (\PDOException $e) {
            App::getInstance(true)->getLogger()->error('Failed to delete user usage: ' . $e->getMessage());

            return false;
        }
    }

    private static function assertUserExists(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = User::getUserById($userId);

        return $user !== null;
    }
}

