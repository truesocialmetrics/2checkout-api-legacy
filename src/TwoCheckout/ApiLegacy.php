<?php
namespace Twee\TwoCheckout;

use RuntimeException;
use InvalidArgumentException;

final class ApiLegacy
{
    private $vendorCode = '';
    private $secretCode = '';

    public function __construct(string $vendorCode, string $secretCode)
    {
        $this->vendorCode = $vendorCode; //merchant code is available on this page https://secure.2checkout.com/cpanel/account_settings.php
        $this->secretCode = $secretCode; //secret key is available on this page https://secure.2checkout.com/cpanel/account_settings.php
    }

    public function rest(array $options)
    {
        //*********SETTING PARAMETERS*********
        $link_params = [];

        //REQUIRED, CANNOT BE EMPTY:
        $link_params['MERCHANT'] = $this->vendorCode;
        if (!array_key_exists('STARTDATE', $options)) {
            throw new InvalidArgumentException('Missed STARTDATE');
        }
        if (!array_key_exists('ENDDATE', $options)) {
            throw new InvalidArgumentException('Missed ENDDATE');
        }

        $link_params['STARTDATE'] = $options['STARTDATE'];
        $link_params['ENDDATE'] = $options['ENDDATE'];

        $link_params['ORDERSTATUS'] = 'ALL'; // replace with any of  ALL, COMPLETE, REFUNDED, UNFINISHED
        $link_params['REQ_DATE'] = date('YmdHis');

        //CAN BE EMPTY:
        $link_params['PRODUCT_ID'] = '';
        $link_params['COUNTRY_CODE'] = '';
        $link_params['FILTER_STRING'] = array_key_exists('FILTER_STRING', $options) ? $options['FILTER_STRING'] : '';

        //REQUIRED, CAN BE EMPTY:
        $link_params['FILTER_FIELD'] = array_key_exists('FILTER_FIELD', $options) ? $options['FILTER_FIELD'] : ''; // EMPTY OR: REFNO, REFNOEXT, NAME, EMAIL, COUPONCODE

        //REQUIRED:
        $link_params['HASH'] = '';

        //OPTIONAL:
        $link_params['INCLUDE_DELIVERED_CODES'] = '';
        $link_params['INCLUDE_FINANCIAL_DETAILS'] = '';
        $link_params['INCLUDE_EXCHANGE_RATES'] = '';
        $link_params['INCLUDE_PRICING_OPTIONS'] = '';
        $link_params['EXPORT_FORMAT'] = 'XML'; //possible values CSV or XML -    if you’re using this sample, please specify the desired export format
        $link_params['EXPORT_TIMEZONE_REGION'] = 'Europe/London';


        return $this->query($link_params);
    }

    private function query(array $link_params)
    {
        $base_link = 'https://secure.2checkout.com/action/ise.php';
        $not_in_hash = ['HASH','INCLUDE_DELIVERED_CODES','INCLUDE_FINANCIAL_DETAILS','INCLUDE_EXCHANGE_RATES','INCLUDE_PRICING_OPTIONS','EXPORT_FORMAT','EXPORT_TIMEZONE_REGION'];

        //*********GET Base string for HMAC_MD5 calculation:*********
        $result = '';
        while (list($key, $val) = each($link_params)) {
            $$key = $val;
            /* get values */
            if (!in_array($key, $not_in_hash)) {
                if (is_array($val)) {
                    $result .= $this->ArrayExpand($val);
                } else {
                    $size = strlen(StripSlashes($val));
                    $result .= $size . StripSlashes($val);
                }
            }
        }


        //*********Calculated HMAC_MD5 signature:*********
        $hash = $this->hmac($this->secretCode, $result);
        $link_params['HASH'] = $hash;

        $get_vars = http_build_query($link_params, '', '&');


        //*********MAKE POST CALL to get ISE results*********
        $ch = curl_init($base_link . '?' . $get_vars);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);  // RETURN THE CONTENTS OF THE CALL
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $responseData = curl_exec($ch);
        $headerCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        //echo 'Curl error: '.curl_error($ch);
        curl_close($ch);

        if ($headerCode != 200) {
            throw new RuntimeException($responseData, $headerCode);
        }

        $xml = simplexml_load_string($responseData, 'SimpleXMLElement', LIBXML_NOCDATA);
        $json = json_encode($xml);

        return json_decode($json, true);
    }

    //*********FUNCTIONS FOR HMAC*********
    private function ArrayExpand(array $array)
    {
        $retval = "";
        foreach ($array as $i => $value) {
            if (is_array($value)) {
                $retval .= $this->ArrayExpand($value);
            } else {
                $size = strlen(StripSlashes($value));
                $retval .= $size . StripSlashes($value);
            }
        }

        return $retval;
    }

    private function hmac($key, $data)
    {
        $b = 64; // byte length for md5
        if (strlen($key) > $b) {
            $key = pack("H*", md5($key));
        }
        $key = str_pad($key, $b, chr(0x00));
        $ipad = str_pad('', $b, chr(0x36));
        $opad = str_pad('', $b, chr(0x5c));
        $k_ipad = $key ^ $ipad ;
        $k_opad = $key ^ $opad;
        return md5($k_opad . pack("H*", md5($k_ipad . $data)));
    }
}
