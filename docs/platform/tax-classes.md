---
title: Tax Classes
description: Manage product and organisation tax classifications.
---

## Overview

Tax classes categorise products and organisations for tax calculation. Signals uses two types of tax classes that work together to determine the applicable tax rate.

## Product Tax Classes

**Route:** `/admin/settings/tax/product-tax-classes`

Product tax classes categorise items by their tax treatment (e.g. "Standard Rate", "Reduced Rate", "Zero Rated", "Exempt").

| Action | Description |
|--------|-------------|
| Create | Add a new product tax class |
| Edit | Update name and description |
| Set Default | Designate as the default for new products |
| Delete | Remove a non-default tax class |

One product tax class must be the default. The default class is applied automatically to new products. The default tax class cannot be deleted.

## Organisation Tax Classes

**Route:** `/admin/settings/tax/organisation-tax-classes`

Organisation tax classes categorise members by their tax status (e.g. "Standard", "Tax Exempt", "Reverse Charge", "Charity").

| Action | Description |
|--------|-------------|
| Create | Add a new organisation tax class |
| Edit | Update name and description |
| Set Default | Designate as the default for new organisations |
| Delete | Remove a non-default tax class |

Organisation tax classes are assigned to members via the member edit form.

## How Tax Classes Work

When calculating tax on an opportunity or invoice line item, Signals considers both the product's tax class and the organisation's tax class to determine the correct tax treatment. The tax calculation engine (planned for a future release) will use these classifications along with tax rules to compute the final tax amount.
