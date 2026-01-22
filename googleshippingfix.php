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
        $this->version = '1.1.0';
        $this->author = 'markoo';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google Merchant Shipping & Return Fix');
        $this->description = $this->l('Automatikusan javítja a szállítási és visszaküldési Schema adatokat a Google számára.');
    }

    public function install()
    {
        // Alapértelmezett értékek mentése telepítéskor
        Configuration::updateValue('GS_RETURN_DAYS', 14);
        return parent::install() && $this->registerHook('displayFooterProduct');
    }

    // Admin felület a beállításokhoz
    public function getContent()
    {
        if (Tools::isSubmit('submit' . $this->name)) {
            Configuration::updateValue('GS_RETURN_DAYS', (int)Tools::getValue('GS_RETURN_DAYS'));
            $this->context->smarty->assign('confirmation', 'Beállítások elmentve!');
        }

        return $this->renderForm();
    }

    protected function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => ['title' => 'Beállítások', 'icon' => 'icon-cogs'],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => 'Visszaküldési határidő (nap)',
                        'name' => 'GS_RETURN_DAYS',
                        'desc' => 'Hány napig küldheti vissza a vásárló a terméket? (Pl. 14)',
                    ],
                ],
                'submit' => ['title' => 'Mentés', 'class' => 'btn btn-default pull-right']
            ],
        ];

        $helper = new HelperForm();
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value['GS_RETURN_DAYS'] = Configuration::get('GS_RETURN_DAYS');
        return $helper->generateForm([$fields_form]);
    }

    public function hookDisplayFooterProduct($params)
{
    $product = $params['product'];
    $currency = $this->context->currency->iso_code;
    
    // Dinamikus országmeghatározás a valuta alapján
    $country_iso = ($currency === 'RON') ? 'RO' : 'HU';
    
    // Szállítási díj lekérése az aktuális pénznemben
    $shipping_cost = $product->getPriceStatic((int)$product->id, true, null, 6, null, false, true, 1, false, null, null, null, $specific_prices, true, true, $this->context, true);

    if ($shipping_cost <= 0) {
        $id_carrier = (int)Configuration::get('PS_CARRIER_DEFAULT');
        $carrier = new Carrier($id_carrier);
        // Itt a PrestaShop a zóna alapú árat fogja visszaadni az aktuális valuta szerint
        $shipping_cost = $carrier->getDeliveryPriceByWeight(0, (int)Context::getContext()->country->id);
    }

    $jsonld = [
        "@context" => "https://schema.org/",
        "@type" => "Product",
        "name" => $product->name,
        "offers" => [
            "@type" => "Offer",
            "priceCurrency" => $currency,
            "price" => number_format($product->getPrice(true), 2, '.', ''),
            "shippingDetails" => [
                "@type" => "OfferShippingDetails",
                "shippingRate" => [
                    "@type" => "MonetaryAmount",
                    "value" => number_format($shipping_cost, 2, '.', ''),
                    "currency" => $currency
                ],
                "shippingDestination" => [
                    "@type" => "DefinedRegion",
                    "addressCountry" => $country_iso // Most már dinamikus: HU vagy RO
                ]
            ],
            "hasMerchantReturnPolicy" => [
                "@type" => "MerchantReturnPolicy",
                "applicableCountry" => $country_iso, // Dinamikus
                "returnPolicyCategory" => "https://schema.org/MerchantReturnFiniteReturnPeriod",
                "merchantReturnDays" => (int)Configuration::get('GS_RETURN_DAYS'),
                "returnMethod" => "https://schema.org/ReturnByMail",
                "returnFees" => "https://schema.org/ReturnFeesCustomerPaying"
            ]
        ]
    ];

    return '<script type="application/ld+json">' . json_encode($jsonld, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
}
}
