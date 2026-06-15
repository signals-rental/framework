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

## Tax Rates

**Route:** `/admin/settings/tax/rates`

Tax rates are named percentages (e.g. "UK Standard" at 20%, "Reduced" at 5%, "Zero" at 0%). Rates are referenced by tax rules rather than applied directly to products.

| Action | Description |
|--------|-------------|
| Create | Add a new named rate and percentage |
| Edit | Update name, description, or percentage |
| Activate / Deactivate | Toggle whether the rate is available for use |
| Delete | Remove a rate |

## Tax Rules

**Route:** `/admin/settings/tax/rules`

Tax rules map a combination of organisation tax class and product tax class to a tax rate. When more than one rule could apply, the rule with the highest **priority** wins. Each rule can be activated or deactivated without deleting it.

| Action | Description |
|--------|-------------|
| Create | Map an organisation tax class + product tax class to a tax rate, with a priority |
| Edit | Update the class pair, rate, or priority |
| Activate / Deactivate | Toggle whether the rule participates in resolution |
| Delete | Remove a rule |

## How Tax Classes Work

When calculating tax on a line item, Signals considers both the product's tax class and the organisation's tax class. The tax calculation engine resolves the highest-priority active **tax rule** for that class pair and applies its **tax rate** to compute the final tax amount. If no rule matches, no tax is applied.

The same resources are available through the [Tax Classes API](/docs/api/tax-classes) for programmatic management.
