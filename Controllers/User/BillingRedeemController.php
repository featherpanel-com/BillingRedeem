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

namespace App\Addons\billingredeem\Controllers\User;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Addons\billingredeem\Chat\RedeemCode;
use App\Addons\billingredeem\Chat\RedeemUsage;
use App\Addons\billingredeem\Helpers\RedeemHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\CreditsHelper;
use App\Addons\billingcore\Helpers\CurrencyHelper;

#[OA\Tag(name: 'User - Billing Redeem', description: 'Redeem codes management for users')]
class BillingRedeemController
{
    #[OA\Post(
        path: '/api/user/billingredeem/redeem',
        summary: 'Redeem a code',
        description: 'Redeem a coupon code for credits',
        tags: ['User - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Code redeemed successfully'),
            new OA\Response(response: 400, description: 'Invalid code or already used'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function redeem(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        if (!RedeemHelper::isEnabled()) {
            return ApiResponse::error('Redeem system is currently disabled', 'REDEEM_DISABLED', 403);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        $code = trim($data['code'] ?? '');
        if (empty($code)) {
            return ApiResponse::error('Code is required', 'CODE_REQUIRED', 400);
        }

        // Get the code from database
        $redeemCode = RedeemCode::getByCode($code);
        if (!$redeemCode) {
            return ApiResponse::error('Invalid redemption code', 'CODE_INVALID', 400);
        }

        // Check if code is valid (not expired, not maxed out)
        if (!RedeemCode::isValid($redeemCode)) {
            return ApiResponse::error('This code is no longer valid (expired or max uses reached)', 'CODE_INVALID', 400);
        }

        // Check if user has already used this code (if multiple uses not allowed)
        $settings = RedeemHelper::getSettings();
        if (!$settings['allow_multiple_uses']) {
            if (RedeemUsage::hasUserUsedCode($user['id'], (int) $redeemCode['id'])) {
                return ApiResponse::error('You have already used this code', 'CODE_ALREADY_USED', 400);
            }
        }

        // Use transaction to ensure atomicity
        $pdo = \App\Chat\Database::getPdoConnection();
        try {
            $pdo->beginTransaction();

            // Double-check code validity (with lock)
            $stmt = $pdo->prepare(
                'SELECT * FROM featherpanel_billingredeem_codes WHERE id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $redeemCode['id']]);
            $lockedCode = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$lockedCode || !RedeemCode::isValid($lockedCode)) {
                $pdo->rollBack();
                return ApiResponse::error('This code is no longer valid', 'CODE_INVALID', 400);
            }

            // Record usage
            $usageRecorded = RedeemUsage::recordUsage($user['id'], (int) $redeemCode['id']);
            if (!$usageRecorded) {
                $pdo->rollBack();
                return ApiResponse::error('Failed to record code usage', 'USAGE_FAILED', 500);
            }

            // Increment code uses
            RedeemCode::incrementUses((int) $redeemCode['id']);

            // Add credits to user
            $amount = (int) $redeemCode['amount'];
            $added = CreditsHelper::addUserCredits($user['id'], $amount);
            if (!$added) {
                $pdo->rollBack();
                return ApiResponse::error('Failed to add credits', 'CREDITS_FAILED', 500);
            }

            // Get updated user credits
            $newCredits = CreditsHelper::getUserCredits($user['id']);

            $pdo->commit();

            return ApiResponse::success([
                'code' => $code,
                'amount' => $amount,
                'amount_formatted' => CurrencyHelper::formatAmount($amount),
                'new_credits' => $newCredits,
                'new_credits_formatted' => CurrencyHelper::formatAmount($newCredits),
            ], 'Code redeemed successfully', 200);
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            \App\App::getInstance(true)->getLogger()->error('Failed to redeem code: ' . $e->getMessage());

            return ApiResponse::error('An error occurred while redeeming the code', 'REDEEM_ERROR', 500);
        }
    }

    #[OA\Get(
        path: '/api/user/billingredeem/history',
        summary: 'Get redemption history',
        description: 'Get the current user\'s redemption code history',
        tags: ['User - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'History retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function getHistory(Request $request): Response
    {
        $user = $request->attributes->get('user') ?? $request->get('user');
        if (!$user || !isset($user['id'])) {
            return ApiResponse::error('User not authenticated', 'UNAUTHORIZED', 401);
        }

        $limit = (int) ($request->query->get('limit', 50));
        $offset = (int) ($request->query->get('offset', 0));

        if ($limit > 100) {
            $limit = 100;
        }
        if ($limit < 1) {
            $limit = 50;
        }

        $history = RedeemUsage::getUserUsage($user['id'], $limit, $offset);
        $total = RedeemUsage::getUserUsageCount($user['id']);

        return ApiResponse::success([
            'history' => $history,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 'History retrieved successfully', 200);
    }
}

