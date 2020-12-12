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
class Ps_pgzarinpalConfirmationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $status = Tools::getValue("Status");
        $error = "";

        /*
         * Check if cart purchased successfully
         */
        if ($status == "OK") {
            $authority = Tools::getValue("Authority");

            //Get cart id by authority
            $id_pgzarinpal = PgzarinpalModel::getIdByAuthority($authority);
            try {
                $pgzarinpal = new PgzarinpalModel($id_pgzarinpal);
            } catch (PrestaShopDatabaseException $e) {
                $error = $e->getMessage();
            } catch (PrestaShopException $e) {
                $error = $e->getMessage();
            }

            //Restore cart and amount
            $cart = new Cart($pgzarinpal->cart_id);
            $customer = new Customer($cart->id_customer);
            $amount = $cart->getOrderTotal();

            //verify the payment
            $zarinpal = new ZarinPalGateway();
            $verify = $zarinpal->verifyPayment($amount, $authority);

            /*
             * update order status if verified and there is no payment error
             */
            if ( is_numeric($verify) && !$zarinpal->getPaymentError()) {
                $payment_status = Configuration::get('PS_OS_PAYMENT');
                $message = "Payment verified success fully. reference ID : {$verify}";

                /*
                 * Converting cart into a valid order
                 */
                $module_name = $this->module->displayName;
                $currency_id = $cart->id_currency;
                $secure_key = $cart->secure_key;

                $this->module->validateOrder($cart->id, $payment_status, $amount, $module_name, $message, array(), $currency_id, false, $secure_key);

                /*
                 * If the order has been validated we try to retrieve it
                 */
                $order_id = Order::getIdByCartId((int)$cart->id);

                if ($order_id && ($secure_key == $customer->secure_key)) {
                    /*
                     * The order has been placed so we redirect the customer on the confirmation page.
                     */
                    $module_id = $this->module->id;

                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $module_id . '&id_order=' . $order_id . '&key=' . $secure_key);
                } else {
                    /*
                     * An error occured and is shown on a new page.
                     */
                    $error = $this->module->l('An error occured. Please contact the merchant to have more informations');
                }
            } else {
                return $this->setTemplate('module:ps_pgzarinpal/views/templates/front/error.tpl', ['errors' => [$zarinpal->getPaymentError()]]);
            }
        } else {
            return $this->setTemplate('module:ps_pgzarinpal/views/templates/front/error.tpl', ['errors' => $error]);
        }
        return true;
    }
}
