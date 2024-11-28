<?php
/*
 * Copyright (c) 2024 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the MIT License
 */

namespace DPOGroup;

use Dpo\Common\Dpo as DpoCommon;
use Exception;
use SimpleXMLElement;
use WHMCS\Database\Capsule;

use function logActivity;
use function logTransaction;

require_once __DIR__ . '/../../../../../init.php';
require_once __DIR__ . '/../../../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../../../includes/invoicefunctions.php';

class DPOPaymentService
{
    const DPO_URL_LIVE = 'https://secure.3gdirectpay.com';
    const DB_PREFIX    = 'tbl';

    private string $dpoUrl;
    private string $dpoGateway;

    public function __construct()
    {
        $this->dpoUrl     = self::DPO_URL_LIVE;
        $this->dpoGateway = $this->dpoUrl . '/payv2.php?ID=';
    }

    /**
     * @return string
     */
    public function getDpoGateway(): string
    {
        return $this->dpoGateway;
    }

    /**
     * @param $params
     *
     * @return void
     * @throws Exception
     */
    public function dpoInitiate($params): void
    {
        // Callback urls
        $systemUrl = $params['systemurl'];
        $returnUrl = $systemUrl . 'modules/gateways/callback/dpo.php';
        $dpoCommon = new DPOCommon(false);

        $data                      = [];
        $data['companyToken']      = $params['CompanyToken'];
        $data['serviceType']       = $params['AccountType'];
        $data['paymentAmount']     = $params['amount'];
        $data['paymentCurrency']   = $params['currency'];
        $data['customerFirstName'] = $params['clientdetails']['firstname'];
        $data['customerLastName']  = $params['clientdetails']['lastname'];
        $data['customerAddress']   = $params['clientdetails']['address1'] . ' ' . $params['clientdetails']['address2'];
        $data['customerCity']      = $params['clientdetails']['city'];
        $data['customerPhone']     = $params['clientdetails']['phonenumber'];
        $data['customerEmail']     = $params['clientdetails']['email'];
        $data['redirectURL']       = $returnUrl;
        $data['backURL']           = $params['returnurl'];
        $data['companyRef']        = $params['invoiceid'];
        $data['customerCountry']   = $params['clientdetails']['countrycode'];

        // Create token
        $tokens = $dpoCommon->createToken($data);
        logTransaction('dpo.php', null, 'Tokens ' . json_encode($tokens));
        logActivity("RequestData: " . json_encode($data));

        if ($tokens['success'] === true) {
            $data['transToken'] = $tokens['transToken'];

            $verify = $dpoCommon->verifyToken($data);
            logTransaction('dpo.php', null, 'Verify ' . $verify);
            $verify = <<<XML
$verify
XML;
            if ($verify != '') {
                $verify = str_replace(array("\r\n", "\r", "\n"), "", $verify);
                $verify = new SimpleXMLElement($verify);
                if ($verify->Result->__toString() === '900') {
                    $payUrl = $this->getDpoGateway() . $data['transToken'];

                    $tbl = self::DB_PREFIX . 'dpogroupdpo';
                    Capsule::table($tbl)
                           ->insert(
                               [
                                   [
                                       'recordtype' => 'dpoclient',
                                       'recordid'   => $data['transToken'],
                                       'recordval'  => $data['companyToken'],
                                   ],
                                   [
                                       'recordtype' => 'systemurl',
                                       'recordid'   => $data['transToken'],
                                       'recordval'  => $systemUrl,
                                   ],
                                   [
                                       'recordtype' => 'dporef',
                                       'recordid'   => $data['transToken'],
                                       'recordval'  => $params['invoiceid'],
                                   ],
                               ]
                           );
                    header('Location: ' . $payUrl);
                }
            }
        } else {
            echo 'Something went wrong: ' . $tokens['resultExplanation'];
            $url = $systemUrl . 'viewinvoice.php?id=' . $data['companyRef'];
            echo <<<HTML
<br><br><a href="$url">Click here to return</a>
HTML;
        }
    }
}
