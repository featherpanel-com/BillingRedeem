ALTER TABLE `featherpanel_billingredeem_codes`
    ADD COLUMN IF NOT EXISTS `reward_type` ENUM('credits', 'billing_plan_trial', 'billing_plan_coupon') NOT NULL DEFAULT 'credits' AFTER `amount`,
    ADD COLUMN IF NOT EXISTS `plan_id` INT(11) NULL DEFAULT NULL AFTER `reward_type`,
    ADD COLUMN IF NOT EXISTS `free_period_days` INT(11) NULL DEFAULT NULL AFTER `plan_id`,
    ADD COLUMN IF NOT EXISTS `discount_percent` DECIMAL(7,2) NULL DEFAULT NULL AFTER `free_period_days`,
    ADD COLUMN IF NOT EXISTS `discount_credits` INT(11) NULL DEFAULT NULL AFTER `discount_percent`,
    ADD COLUMN IF NOT EXISTS `coupon_scope` ENUM('initial','renewal','both') NULL DEFAULT NULL AFTER `discount_credits`,
    ADD KEY `idx_reward_type` (`reward_type`),
    ADD KEY `idx_plan_id` (`plan_id`);
