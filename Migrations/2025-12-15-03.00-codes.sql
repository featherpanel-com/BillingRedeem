CREATE TABLE
	IF NOT EXISTS `featherpanel_billingredeem_codes` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`code` VARCHAR(255) NOT NULL,
		`amount` INT (11) NOT NULL DEFAULT 0 COMMENT 'Amount of coins to redeem',
		`uses` INT (11) NOT NULL DEFAULT 0 COMMENT 'Number of uses',
		`max_uses` INT (11) NOT NULL DEFAULT 1 COMMENT 'Maximum number of uses',
		`expires_at` DATETIME NULL DEFAULT NULL COMMENT 'Expiration date',
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `billingredeem_codes_code_unique` (`code`),
		KEY `idx_expires_at` (`expires_at`)
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


CREATE TABLE
	IF NOT EXISTS `featherpanel_billingredeem_usage` (
		`id` INT (11) NOT NULL AUTO_INCREMENT,
		`code_id` INT (11) NOT NULL,
		`user_id` INT (11) NOT NULL,
		`used_at` DATETIME NOT NULL COMMENT 'Date and time the code was used',
		`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `billingredeem_usage_code_user_unique` (`code_id`, `user_id`),
		KEY `idx_used_at` (`used_at`),
		KEY `idx_code_id` (`code_id`),
		KEY `idx_user_id` (`user_id`),
		CONSTRAINT `billingredeem_usage_code_id_foreign` FOREIGN KEY (`code_id`) REFERENCES `featherpanel_billingredeem_codes` (`id`) ON DELETE CASCADE,
		CONSTRAINT `billingredeem_usage_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `featherpanel_users` (`id`) ON DELETE CASCADE
	) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;