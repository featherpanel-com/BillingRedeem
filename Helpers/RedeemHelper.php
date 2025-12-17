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
            'default_max_uses' => self::getSetting('default_max_uses') ? (int) self::getSetting('default_max_uses') : 1,
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
