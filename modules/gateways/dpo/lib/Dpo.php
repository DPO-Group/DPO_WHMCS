<?php
/*
 * Copyright (c) 2021 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the MIT License
 */

namespace DPOGroup;

class Dpo
{
    const DPO_URL_TEST = 'https://secure.3gdirectpay.com';
    const DPO_URL_LIVE = 'https://secure.3gdirectpay.com';

    private $dpoUrl;
    private $dpoGateway;
    private $testMode = false;

    public function __construct( $testMode = false )
    {
        $this->testMode = $testMode;
        if ( $testMode ) {
            $this->dpoUrl = self::DPO_URL_TEST;
        } else {
            $this->dpoUrl = self::DPO_URL_LIVE;
        }
        $this->dpoGateway = $this->dpoUrl . '/payv2.php?ID=';
    }

    public function getDpoGateway()
    {
        return $this->dpoGateway;
    }

    /**
     * Create a DPO token for payment processing
     * @param $data
     * @return array
     */
    public function createToken( $data )
    {
        $companyToken      = $this->testMode ? '9F416C11-127B-4DE2-AC7F-D5710E4C5E0A' : $data['companyToken'];
        $accountType       = $this->testMode ? '3854' : $data['accountType'];
        $paymentAmount     = $data['paymentAmount'];
        $paymentCurrency   = $data['paymentCurrency'];
        $customerFirstName = $data['customerFirstName'];
        $customerLastName  = $data['customerLastName'];
        $customerAddress   = $data['customerAddress'];
        $customerCity      = $data['customerCity'];
        $customerPhone     = preg_replace( '/[^0-9]/', '', $data['customerPhone'] );
        $redirectURL       = $data['redirectURL'];
        $backURL           = $data['backUrl'];
        $customerEmail     = $data['customerEmail'];
        $reference         = $data['companyRef'];

        $odate   = date( 'Y/m/d H:i' );
        $postXml = <<<POSTXML
        <?xml version="1.0" encoding="utf-8"?> <API3G> <CompanyToken>$companyToken</CompanyToken> <Request>createToken</Request> <Transaction> <PaymentAmount>$paymentAmount</PaymentAmount> <PaymentCurrency>$paymentCurrency</PaymentCurrency> <CompanyRef>$reference</CompanyRef> <customerFirstName>$customerFirstName</customerFirstName> <customerLastName>$customerLastName</customerLastName> <customerAddress>$customerAddress</customerAddress> <customerCity>$customerCity</customerCity> <customerPhone>$customerPhone</customerPhone> <RedirectURL>$redirectURL</RedirectURL> <BackURL>$backURL</BackURL> <customerEmail>$customerEmail</customerEmail> <TransactionSource>whmcs</TransactionSource> </Transaction> <Services> <Service> <ServiceType>$accountType</ServiceType> <ServiceDescription>$reference</ServiceDescription> <ServiceDate>$odate</ServiceDate> </Service> </Services> </API3G>
POSTXML;

        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => $this->dpoUrl . "/API/v6/",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => $postXml,
            CURLOPT_HTTPHEADER     => array(
                "cache-control: no-cache",
            ),
        ) );

        $response = curl_exec( $curl );
        $error    = curl_error( $curl );

        curl_close( $curl );

        if ( $response != '' ) {
            $xml               = new \SimpleXMLElement( $response );
            $result            = $xml->xpath( 'Result' )[0]->__toString();
            $resultExplanation = $xml->xpath( 'ResultExplanation' )[0]->__toString();
            $returnResult      = [
                'result'            => $result,
                'resultExplanation' => $resultExplanation,
            ];

            // Check if token was created successfully
            if ( $xml->xpath( 'Result' )[0] != '000' ) {
                $returnResult['success'] = 'false';
            } else {
                $transToken                 = $xml->xpath( 'TransToken' )[0]->__toString();
                $transRef                   = $xml->xpath( 'TransRef' )[0]->__toString();
                $returnResult['success']    = 'true';
                $returnResult['transToken'] = $transToken;
                $returnResult['transRef']   = $transRef;
            }
            return $returnResult;
        } else {
            return [
                'success'           => false,
                'result'            => !empty( $error ) ? $error : 'Unknown error occurred in token creation',
                'resultExplanation' => !empty( $error ) ? $error : 'Unknown error occurred in token creation',
            ];
        }
    }

    /**
     * Verify the DPO token created in first step of transaction
     * @param $data
     * @return bool|string
     */
    public function verifyToken( $data )
    {
        $companyToken = $data['companyToken'];
        $transToken   = $data['transToken'];

        try {
            $curl = curl_init();
            curl_setopt_array( $curl, array(
                CURLOPT_URL            => $this->dpoUrl . "/API/v6/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => "POST",
                CURLOPT_POSTFIELDS     => "<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<API3G>\r\n  <CompanyToken>" . $companyToken . "</CompanyToken>\r\n  <Request>verifyToken</Request>\r\n  <TransactionToken>" . $transToken . "</TransactionToken>\r\n</API3G>",
                CURLOPT_HTTPHEADER     => array(
                    "cache-control: no-cache",
                ),
            ) );

            $response = curl_exec( $curl );
            $err      = curl_error( $curl );

            curl_close( $curl );

            if ( strlen( $err ) > 0 ) {
                echo "cURL Error #:" . $err;
            } else {
                return $response;
            }
        } catch ( Exception $e ) {
            throw $e;
        }
    }
}
