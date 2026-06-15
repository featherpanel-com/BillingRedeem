# BillingRedeem

Let users redeem coupon codes for credits or billing-plan rewards. Integrates with **Billing Core** for credit payouts and optionally with **Billing Plans** for trial subscriptions and plan coupons.


## Features

- **User-facing**
  - **Earn Credits → Redeem Codes** (`/earn/redeem`)
  - Enter a code to redeem
  - Reward types: flat **credits**, **billing plan trial** (free period), **billing plan coupon** (discount on initial/renewal/both)
  - View redemption history

- **Admin**
  - **Fremium Earn Credits → Redeem Codes**
  - Enable/disable redeem system; allow multiple uses per user; default max uses
  - Create/edit/delete codes — amount, expiry, max uses, reward type, linked plan, free period days, discount percent/credits, coupon scope
  - View code usage per code; plan options endpoint (requires BillingPlans for plan-linked codes)


## Authors

- NaysKutzu  
- MythicalSystems
