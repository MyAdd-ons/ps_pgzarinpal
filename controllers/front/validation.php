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
        if ( $this->module->active == false ) {
            // Redirect the customer to cart controller
            Tools::redirect($this->context->link->getPageLink('cart?action=show'));
        }

        $errors = "";

        //Get cart info by Context
        $cart     = Context::getContext()->cart;
        $shop     = Context::getContext()->shop;
        $customer = Context::getContext()->customer;

        $email       = $customer->email;
        $mobile      = (new Address($cart->id_address_invoice))->phone_mobile;
        $amount      = (int)$cart->getOrderTotal();
        $description = $customer->firstname . " " . $customer->lastname;

        $sandbox     = "www";
        $callback    = $shop->getBaseURL() . "module/ps_pgzarinpal/confirmation";
        $zaringate   = "";
        $merchant_id = Configuration::get("ZARINPAL_MERCHANT_ID");

        //Check for SandBox
        if( Configuration::get("ZARINPAL_SANDBOX") == 1 ) {
            $sandbox = "sandbox";
            $merchant_id = "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx";
        }

        //Check for ZarinGate
        if( Configuration::get("ZARINPAL_ZARINGATE") == 1 ) {
            $zaringate = "/ZarinGate";
        }

        //Gate data
        $data = [
            'MerchantID'  => $merchant_id,
            'Amount'      => $amount,
            'CallbackURL' => $callback,
            'Description' => $description,
            'Email'       => $email,
            'Mobile'      => $mobile,
        ];
        $jsonData = json_encode($data);

        $ch = curl_init("https://{$sandbox}.zarinpal.com/pg/rest/WebGate/PaymentRequest.json");
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
        $result = json_decode($result, true);
        curl_close($ch);

        //if there is an cURL error
        if ($err) {
            $errors = "cURL Error : " . $err;
        } else {
            //Check for gate result
            if ( $result["Status"] == 100 ) {

                /*
                 * If payment failed update new authority or creat new one
                 */
                if ( PgzarinpalModel::cartIdExists($cart->id) ) {
                    $id_pgzarinpal         = PgzarinpalModel::getIdByCartId($cart->id);
                    $pgzarinpal            = new PgzarinpalModel($id_pgzarinpal);

                    $pgzarinpal->authority = $result["Authority"];
                    $pgzarinpal->update();
                } else {
                    $pgzarinpal            = new PgzarinpalModel();

                    $pgzarinpal->cart_id   = (int)$cart->id;
                    $pgzarinpal->authority = $result["Authority"];
                    $pgzarinpal->save();
                }

                //redirect to gate
                header("Location: https://{$sandbox}.zarinpal.com/pg/StartPay/{$result["Authority"]}{$zaringate}");
                exit;
            } else {
                $error_code = $result["Status"];

                /*
                 * Handel gate errors
                 */
                switch ($error_code) {
                    case "-1" :
                        $errors = $this->l('اطلاعات ارسال شده ناقص است');
                        break;
                    case "-2" :
                        $errors = $this->l('آی پی یا مرچنت کد پذیرنده صحیح نیست');
                        break;
                    case "-3" :
                        $errors = $this->l('اطلاعات ارسال شده ناقص است');
                        break;
                    case "-9" :
                        $errors = $this->l('خطای اعتبار سنجی');
                        break;
                    case "-10" :
                        $errors = $this->l('ای پی و يا مرچنت كد پذيرنده صحيح نيست');
                        break;
                    case "-11" :
                        $errors = $this->l('مرچنت کد فعال نیست لطفا با تیم پشتیبانی ما تماس بگیرید');
                        break;
                    case "-12" :
                        $errors = $this->l('تلاش بیش از حد در یک بازه زمانی کوتاه');
                        break;
                    case "-15" :
                        $errors = $this->l('ترمینال شما به حالت تعلیق در آمده با تیم پشتیبانی تماس بگیرید');
                        break;
                    case "-16" :
                        $errors = $this->l('سطح تاييد پذيرنده پايين تر از سطح نقره اي است');
                        break;
                }
            }
        }

        //Check for errors
        if ( $errors ) {
            $this->errors[] = $errors . " لطفا دوباره تلاش کنید ";

            return $this->setTemplate('module:ps_pgzarinpal/views/templates/front/error.tpl');
        }

        return true;
    }
}