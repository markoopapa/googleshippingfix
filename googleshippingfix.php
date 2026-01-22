<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class GoogleShippingFix extends Module
{
    public function __construct()
    {
        $this->name = 'googleshippingfix';
        $this->tab = 'seo';
        $this->version = '1.1.2';
        $this->author = 'markoo';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google Merchant Shipping & Return Fix');
        $this->description = $this->l('Javítja a szállítási és visszaküldési adatokat (HUF/RON).');
    }

    public function install()
    {
        Configuration::updateValue('GS_RETURN_DAYS', 14);
        return parent::install() && $this->registerHook('displayFooterProduct');
    }

    public function hookDisplayFooterProduct($params)
{
    $id_lang = (int)$this->context->language->id;
    $product_obj = null;

    if (isset($params['product']) && is_object($params['product'])) {
        $product_obj = new Product((int)$params['product']->id, true, $id_lang);
    } elseif (isset($params['product']['id_product'])) {
        $product_obj = new Product((int)$params['product']['id_product'], true, $id_lang);
    }

    if (!Validate::isLoadedObject($product_obj)) return '';

    $id_product = (int)$product_obj->id;
    $currency = $this->context->currency->iso_code;
    $country_iso = ($currency === 'RON') ? 'RO' : 'HU';

    // FIX: Szállítási díj meghatározása
    // Ha a PrestaShop nem adja vissza jól, itt adj meg egy alapértelmezett értéket (pl. 1500)
    $shipping_cost = 1500; 
    if ($currency === 'RON') {
        $shipping_cost = 25; // Példa román szállítási díjra
    }

    $jsonld = [
        "@context" => "https://schema.org/",
        "@type" => "Product",
        "name" => strip_tags($product_obj->name[$id_lang] ?? $product_obj->name),
        "description" => strip_tags($product_obj->description_short[$id_lang] ?? $product_obj->description_short),
        "image" => $this->context->link->getImageLink($product_obj->link_rewrite[$id_lang] ?? $product_obj->link_rewrite, Image::getCover($id_product)['id_image'], 'large_default'),
        "sku" => $product_obj->reference,
        "mpn" => $product_obj->reference,
        "gtin" => $product_obj->ean13,
        "brand" => ["@type" => "Brand", "name" => Manufacturer::getNameById((int)$product_obj->id_manufacturer) ?: Configuration::get('PS_SHOP_NAME')],
        "offers" => [
            "@type" => "Offer",
            "priceCurrency" => $currency,
            "price" => number_format((float)Product::getPriceStatic($id_product, true), 2, '.', ''),
            "priceValidUntil" => "2026-12-31",
            "availability" => ($product_obj->quantity > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
            "url" => $this->context->link->getProductLink($product_obj),
            "shippingDetails" => [
                "@type" => "OfferShippingDetails",
                "shippingRate" => ["@type" => "MonetaryAmount", "value" => number_format($shipping_cost, 2, '.', ''), "currency" => $currency],
                "shippingDestination" => ["@type" => "DefinedRegion", "addressCountry" => $country_iso],
                "deliveryTime" => [
                    "@type" => "ShippingDeliveryTime",
                    "handlingTime" => ["@type" => "QuantitativeValue", "minValue" => 0, "maxValue" => 1, "unitCode" => "DAY"],
                    "transitTime" => ["@type" => "QuantitativeValue", "minValue" => 1, "maxValue" => 3, "unitCode" => "DAY"]
                ]
            ],
            "hasMerchantReturnPolicy" => [
                "@type" => "MerchantReturnPolicy",
                "applicableCountry" => $country_iso,
                "returnPolicyCountry" => $country_iso,
                "returnPolicyCategory" => "https://schema.org/MerchantReturnFiniteReturnWindow",
                "merchantReturnDays" => 14,
                "returnMethod" => "https://schema.org/ReturnByMail",
                "returnFees" => "https://schema.org/FreeReturn"
            ]
        ]
    ];

    return '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
}
}
