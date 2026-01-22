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
        // Hibatűrő adatlekérés: megnézzük, hogy objektum vagy tömb-e a termék
        $product_obj = null;
        if (isset($params['product']) && is_object($params['product'])) {
            $product_obj = $params['product'];
        } elseif (isset($params['product']['id_product'])) {
            $product_obj = new Product((int)$params['product']['id_product'], true, (int)$this->context->language->id);
        }

        if (!$product_obj) {
            return '';
        }

        $currency = $this->context->currency->iso_code;
        $country_iso = ($currency === 'RON') ? 'RO' : 'HU';

        // Szállítási díj egyszerűsített lekérése
        $id_product = (int)$product_obj->id;
        $shipping_cost = Product::getPriceStatic($id_product, true, null, 6, null, false, true);

        // Ha nincs egyedi szállítási díj, az alapértelmezett szállító árát vesszük
        if ($shipping_cost <= 0) {
            $id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');
            $carrier = new Carrier($id_carrier);
            $shipping_cost = $carrier->getDeliveryPriceByWeight(0, (int)Context::getContext()->country->id);
        }

        $jsonld = [
            "@context" => "https://schema.org/",
            "@type" => "Product",
            "name" => is_object($product_obj) ? $product_obj->name : $params['product']['name'],
            "offers" => [
                "@type" => "Offer",
                "priceCurrency" => $currency,
                "price" => number_format(Product::getPriceStatic($id_product, true), 2, '.', ''),
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
                    "returnFees" => "https://schema.org/ReturnFeesCustomerPaying"
                ]
            ]
        ];

        return '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
    }
}
