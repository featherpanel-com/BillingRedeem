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

namespace App\Addons\billingredeem\Controllers\User;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Addons\billingredeem\Chat\RedeemCode;
use Symfony\Component\HttpFoundation\Request;
use App\Addons\billingredeem\Chat\RedeemUsage;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\CreditsHelper;
use App\Addons\billingcore\Helpers\CurrencyHelper;
use App\Addons\billingredeem\Helpers\RedeemHelper;
use App\Addons\billingplans\Controllers\User\PlansController;

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

        $data = json_decode($request->getContent() ?: '{}', true, 32);
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

            // Record usage (use same PDO connection for transaction)
            $usageRecorded = RedeemUsage::recordUsage($user['id'], (int) $redeemCode['id'], $pdo);
            if (!$usageRecorded) {
                $pdo->rollBack();
                // Check if it's a duplicate (user already used this code)
                if (RedeemUsage::hasUserUsedCode($user['id'], (int) $redeemCode['id'])) {
                    return ApiResponse::error('You have already used this code', 'CODE_ALREADY_USED', 400);
                }

                return ApiResponse::error('Failed to record code usage', 'USAGE_FAILED', 500);
            }

            // Increment code uses (use same PDO connection for transaction)
            $incremented = RedeemCode::incrementUses((int) $redeemCode['id'], $pdo);
            if (!$incremented) {
                $pdo->rollBack();

                return ApiResponse::error('Failed to increment code uses', 'INCREMENT_FAILED', 500);
            }

            $rewardType = ($redeemCode['reward_type'] ?? 'credits') === 'billing_plan_trial'
                ? 'billing_plan_trial'
                : 'credits';
            if (($redeemCode['reward_type'] ?? 'credits') === 'billing_plan_coupon') {
                $pdo->rollBack();

                return ApiResponse::error(
                    'This is a checkout coupon code. Use it when subscribing to a billing plan.',
                    'COUPON_CHECKOUT_ONLY',
                    400
                );
            }
            $amount = (int) $redeemCode['amount'];
            $subscriptionPayload = null;
            if ($rewardType === 'credits') {
                $added = CreditsHelper::addUserCredits($user['id'], $amount);
                if (!$added) {
                    $pdo->rollBack();

                    return ApiResponse::error('Failed to add credits', 'CREDITS_FAILED', 500);
                }
            } else {
                if (!class_exists(PlansController::class)) {
                    $pdo->rollBack();

                    return ApiResponse::error('Billing plans addon is not available', 'BILLINGPLANS_UNAVAILABLE', 400);
                }

                $planId = (int) ($redeemCode['plan_id'] ?? 0);
                $freePeriodDays = max(1, (int) ($redeemCode['free_period_days'] ?? 0));
                if ($planId < 1 || $freePeriodDays < 1) {
                    $pdo->rollBack();

                    return ApiResponse::error('This redeem code is misconfigured.', 'CODE_MISCONFIGURED', 400);
                }

                $subscriptionRequest = Request::create(
                    '',
                    'POST',
                    [],
                    [],
                    [],
                    [],
                    json_encode([])
                );
                $subscriptionRequest->attributes->set('user', $user);
                $subscriptionRequest->attributes->set('_billingplans_skip_initial_charge', true);
                $subscriptionRequest->attributes->set('_billingplans_initial_period_days', $freePeriodDays);
                $subscriptionRequest->attributes->set('_billingplans_subscription_source', 'redeem_code:' . $code);

                $subscriptionResponse = (new PlansController())->subscribe($subscriptionRequest, $planId);
                $subscriptionResponsePayload = json_decode($subscriptionResponse->getContent(), true);
                if (($subscriptionResponsePayload['success'] ?? false) !== true) {
                    $pdo->rollBack();

                    return ApiResponse::error(
                        $subscriptionResponsePayload['message'] ?? 'Failed to activate plan from code',
                        $subscriptionResponsePayload['error_code'] ?? 'PLAN_TRIAL_FAILED',
                        (int) $subscriptionResponse->getStatusCode()
                    );
                }
                $subscriptionPayload = $subscriptionResponsePayload['data'] ?? null;
            }

            // Get updated user credits
            $newCredits = CreditsHelper::getUserCredits($user['id']);

            $pdo->commit();

            return ApiResponse::success([
                'code' => $code,
                'reward_type' => $rewardType,
                'amount' => $amount,
                'amount_formatted' => CurrencyHelper::formatAmount($amount),
                'new_credits' => $newCredits,
                'new_credits_formatted' => CurrencyHelper::formatAmount($newCredits),
                'plan_id' => isset($redeemCode['plan_id']) ? (int) $redeemCode['plan_id'] : null,
                'free_period_days' => isset($redeemCode['free_period_days']) ? (int) $redeemCode['free_period_days'] : null,
                'subscription' => $subscriptionPayload,
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

        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

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
