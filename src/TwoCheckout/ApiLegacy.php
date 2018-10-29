<?php
namespace Twee\TwoCheckout;

final class ApiLegacy
{
    private $vendorCode = '';
    private $secretCode = '';

    public function __construct(string $vendorCode, string $secretCode)
    {
        $this->vendorCode = $vendorCode;
        $this->secretCode = $secretCode;
    }

    public function rest(array $options)
    {
        $secret_key = $this->secretCode; //secret key is available on this page https://secure.2checkout.com/cpanel/account_settings.php
        $base_link = 'https://secure.2checkout.com/action/ise.php';
        date_default_timezone_set('UTC');


        //*********SETTING PARAMETERS*********
        // $link_params = array();
        $not_in_hash = ['HASH','INCLUDE_DELIVERED_CODES','INCLUDE_FINANCIAL_DETAILS','INCLUDE_EXCHANGE_RATES','INCLUDE_PRICING_OPTIONS','EXPORT_FORMAT','EXPORT_TIMEZONE_REGION'];

        //REQUIRED, CANNOT BE EMPTY:
        $link_params['MERCHANT'] = $this->vendorCode; //merchant code is available on this page https://secure.2checkout.com/cpanel/account_settings.php
        $link_params['STARTDATE'] = date("Y-m-d", strtotime('-1 month', strtotime(date('Y') . '/' . date('m') . '/01' . ' 00:00:00'))); //first day from last month
        $link_params['ENDDATE'] = date("Y-m-d", strtotime('-1 second', strtotime(date('Y') . '/' . date('m') . '/01' . ' 00:00:00'))); //last day from last month

        $link_params['ORDERSTATUS'] = 'ALL'; // replace with any of  ALL, COMPLETE, REFUNDED, UNFINISHED
        $link_params['REQ_DATE'] = date('YmdHis');

        //CAN BE EMPTY:
        $link_params['PRODUCT_ID'] = '';
        $link_params['COUNTRY_CODE'] = '';
        $link_params['FILTER_STRING'] = '';

        //REQUIRED, CAN BE EMPTY:
        $link_params['FILTER_FIELD'] = ''; // EMPTY OR: REFNO, REFNOEXT, NAME, EMAIL, COUPONCODE

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
        $hash = $this->hmac($secret_key, $result);
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


        //*********PROCESS RESULTS*********
        if ($headerCode == 200) {
            // do something with the csv or xml received
            //the format of the export file is set using $link_params['EXPORT_FORMAT']
            $exportType = strtolower($link_params['EXPORT_FORMAT']);
            $headerType = 'Content-type: application/' . $exportType . ';charset=UTF-8';
            $headerDisposition = 'Content-Disposition: attachment; filename="ise.' . $exportType . '"';
            header($headerType);
            header($headerDisposition);
            echo $responseData;
        } else {
            //no valid answer received: request period is too big, etc.
            if (strpos($contentType, 'xml') === false) {
                echo 'Header returned: ' . $headerCode;
                echo $responseData;
            } else {
                //YOUR CODE HERE AFTER RECEIVING the xml with one of the codes from Instant Search Export Handbook
                $xml = $responseData;
                $xml = simplexml_load_string($xml);
                $response = [];
                $i = 0;
                foreach ($xml->children() as $child) {
                    $response[$i] = $child;
                    $i++;
                }
                echo $xml->asXML();
            }
        }
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
