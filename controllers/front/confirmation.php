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
class Ps_pgzarinpalConfirmationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $status  = $_GET['Status'];
        $errors  = false;
        $success = false;

        /*
         * Check if cart purchased successfully
         */
        if ( $status == "OK" ) {
            $authority = $_GET['Authority'];
            $sandbox   = "www";

            //Get cart id by authority
            $id_pgzarinpal = PgzarinpalModel::getIdByAuthority($authority);
            try {
                $pgzarinpal = new PgzarinpalModel($id_pgzarinpal);
            } catch (PrestaShopDatabaseException $e) {
                $errors = $e->getMessage();
            } catch (PrestaShopException $e) {
                $errors = $e->getMessage();
            }

            /*
             * Restore cart info
             */
            $cart        = new Cart($pgzarinpal->cart_id);
            $customer    = new Customer($cart->id_customer);
            $merchant_id = Configuration::get("ZARINPAL_MERCHANT_ID");
            try {
                $amount = $cart->getOrderTotal();
            } catch (Exception $e) {
                $errors = $e->getMessage();
            }

            if( Configuration::get("ZARINPAL_SANDBOX") == 1 ) {
                $sandbox     = "sandbox";
                $merchant_id = "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx";
            }

            //Gate payment verification data
            $data = [
                'MerchantID' => $merchant_id,
                'Authority'  => $authority,
                'Amount'     => $amount
            ];
            $jsonData = json_encode($data);

            $ch = curl_init("https://{$sandbox}.zarinpal.com/pg/rest/WebGate/PaymentVerification.json");
            curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ));

            $result = curl_exec($ch);
            $err    = curl_error($ch);
            curl_close($ch);
            $result = json_decode($result, true);

            //if there is an cURL error
            if ( $err ) {
                $errors = "cURL Error : " . $err;
            } else {
                /*
                 * Check for verification result
                 * @TODO SMS user the result of payment
                 */
                if ($result['Status'] == 100) {
                    $message        = $this->l("Payment reference ID is {$result['RefID']}");
                    $payment_status = Configuration::get('PS_OS_PAYMENT');

                    $success = 'Transation success. RefID : ' . $result['RefID'];
                } else {
                    $payment_status = Configuration::get('PS_OS_ERROR');

                    /**
                     * Add a message to explain why the order has not been validated
                     */
                    $message = $this->module->l("An error occurred while verifying payment {$result['Status']}");

                    $errors = 'Transation failed. Status : ' . $result['Status'];
                }

                /**
                 * Converting cart into a valid order
                 */
                $module_name = $this->module->displayName;
                $currency_id = $cart->id_currency;
                $secure_key  = $cart->secure_key;

                $this->module->validateOrder($cart->id, $payment_status, $amount, $module_name, $message, array(), $currency_id, false, $secure_key);

                /**
                 * If the order has been validated we try to retrieve it
                 */
                $order_id = Order::getIdByCartId((int) $cart->id);

                if ( $order_id && ($secure_key == $customer->secure_key )) {
                    /**
                     * The order has been placed so we redirect the customer on the confirmation page.
                     */
                    $module_id = $this->module->id;

                    Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cart->id . '&id_module=' . $module_id . '&id_order=' . $order_id . '&key=' . $secure_key);
                } else {
                    /*
                     * An error occured and is shown on a new page.
                     */
                    $errors = $this->module->l('An error occured. Please contact the merchant to have more informations');
                }
            }
        } else {
            $errors = $this->module->l('An error occured. Failed to verify payment');
        }

        if ( $errors ) {
            $this->errors[] = $errors;

            return $this->setTemplate('module:ps_pgzarinpal/views/templates/front/error.tpl');
        }

        if ( $success ) {
            $this->success[] = "success";

            return $this->setTemplate('module:ps_pgzarinpal/views/templates/front/error.tpl');
        }
    }
}
