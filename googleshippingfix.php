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

    if (!Validate::isLoadedObject($product_obj)) {
        return '';
    }

    $id_product = (int)$product_obj->id;
    $currency = $this->context->currency->iso_code;
    $country_iso = ($currency === 'RON') ? 'RO' : 'HU';

    $p_name = is_array($product_obj->name) ? $product_obj->name[$id_lang] : $product_obj->name;
    $p_desc = is_array($product_obj->description_short) ? $product_obj->description_short[$id_lang] : $product_obj->description_short;
    if (empty($p_desc)) {
        $p_desc = is_array($product_obj->description) ? $product_obj->description[$id_lang] : $product_obj->description;
    }

    $image = Image::getCover($id_product);
    $image_url = "";
    if ($image) {
        $image_url = $this->context->link->getImageLink($product_obj->link_rewrite[$id_lang] ?? $product_obj->link_rewrite, $image['id_image'], 'large_default');
    }

    $shipping_cost = Product::getPriceStatic($id_product, true, null, 6, null, false, true, 1, false, null, null, null, $s_p, true, true, $this->context);
    if ($shipping_cost <= 0) {
        $shipping_cost = 0;
    }

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
            "price" => number_format(Product::getPriceStatic($id_product, true), 2, '.', ''),
            "availability" => ($product_obj->quantity > 0) ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
            "url" => $this->context->link->getProductLink($product_obj),
            "shippingDetails" => [
                "@type" => "OfferShippingDetails",
                "shippingRate" => [
                    "@type" => "MonetaryAmount",
                    "value" => number_format((float)$shipping_cost, 2, '.', ''),
                    "currency" => $currency
                ],
                "shippingDestination" => [
                    "@type" => "DefinedRegion",
                    "addressCountry" => $country_iso
                ],
                "deliveryTime" => [
                    "@type" => "ShippingDeliveryTime",
                    "handlingTime": [
                        "@type": "QuantitativeValue",
                        "minValue": 0,
                        "maxValue": 1,
                        "unitCode": "DAY"
                    ],
                    "transitTime": [
                        "@type": "QuantitativeValue",
                        "minValue": 1,
                        "maxValue": 3,
                        "unitCode": "DAY"
                    ]
                ]
            ],
            "hasMerchantReturnPolicy" => [
                "@type" => "MerchantReturnPolicy",
                "applicableCountry" => $country_iso,
                "returnPolicyCategory" => "https://schema.org/MerchantReturnFiniteReturnPeriod",
                "merchantReturnDays" => (int)Configuration::get('GS_RETURN_DAYS', 14),
                "returnMethod" => "https://schema.org/ReturnByMail",
                "returnFees" => ($currency === 'RON' ? "https://schema.org/ReturnFeesCustomerPaying" : "https://schema.org/FreeReturn")
            ]
        ]
    ];

    return '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
}
}
