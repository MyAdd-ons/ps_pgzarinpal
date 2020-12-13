<?php
/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

defined('_PS_VERSION_') || exit;

require_once(dirname(__FILE__).'/models/PgzarinpalModel.php');
require_once(dirname(__FILE__).'/tools/ZarinPalGateway.php');

class Ps_pgzarinpal extends PaymentModule
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'ps_pgzarinpal';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'MyAdd-ons';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('ZarinPal Payment Gateway');
        $this->description = $this->l('ZarinPal Payment Gateway for Prestashop');

        $this->limited_countries = ['IR'];

        $this->ps_versions_compliancy = [
            'min' => '1.7',
            'max' => _PS_VERSION_
        ];
    }

    /**
     * Installs module requirements, such as hooks, models, etc.
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        if (in_array($iso_code, $this->limited_countries) == false)
        {
            $this->_errors[] = $this->l('This module is not available in your country');
            return false;
        }

        require_once(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayPayment') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->installTabs();
    }

    /**
     * Uninstall module models and tabs.
     */
    public function uninstall()
    {
        require_once(dirname(__FILE__).'/sql/uninstall.php');

        $form_values = $this->getConfigForm();

        foreach ($form_values['form']['input'] as $key) {
            $key = $key['name'];
            Configuration::deleteByName($key);
        }

        return parent::uninstall() &&
            $this->unInstallTabs();
    }

    /**
     * install module tabs in sidebar
     */
    private function installTabs()
    {
        $tabParent = new Tab();
        $tabParent->name[$this->context->language->id] = $this->l($this->displayName);
        $tabParent->class_name = 'ModuleConfiguration';
        $tabParent->id_parent = 0;
        $tabParent->module = $this->name;
        $tabParent->save();

        $tabSettings = new Tab();
        $tabSettings->name[$this->context->language->id] = $this->l('Setting');
        $tabSettings->class_name = 'ModuleConfiguration';
        $tabSettings->icon = 'settings';
        $tabSettings->id_parent = $tabParent->id;
        $tabSettings->module = $this->name;
        $tabSettings->save();

        return true;
    }

    /**
     * Uninstall module tabs.
     */
    private function unInstallTabs()
    {
        $moduleTabs = Tab::getCollectionFromModule($this->name);
        if (!empty($moduleTabs)) {
            foreach ($moduleTabs as $moduleTab) {
                try {
                    $moduleTab->delete();
                } catch (PrestaShopException $e) {
                    echo $e->getMessage();
                }
            }
        }

        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('saveSetting')) == true) {
            $this->postProcess();
        }

        return $this->getMessages() . $this->renderForm();
    }

    /**
     * Display error and confirmation messages
     *
     * @return string
     */
    private function getMessages()
    {
        $messages = '';

        if ( count($this->getErrors()) ) {
            $messages .= $this->displayError($this->getErrors());
        }

        if ( count($this->getConfirmations()) ) {
            $confirmMessage = '';

            foreach ($this->getConfirmations() as $confirmation) {
                $confirmMessage .= $confirmation . "<br />";
            }

            $messages .= $this->displayConfirmation($confirmMessage);
        }

        return $messages;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'saveSetting';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                'title'  => $this->l('Settings'),
                'icon'   => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type'  => 'text',
                        'col'   => 3,
                        'label' => $this->l('Merchant ID'),
                        'name'  => 'ZARINPAL_MERCHANT_ID',
                        'size'  => 36,
                        'required' => true,
                        'placeholder' => 'XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX',
                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Zarin Gate ? '),
                        'name'   => 'ZARINPAL_ZARINGATE',
                        'desc'   => $this->l('User will directly move to payment gateway'),
                        'values' => [
                            [
                                'value' => 0,
                            ],
                            [
                                'value' => 1,
                            ],
                        ],

                    ],
                    [
                        'type'   => 'switch',
                        'label'  => $this->l('Sand Box ? '),
                        'name'   => 'ZARINPAL_SANDBOX',
                        'desc'   => $this->l('Payment simulator environment '),
                        'hint' => $this->l('( Sandbox mode represents a test environment that allows to order without really paying for it ) ( Enable it just for test purposes ) ( !Do not enable it if your shop is online ) '),
                        'values' => [
                            [
                                'value' => 0,
                            ],
                            [
                                'value' => 1,
                            ],
                        ],
                    ],
                ],
                'submit'    => [
                    'title' => $this->l('Save Setting'),
                    'name'  => 'saveSetting'
                ],
            ],
        ];
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $fields = [];
        $form_values = $this->getConfigForm();

        foreach ($form_values['form']['input'] as $key) {
            $key = $key['name'];
            $fields[$key] = Configuration::get($key);
        }

        return $fields;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigForm();

        $res = [];

        foreach ($form_values['form']['input'] as $key) {
            $key = $key['name'];
            $res[] = Configuration::updateValue($key, Tools::getValue($key));
        }

        if ( in_array(0, $res) )
            $this->_errors[] = $this->l('Failed to save setting');
        else
            $this->_confirmations[] = $this->l('Configuration saved successfully');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l($this->displayName))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        return [
            $option
        ];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function hookDisplayPayment()
    {
        /* Place your code here. */
    }

    public function hookDisplayPaymentReturn()
    {
        /* Place your code here. */
    }
}
