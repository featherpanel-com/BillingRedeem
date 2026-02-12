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

use App\App;
use App\Permissions;
use App\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouteCollection;
use App\Addons\billingredeem\Controllers\User\BillingRedeemController as UserController;
use App\Addons\billingredeem\Controllers\Admin\BillingRedeemController as AdminController;

return function (RouteCollection $routes): void {
    // User Routes (require authentication)
    // Redeem a code
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingredeem-user-redeem',
        '/api/user/billingredeem/redeem',
        function (Request $request) {
            return (new UserController())->redeem($request);
        },
        ['POST']
    );

    // Get redemption history
    App::getInstance(true)->registerAuthRoute(
        $routes,
        'billingredeem-user-history',
        '/api/user/billingredeem/history',
        function (Request $request) {
            return (new UserController())->getHistory($request);
        },
        ['GET']
    );

    // Admin Routes
    // Get settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingredeem-admin-settings',
        '/api/admin/billingredeem/settings',
        function (Request $request) {
            return (new AdminController())->getSettings($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update settings
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingredeem-admin-settings-update',
        '/api/admin/billingredeem/settings',
        function (Request $request) {
            return (new AdminController())->updateSettings($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Get all codes
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingredeem-admin-codes',
        '/api/admin/billingredeem/codes',
        function (Request $request) {
            return (new AdminController())->getCodes($request);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Create a new code
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingredeem-admin-codes-create',
        '/api/admin/billingredeem/codes',
        function (Request $request) {
            return (new AdminController())->createCode($request);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['POST']
    );

    // Get code by ID
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingredeem-admin-code',
        '/api/admin/billingredeem/codes/{id}',
        function (Request $request, array $args) {
            $id = (int) ($args['id'] ?? 0);
            if (!$id) {
                return ApiResponse::error('Invalid code ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->getCode($request, $id);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );

    // Update code
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingredeem-admin-code-update',
        '/api/admin/billingredeem/codes/{id}',
        function (Request $request, array $args) {
            $id = (int) ($args['id'] ?? 0);
            if (!$id) {
                return ApiResponse::error('Invalid code ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->updateCode($request, $id);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['PATCH', 'PUT']
    );

    // Delete code
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingredeem-admin-code-delete',
        '/api/admin/billingredeem/codes/{id}',
        function (Request $request, array $args) {
            $id = (int) ($args['id'] ?? 0);
            if (!$id) {
                return ApiResponse::error('Invalid code ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->deleteCode($request, $id);
        },
        Permissions::ADMIN_USERS_EDIT,
        ['DELETE']
    );

    // Get code usage
    App::getInstance(true)->registerAdminRoute(
        $routes,
        'billingredeem-admin-code-usage',
        '/api/admin/billingredeem/codes/{id}/usage',
        function (Request $request, array $args) {
            $id = (int) ($args['id'] ?? 0);
            if (!$id) {
                return ApiResponse::error('Invalid code ID', 'INVALID_ID', 400);
            }

            return (new AdminController())->getCodeUsage($request, $id);
        },
        Permissions::ADMIN_USERS_VIEW,
        ['GET']
    );
};
