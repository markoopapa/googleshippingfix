Google Merchant Shipping & Return Fix (PrestaShop Module)

Compatibility: PrestaShop 8.x (Tested on 8.2.1)

Version: 1.1.5

Overview
This module is designed to resolve critical errors and warnings in Google Merchant Center and Google Search Console caused by missing or incorrectly formatted structured data (Schema.org). It is specifically optimized for stores operating in Hungary (HUF) and Romania (RON).

Key Fixes
The module automatically injects a clean JSON-LD block into product pages to fix:

Mismatched Currency in Shipping: Detects if the user is browsing in HUF or RON and adjusts the shippingRate currency and addressCountry accordingly.

Missing Shipping Details: Adds the shippingDetails object, including handling time and transit time.

Merchant Return Policy Errors: Resolves the "Invalid enum value" error by using the correct https://schema.org/MerchantReturnReturnFiniteReturnWindow instead of deprecated terms.

Return Fees: Correctly sets FreeReturn or ReturnFeesCustomerPaying using full HTTPS Schema.org identifiers.

Missing SEO Fields: Auto-generates priceValidUntil, sku, gtin, and brand to satisfy Google's "Product Snippets" requirements.

Features
Multi-Currency Support: Dynamically switches between HU and RO regions based on the active currency (HUF/RON).

Multi-Language Fix: Robust detection for product names and descriptions, ensuring they don't display as single characters (e.g., "o") in multi-language environments.

Admin Configuration: Set your global return period (e.g., 14 days) directly from the module configuration page.

Theme Independent: Works alongside any theme (like ZOneTheme) by providing a more complete data block that Google prioritizes.

Installation
Compress the googleshippingfix folder into a .zip file.

Go to PrestaShop Admin > Modules > Module Manager.

Click Upload a module and select your zip file.

Click Install.

Configuration
Find the module in your Module Manager and click Configure.

Set the Return Policy Days (Default is 14).

Save the settings.

Important: Clear your PrestaShop cache under Advanced Parameters > Performance > Clear Cache.

Troubleshooting
Duplicated Product Items
If the Google Rich Results Test shows two "Product" items, it means your theme is also generating Schema data. Google will typically prioritize the more complete block (the one from this module). For a cleaner setup, you can rename the theme's microdata file: themes/[your-theme]/templates/_partials/microdata/product-jsonld.tpl → product-jsonld.tpl.bak.

Validating the Fix
After installation, use the Google Rich Results Test to verify that the Merchant Listings section is green and free of "Invalid enum" errors.
