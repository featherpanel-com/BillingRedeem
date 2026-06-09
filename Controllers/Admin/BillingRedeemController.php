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

namespace App\Addons\billingredeem\Controllers\Admin;

use App\Helpers\ApiResponse;
use OpenApi\Attributes as OA;
use App\Addons\billingplans\Chat\Plan;
use App\Addons\billingredeem\Chat\RedeemCode;
use Symfony\Component\HttpFoundation\Request;
use App\Addons\billingredeem\Chat\RedeemUsage;
use Symfony\Component\HttpFoundation\Response;
use App\Addons\billingcore\Helpers\CurrencyHelper;
use App\Addons\billingredeem\Helpers\RedeemHelper;

#[OA\Tag(name: 'Admin - Billing Redeem', description: 'Redeem codes administration')]
class BillingRedeemController
{
    #[OA\Get(
        path: '/api/admin/billingredeem/settings',
        summary: 'Get redeem settings',
        description: 'Get all redeem configuration settings',
        tags: ['Admin - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Settings retrieved successfully'),
        ]
    )]
    public function getSettings(Request $request): Response
    {
        $settings = RedeemHelper::getSettings();

        return ApiResponse::success($settings, 'Settings retrieved successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingredeem/settings',
        summary: 'Update redeem settings',
        description: 'Update redeem configuration settings',
        tags: ['Admin - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Settings updated successfully'),
            new OA\Response(response: 400, description: 'Invalid request data'),
        ]
    )]
    public function updateSettings(Request $request): Response
    {
        $data = json_decode($request->getContent() ?: '{}', true, 32);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        try {
            RedeemHelper::updateSettings($data);

            return ApiResponse::success(RedeemHelper::getSettings(), 'Settings updated successfully', 200);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update settings: ' . $e->getMessage(), 'UPDATE_FAILED', 500);
        }
    }

    #[OA\Get(
        path: '/api/admin/billingredeem/codes',
        summary: 'Get all codes',
        description: 'Get all redemption codes with pagination',
        tags: ['Admin - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Codes retrieved successfully'),
        ]
    )]
    public function getCodes(Request $request): Response
    {
        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        if ($limit > 100) {
            $limit = 100;
        }
        if ($limit < 1) {
            $limit = 50;
        }

        $codes = RedeemCode::getAll($limit, $offset);
        $total = RedeemCode::getCount();

        // Add usage count for each code
        foreach ($codes as &$code) {
            $code['usage_count'] = RedeemUsage::getCodeUsageCount((int) $code['id']);
            $code['is_valid'] = RedeemCode::isValid($code);
            $code['amount_formatted'] = CurrencyHelper::formatAmount((int) $code['amount']);
        }

        return ApiResponse::success([
            'codes' => $codes,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 'Codes retrieved successfully', 200);
    }

    public function getPlanOptions(Request $request): Response
    {
        if (!class_exists(Plan::class)) {
            return ApiResponse::success(['plans' => []], 'Billing plans addon is not installed', 200);
        }

        $plans = array_map(static fn (array $plan): array => [
            'id' => (int) ($plan['id'] ?? 0),
            'name' => (string) ($plan['name'] ?? ''),
            'billing_period_days' => (int) ($plan['billing_period_days'] ?? 30),
        ], Plan::getAll(true));

        return ApiResponse::success(['plans' => array_values($plans)], 'Plan options retrieved successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingredeem/codes/{id}',
        summary: 'Get code by ID',
        description: 'Get a specific redemption code by ID',
        tags: ['Admin - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Code retrieved successfully'),
            new OA\Response(response: 404, description: 'Code not found'),
        ]
    )]
    public function getCode(Request $request, int $id): Response
    {
        $code = RedeemCode::getById($id);
        if (!$code) {
            return ApiResponse::error('Code not found', 'CODE_NOT_FOUND', 404);
        }

        $code['usage_count'] = RedeemUsage::getCodeUsageCount($id);
        $code['is_valid'] = RedeemCode::isValid($code);
        $code['amount_formatted'] = CurrencyHelper::formatAmount((int) $code['amount']);

        return ApiResponse::success($code, 'Code retrieved successfully', 200);
    }

    #[OA\Post(
        path: '/api/admin/billingredeem/codes',
        summary: 'Create a new code',
        description: 'Create a new redemption code',
        tags: ['Admin - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Code created successfully'),
            new OA\Response(response: 400, description: 'Invalid request data'),
        ]
    )]
    public function createCode(Request $request): Response
    {
        $data = json_decode($request->getContent() ?: '{}', true, 32);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        $code = strtoupper(trim($data['code'] ?? ''));
        $amount = isset($data['amount']) ? (int) $data['amount'] : null;
        $maxUses = isset($data['max_uses']) ? (int) $data['max_uses'] : null;
        $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;
        $rewardType = in_array($data['reward_type'] ?? 'credits', ['credits', 'billing_plan_trial', 'billing_plan_coupon'], true)
            ? (string) $data['reward_type']
            : 'credits';
        $planId = isset($data['plan_id']) ? (int) $data['plan_id'] : null;
        $freePeriodDays = isset($data['free_period_days']) ? (int) $data['free_period_days'] : null;
        $discountPercent = array_key_exists('discount_percent', $data) ? (float) $data['discount_percent'] : 0.0;
        $discountCredits = array_key_exists('discount_credits', $data) ? (int) $data['discount_credits'] : 0;
        $couponScope = isset($data['coupon_scope']) ? (string) $data['coupon_scope'] : 'initial';

        if (empty($code)) {
            return ApiResponse::error('Code is required', 'CODE_REQUIRED', 400);
        }

        if ($amount === null || $amount < 0) {
            return ApiResponse::error('Amount must be a non-negative integer', 'INVALID_AMOUNT', 400);
        }

        if ($rewardType === 'billing_plan_trial') {
            if (!class_exists(Plan::class)) {
                return ApiResponse::error('Billing plans addon is not available', 'BILLINGPLANS_UNAVAILABLE', 400);
            }
            if ($planId === null || $planId < 1 || Plan::getById($planId) === null) {
                return ApiResponse::error('Please select a valid billing plan', 'INVALID_PLAN_ID', 400);
            }
            if ($freePeriodDays === null || $freePeriodDays < 1) {
                return ApiResponse::error('free_period_days must be at least 1', 'INVALID_FREE_PERIOD', 400);
            }
            $amount = 0;
            $discountPercent = 0.0;
            $discountCredits = 0;
            $couponScope = null;
        }

        if ($rewardType === 'billing_plan_coupon') {
            if (!class_exists(Plan::class)) {
                return ApiResponse::error('Billing plans addon is not available', 'BILLINGPLANS_UNAVAILABLE', 400);
            }
            if ($planId !== null && $planId > 0 && Plan::getById($planId) === null) {
                return ApiResponse::error('Please select a valid billing plan', 'INVALID_PLAN_ID', 400);
            }
            if ($discountPercent < 0 || $discountPercent > 100) {
                return ApiResponse::error('discount_percent must be between 0 and 100', 'INVALID_DISCOUNT_PERCENT', 400);
            }
            if ($discountCredits < 0) {
                return ApiResponse::error('discount_credits must be >= 0', 'INVALID_DISCOUNT_CREDITS', 400);
            }
            if ($discountPercent <= 0 && $discountCredits <= 0) {
                return ApiResponse::error('Set discount_percent or discount_credits for coupon codes', 'MISSING_DISCOUNT', 400);
            }
            if (!in_array($couponScope, ['initial', 'renewal', 'both'], true)) {
                return ApiResponse::error('coupon_scope must be initial, renewal, or both', 'INVALID_COUPON_SCOPE', 400);
            }
            $amount = 0;
            $freePeriodDays = null;
        }

        // Check if code already exists
        $existing = RedeemCode::getByCode($code);
        if ($existing) {
            return ApiResponse::error('Code already exists', 'CODE_EXISTS', 400);
        }

        // Use default max uses from settings if not provided
        if ($maxUses === null) {
            $settings = RedeemHelper::getSettings();
            $maxUses = $settings['default_max_uses'];
        }

        $codeData = [
            'code' => $code,
            'amount' => $amount,
            'max_uses' => $maxUses,
            'expires_at' => $expiresAt,
            'reward_type' => $rewardType,
            'plan_id' => match ($rewardType) {
                'billing_plan_trial' => $planId,
                'billing_plan_coupon' => ($planId !== null && $planId > 0) ? $planId : null,
                default => null,
            },
            'free_period_days' => $rewardType === 'billing_plan_trial' ? $freePeriodDays : null,
            'discount_percent' => $rewardType === 'billing_plan_coupon' ? $discountPercent : null,
            'discount_credits' => $rewardType === 'billing_plan_coupon' ? $discountCredits : null,
            'coupon_scope' => $rewardType === 'billing_plan_coupon' ? $couponScope : null,
        ];

        $created = RedeemCode::create($codeData);
        if (!$created) {
            return ApiResponse::error('Failed to create code', 'CREATE_FAILED', 500);
        }

        $created['usage_count'] = 0;
        $created['is_valid'] = RedeemCode::isValid($created);
        $created['amount_formatted'] = CurrencyHelper::formatAmount((int) $created['amount']);

        return ApiResponse::success($created, 'Code created successfully', 200);
    }

    #[OA\Patch(
        path: '/api/admin/billingredeem/codes/{id}',
        summary: 'Update a code',
        description: 'Update an existing redemption code',
        tags: ['Admin - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Code updated successfully'),
            new OA\Response(response: 404, description: 'Code not found'),
        ]
    )]
    public function updateCode(Request $request, int $id): Response
    {
        $code = RedeemCode::getById($id);
        if (!$code) {
            return ApiResponse::error('Code not found', 'CODE_NOT_FOUND', 404);
        }

        $data = json_decode($request->getContent() ?: '{}', true, 32);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ApiResponse::error('Invalid JSON in request body', 'INVALID_JSON', 400);
        }

        // Check if code is being changed and if it already exists
        if (isset($data['code']) && $data['code'] !== $code['code']) {
            $existing = RedeemCode::getByCode($data['code']);
            if ($existing) {
                return ApiResponse::error('Code already exists', 'CODE_EXISTS', 400);
            }
        }

        $effectiveRewardType = isset($data['reward_type']) && in_array($data['reward_type'], ['credits', 'billing_plan_trial', 'billing_plan_coupon'], true)
            ? $data['reward_type']
            : ((in_array($code['reward_type'] ?? 'credits', ['credits', 'billing_plan_trial', 'billing_plan_coupon'], true)) ? $code['reward_type'] : 'credits');
        $effectivePlanId = array_key_exists('plan_id', $data) ? (int) ($data['plan_id'] ?? 0) : (int) ($code['plan_id'] ?? 0);
        $effectiveFreeDays = array_key_exists('free_period_days', $data) ? (int) ($data['free_period_days'] ?? 0) : (int) ($code['free_period_days'] ?? 0);
        $effectiveDiscountPercent = array_key_exists('discount_percent', $data) ? (float) ($data['discount_percent'] ?? 0) : (float) ($code['discount_percent'] ?? 0);
        $effectiveDiscountCredits = array_key_exists('discount_credits', $data) ? (int) ($data['discount_credits'] ?? 0) : (int) ($code['discount_credits'] ?? 0);
        $effectiveCouponScope = array_key_exists('coupon_scope', $data) ? (string) ($data['coupon_scope'] ?? '') : (string) ($code['coupon_scope'] ?? '');
        if ($effectiveRewardType === 'billing_plan_trial') {
            if (!class_exists(Plan::class)) {
                return ApiResponse::error('Billing plans addon is not available', 'BILLINGPLANS_UNAVAILABLE', 400);
            }
            if ($effectivePlanId < 1 || Plan::getById($effectivePlanId) === null) {
                return ApiResponse::error('Please select a valid billing plan', 'INVALID_PLAN_ID', 400);
            }
            if ($effectiveFreeDays < 1) {
                return ApiResponse::error('free_period_days must be at least 1', 'INVALID_FREE_PERIOD', 400);
            }
            $data['amount'] = 0;
            $data['discount_percent'] = null;
            $data['discount_credits'] = null;
            $data['coupon_scope'] = null;
        } elseif ($effectiveRewardType === 'billing_plan_coupon') {
            if (!class_exists(Plan::class)) {
                return ApiResponse::error('Billing plans addon is not available', 'BILLINGPLANS_UNAVAILABLE', 400);
            }
            if ($effectivePlanId > 0 && Plan::getById($effectivePlanId) === null) {
                return ApiResponse::error('Please select a valid billing plan', 'INVALID_PLAN_ID', 400);
            }
            if ($effectiveDiscountPercent < 0 || $effectiveDiscountPercent > 100) {
                return ApiResponse::error('discount_percent must be between 0 and 100', 'INVALID_DISCOUNT_PERCENT', 400);
            }
            if ($effectiveDiscountCredits < 0) {
                return ApiResponse::error('discount_credits must be >= 0', 'INVALID_DISCOUNT_CREDITS', 400);
            }
            if ($effectiveDiscountPercent <= 0 && $effectiveDiscountCredits <= 0) {
                return ApiResponse::error('Set discount_percent or discount_credits for coupon codes', 'MISSING_DISCOUNT', 400);
            }
            if (!in_array($effectiveCouponScope, ['initial', 'renewal', 'both'], true)) {
                return ApiResponse::error('coupon_scope must be initial, renewal, or both', 'INVALID_COUPON_SCOPE', 400);
            }
            $data['amount'] = 0;
            $data['free_period_days'] = null;
        } elseif (isset($data['amount']) && (int) $data['amount'] < 0) {
            return ApiResponse::error('Amount must be a non-negative integer', 'INVALID_AMOUNT', 400);
        }

        $updated = RedeemCode::update($id, $data);
        if (!$updated) {
            return ApiResponse::error('Failed to update code', 'UPDATE_FAILED', 500);
        }

        $updatedCode = RedeemCode::getById($id);
        $updatedCode['usage_count'] = RedeemUsage::getCodeUsageCount($id);
        $updatedCode['is_valid'] = RedeemCode::isValid($updatedCode);
        $updatedCode['amount_formatted'] = CurrencyHelper::formatAmount((int) $updatedCode['amount']);

        return ApiResponse::success($updatedCode, 'Code updated successfully', 200);
    }

    #[OA\Delete(
        path: '/api/admin/billingredeem/codes/{id}',
        summary: 'Delete a code',
        description: 'Delete a redemption code',
        tags: ['Admin - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Code deleted successfully'),
            new OA\Response(response: 404, description: 'Code not found'),
        ]
    )]
    public function deleteCode(Request $request, int $id): Response
    {
        $code = RedeemCode::getById($id);
        if (!$code) {
            return ApiResponse::error('Code not found', 'CODE_NOT_FOUND', 404);
        }

        $deleted = RedeemCode::delete($id);
        if (!$deleted) {
            return ApiResponse::error('Failed to delete code', 'DELETE_FAILED', 500);
        }

        return ApiResponse::success([], 'Code deleted successfully', 200);
    }

    #[OA\Get(
        path: '/api/admin/billingredeem/codes/{id}/usage',
        summary: 'Get code usage',
        description: 'Get all users who used a specific code',
        tags: ['Admin - Billing Redeem'],
        responses: [
            new OA\Response(response: 200, description: 'Usage retrieved successfully'),
            new OA\Response(response: 404, description: 'Code not found'),
        ]
    )]
    public function getCodeUsage(Request $request, int $id): Response
    {
        $code = RedeemCode::getById($id);
        if (!$code) {
            return ApiResponse::error('Code not found', 'CODE_NOT_FOUND', 404);
        }

        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        if ($limit > 100) {
            $limit = 100;
        }
        if ($limit < 1) {
            $limit = 50;
        }

        $usage = RedeemUsage::getCodeUsage($id, $limit, $offset);
        $total = RedeemUsage::getCodeUsageCount($id);

        return ApiResponse::success([
            'usage' => $usage,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ], 'Usage retrieved successfully', 200);
    }
}
