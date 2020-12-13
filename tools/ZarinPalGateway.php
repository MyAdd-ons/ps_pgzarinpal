<?php


class ZarinPalGateway
{
    /**
     * @var string
     */
    private $error;

    /**
     * @var string
     */
    private $merchant_id = "XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX";

    /**
     * @var string
     */
    private $sandbox = "www";

    /**
     * @var string
     */
    private $zaringate;

    /**
     * Payment request callback
     * @var string
     */
    private $callback;

    /**
     * shop current currency
     * @var boolean
     */
    private $currency = false;

    /**
     * ZarinPalGateway constructor
     * Sets class requirements
     * Checks for shop currency
     */
    public function __construct()
    {
        //Check for SandBox
        if (Configuration::get("ZARINPAL_SANDBOX") == 1) {
            $this->sandbox = "sandbox";
            $this->merchant_id = Configuration::get("ZARINPAL_MERCHANT_ID");
        }

        //Check for ZarinGate
        if (Configuration::get("ZARINPAL_ZARINGATE") == 1) {
            $this->zaringate = "/ZarinGate";
        }

        $this->callback = Context::getContext()->shop->getBaseURL() . "module/ps_pgzarinpal/confirmation";

        //Check for shop currency
        if (Context::getContext()->currency->iso_code == "IRR" && Context::getContext()->currency->numeric_iso_code == 364) {
            $this->currency = "IRR";
        }

        if (Context::getContext()->currency->iso_code == "IRT" && Context::getContext()->currency->numeric_iso_code == 365) {
            $this->currency = "IRT";
        }
    }

    /**
     * Sends a payment request to ZarinPal
     * Return an error message on failure that you must get the error via getPaymentError()
     *
     * @param int $amount
     * @param string $description
     * @param string $email
     * @param string $mobile
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function paymentRequest($amount, $description, $email = "", $mobile = "")
    {
        //Check that currency is IRR or IRT
        if ($this->currency === false) {
            $this->error = $this->l("Shop currency must be IRR or IRT");

            return false;
        }

        //Convert IRR to IRT because ZarinPal excepts just IRT
        if ($this->currency == "IRR") {
            $amount = $this->convertToIrt($amount);
        }

        //Gate data
        $data = [
            'MerchantID' => $this->merchant_id,
            'Amount' => (int)$amount,
            'CallbackURL' => $this->callback,
            'Description' => $description,
            'Email' => $email,
            'Mobile' => $mobile,
        ];
        $jsonData = json_encode($data);

        $ch = curl_init("https://{$this->sandbox}.zarinpal.com/pg/rest/WebGate/PaymentRequest.json");
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));

        $result = curl_exec($ch);
        $err = curl_error($ch);
        $result = json_decode($result, true);
        curl_close($ch);

        //if there is an cURL error
        if ($err) {
            $this->error = "cURL Error : " . $err;

            return false;
        } else {
            //Check for gate result
            if ($result["Status"] == 100) {
                /*
                 * If payment failed update new authority or creat new one
                 */
                $cart_id = Context::getContext()->cart->id;
                if (PgzarinpalModel::cartIdExists($cart_id)) {
                    $id_pgzarinpal = PgzarinpalModel::getIdByCartId($cart_id);
                    $pgzarinpal = new PgzarinpalModel($id_pgzarinpal);

                    $pgzarinpal->authority = $result["Authority"];
                    $authority_result = $pgzarinpal->update();
                } else {
                    $pgzarinpal = new PgzarinpalModel();

                    $pgzarinpal->cart_id = (int)$cart_id;
                    $pgzarinpal->authority = $result["Authority"];
                    $authority_result = $pgzarinpal->save();
                }

                /*
                 * Redirect to payment page if authority saved
                 */
                if ($authority_result) {
                    //redirect to gate
                    header("Location: https://{$this->sandbox}.zarinpal.com/pg/StartPay/{$result["Authority"]}{$this->zaringate}");
                    return true;
                } else {
                    $this->error = $this->l("Failed to save payment info");

                    return false;
                }
            } else {
                $this->error = $result["Status"];

                return false;
            }
        }
    }

    /**
     * verify the payment request
     * Return an error message on failure that you must get the error via getPaymentError()
     * Return reference ID on success
     *
     * @param int $amount
     * @param string $authority
     *
     * @return bool|string
     */
    public function verifyPayment($amount, $authority)
    {
        //Check that currency is IRR or IRT
        if ($this->currency === false) {
            $this->error = $this->l("Shop currency must be IRR or IRT");

            return false;
        }

        //Convert IRR to IRT because ZarinPal accepts just IRT
        if ($this->currency == "IRR") {
            $amount = $this->convertToIrt($amount);
        }

        //Gate payment verification data
        $data = [
            'MerchantID' => $this->merchant_id,
            'Authority' => $authority,
            'Amount' => (int)$amount
        ];
        $jsonData = json_encode($data);

        $ch = curl_init("https://{$this->sandbox}.zarinpal.com/pg/rest/WebGate/PaymentVerification.json");
        curl_setopt($ch, CURLOPT_USERAGENT, 'ZarinPal Rest Api v1');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonData)
        ));

        $result = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        $result = json_decode($result, true);

        //if there is an cURL error
        if ($err) {
            $this->error = "cURL Error : " . $err;

            return false;
        } else {
            /*
             * Check for verification result
             */
            if ($result['Status'] == 100) {
                return $result['RefID'];
            } else {
                $this->error = 'An error occurred while verifying payment : ' . $result['Status'];

                return false;
            }
        }
    }

    /**
     * Handle ZarinPal errors
     * Return error message if there is an error
     *
     * @return bool|string
     */
    public function getPaymentError()
    {
        switch ($this->error) {
            case "-1":
                return 'اطلاعات ارسال شده ناقص است';
                break;
            case "-2":
                return 'IP و يا مرچنت كد پذيرنده صحيح نيست';
                break;
            case "-3":
                return 'با توجه به محدوديت هاي شاپرك امكان پرداخت با رقم درخواست شده ميسر نمي باشد';
                break;
            case "-4":
                return 'سطح تاييد پذيرنده پايين تر از سطح نقره اي است';
                break;
            case "-11":
                return 'درخواست مورد نظر يافت نشد';
                break;
            case "-12":
                return 'امكان ويرايش درخواست ميسر نمي باشد';
                break;
            case "-21":
                return 'هيچ نوع عمليات مالي براي اين تراكنش يافت نشد';
                break;
            case "-22":
                return 'تراكنش نا موفق مي باشد';
                break;
            case "-33":
                return 'رقم تراكنش با رقم پرداخت شده مطابقت ندارد';
                break;
            case "-34":
                return 'سقف تقسيم تراكنش از لحاظ تعداد يا رقم عبور نموده است';
                break;
            case "-40":
                return 'اجازه دسترسي به متد مربوطه وجود ندارد';
                break;
            case "-41":
                return 'اطلاعات ارسال شده مربوط به AdditionalData غيرمعتبر مي باشد .';
                break;
            case "-42":
                return 'مدت زمان معتبر طول عمر  شناسه  پرداخت بايد بين 30دقيه تا 45روز مي باش';
                break;
            case "-54":
                return 'درخواست مورد نظر آرشيو شده است .';
                break;
            case "101":
                return 'عمليات پرداخت موفق بوده وقبلا PaymentVerification تراكنش انجام شده است';
                break;
            case "100":
                return 'عملیات با موفقیت انجام شد';
                break;
        }

        if ( is_string($this->error) ) {
            return $this->error;
        }

        return false;
    }

    /**
     * Convert IRR to IRT
     *
     * @param int|string|float $amount
     *
     * @return float|int
     */
    public function convertToIrt($amount)
    {
        return (int)$amount / 10;
    }
}
