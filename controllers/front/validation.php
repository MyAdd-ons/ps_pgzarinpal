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
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */
class Ps_pgzarinpalValidationModuleFrontController extends ModuleFrontController
{
    /**
     * This class should be use by your Instant Payment
     * Notification system to validate the order remotely
     */
    public function postProcess()
    {
        /*
         * If the module is not active anymore, no need to process anything.
         */
        if ($this->module->active == false) {
            // Redirect the customer to cart controller
            Tools::redirect($this->context->link->getPageLink('cart?action=show'));
        }

        //Get cart info by Context
        $cart = Context::getContext()->cart;
        $customer = Context::getContext()->customer;

        $amount = $cart->getOrderTotal();
        $description = $customer->firstname . " " . $customer->lastname;
        $email = $customer->email;
        $mobile = (new Address($cart->id_address_invoice))->phone;

        $zarinpal = new ZarinPalGateway();
        try {
            $zarinpal->paymentRequest($amount, $description, $email, $mobile);
        } catch (PrestaShopDatabaseException $e) {
            echo $e->getMessage();
        } catch (PrestaShopException $e) {
            echo $e->getMessage();
        }

        if ( $zarinpal->getPaymentError() ) {
            echo $zarinpal->getPaymentError();
            return $this->setTemplate('module:ps_pgzarinpal/views/templates/front/error.tpl', ['errors' => [$zarinpal->getPaymentError()]]);
        }

        return true;
    }
}