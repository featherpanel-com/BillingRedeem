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

namespace App\Addons\billingredeem\Helpers;

use App\Plugins\PluginSettings;

/**
 * Helper for working with Redeem settings using PluginSettings.
 */
class RedeemHelper
{
    private const PLUGIN_IDENTIFIER = 'billingredeem';

    /**
     * Get all redeem settings.
     */
    public static function getSettings(): array
    {
        return [
            'is_enabled' => self::getSetting('is_enabled') === '1' || self::getSetting('is_enabled') === 'true',
            'allow_multiple_uses' => self::getSetting('allow_multiple_uses') === '1' || self::getSetting('allow_multiple_uses') === 'true',
            'default_max_uses' => self::defaultMaxUsesFromStored(),
        ];
    }

    /**
     * Update redeem settings.
     */
    public static function updateSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            if ($value === null) {
                continue;
            }

            // Convert boolean to string
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } else {
                $value = (string) $value;
            }

            self::setSetting($key, $value);
        }
    }

    /**
     * Check if redeem system is enabled.
     */
    public static function isEnabled(): bool
    {
        return self::getSetting('is_enabled') === '1' || self::getSetting('is_enabled') === 'true';
    }

    /**
     * Default max uses from storage; 0 means unlimited. Missing / empty falls back to 1.
     */
    private static function defaultMaxUsesFromStored(): int
    {
        $raw = self::getSetting('default_max_uses');
        if ($raw === null || $raw === '') {
            return 1;
        }

        return (int) $raw;
    }

    /**
     * Get a setting value.
     */
    private static function getSetting(string $key): ?string
    {
        return PluginSettings::getSetting(self::PLUGIN_IDENTIFIER, $key);
    }

    /**
     * Set a setting value.
     */
    private static function setSetting(string $key, string $value): void
    {
        PluginSettings::setSetting(self::PLUGIN_IDENTIFIER, $key, $value);
    }
}
