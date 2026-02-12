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

        $code = trim($data['code'] ?? '');
        $amount = isset($data['amount']) ? (int) $data['amount'] : null;
        $maxUses = isset($data['max_uses']) ? (int) $data['max_uses'] : null;
        $expiresAt = !empty($data['expires_at']) ? $data['expires_at'] : null;

        if (empty($code)) {
            return ApiResponse::error('Code is required', 'CODE_REQUIRED', 400);
        }

        if ($amount === null || $amount < 0) {
            return ApiResponse::error('Amount must be a non-negative integer', 'INVALID_AMOUNT', 400);
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
