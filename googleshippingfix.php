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
        $this->version = '1.1.1';
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
    $product_obj = null;
    if (isset($params['product']) && is_object($params['product'])) {
        $product_obj = $params['product'];
    } elseif (isset($params['product']['id_product'])) {
        $product_obj = new Product((int)$params['product']['id_product'], true, (int)$this->context->language->id);
    }

    if (!$product_obj) {
        return '';
    }

    $id_product = (int)$product_obj->id;
    $currency = $this->context->currency->iso_code;
    $country_iso = ($currency === 'RON') ? 'RO' : 'HU';
    
    // Képek lekérése
    $images = $product_obj->getImages((int)$this->context->language->id);
    $image_urls = [];
    foreach ($images as $img) {
        $image_urls[] = $this->context->link->getImageLink($product_obj->link_rewrite, $img['id_image'], 'large_default');
    }

    // Szállítási díj lekérése
    $shipping_cost = Product::getPriceStatic($id_product, true, null, 6, null, false, true);
    if ($shipping_cost <= 0) {
        $id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');
        $carrier = new Carrier($id_carrier);
        $shipping_cost = $carrier->getDeliveryPriceByWeight(0, (int)Context::getContext()->country->id);
    }

    // Teljes JSON-LD struktúra az összes kötelező mezővel
    $jsonld = [
        "@context" => "https://schema.org/",
        "@type" => "Product",
        "name" => $product_obj->name,
        "description" => strip_tags($product_obj->description_short ?: $product_obj->description),
        "image" => $image_urls,
        "sku" => $product_obj->reference,
        "mpn" => $product_obj->reference,
        "gtin" => $product_obj->ean13,
        "brand" => [
            "@type" => "Brand",
            "name" => Manufacturer::getNameById((int)$product_obj->id_manufacturer) ?: 'minmag.ro'
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
                    "value" => number_format($shipping_cost, 2, '.', ''),
                    "currency" => $currency
                ],
                "shippingDestination" => [
                    "@type" => "DefinedRegion",
                    "addressCountry" => $country_iso
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
