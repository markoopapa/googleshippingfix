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

    // JAVÍTÁS: Név és leírás kinyerése kényszerített nyelvvel
    $p_name = $product_obj->name;
    if (is_array($p_name)) {
        $p_name = isset($p_name[$id_lang]) ? $p_name[$id_lang] : reset($p_name);
    }

    $p_desc = $product_obj->description_short;
    if (is_array($p_desc)) {
        $p_desc = isset($p_desc[$id_lang]) ? $p_desc[$id_lang] : reset($p_desc);
    }
    if (empty(strip_tags($p_desc))) {
        $p_desc = is_array($product_obj->description) ? (isset($product_obj->description[$id_lang]) ? $product_obj->description[$id_lang] : reset($product_obj->description)) : $product_obj->description;
    }

    $image = Image::getCover($id_product);
    $image_url = $image ? $this->context->link->getImageLink($product_obj->link_rewrite[$id_lang] ?? $product_obj->link_rewrite, $image['id_image'], 'large_default') : "";

    // FIX: Szállítási díj (itt írd át az összeget, ha nem 1500)
    $shipping_cost = ($currency === 'RON') ? 25.00 : 1500.00;

    $jsonld = [
        "@context" => "https://schema.org/",
        "@type" => "Product",
        "name" => strip_tags($p_name),
        "description" => strip_tags($p_desc),
        "image" => $image_url,
        "sku" => $product_obj->reference,
        "mpn" => $product_obj->reference,
        "gtin" => $product_obj->ean13,
        "brand" => [
            "@type" => "Brand", 
            "name" => Manufacturer::getNameById((int)$product_obj->id_manufacturer) ?: Configuration::get('PS_SHOP_NAME')
        ],
        "offers" => [
            "@type" => "Offer",
            "priceCurrency" => $currency,
            "price" => number_format((float)Product::getPriceStatic($id_product, true), 2, '.', ''),
            "priceValidUntil" => "2026-12-31",
            "availability" => ($product_obj->quantity > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
            "url" => $this->context->link->getProductLink($product_obj),
            "shippingDetails" => [
                "@type" => "OfferShippingDetails",
                "shippingRate" => [
                    "@type" => "MonetaryAmount", 
                    "value" => number_format($shipping_cost, 2, '.', ''), 
                    "currency" => $currency
                ],
                "shippingDestination" => [
                    "@type" => "DefinedRegion", 
                    "addressCountry" => $country_iso
                ],
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
