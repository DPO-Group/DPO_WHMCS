<?php
/*
 * Copyright (c) 2024 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * This file handles the return POST from a PayHost or PayBatch transactionId
 *
 */

// Require libraries needed for gateway module functions
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../dpo/vendor/autoload.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use Dpo\Common\Dpo as DPOCommon;

const DB_PREFIX = 'tbl';

if (!function_exists('createDPOGroupdpoTable')) {
    function createDPOGroupdpoTable()
    {
        try {
            if (Capsule::schema()->hasTable(DB_PREFIX . 'paygatedpo')) {
                Capsule::schema()->rename(DB_PREFIX . 'paygatedpo', DB_PREFIX . 'dpogroupdpo');
            }

            if (!Capsule::schema()->hasTable(DB_PREFIX . 'dpogroupdpo')) {
                Capsule::schema()->create(
                    DB_PREFIX . 'dpogroupdpo',
                    function ($table) {
                        $table->increments('id');
                        $table->string('recordtype', 20);
                        $table->string('recordid', 50);
                        $table->string('recordval', 50);
                        $table->string('dbid', 10)->default('1');
                    }
                );
            }
        } catch (Exception $e) {
            logActivity("DPO Table Error: " . $e->getMessage());
        }
    }
}

createDPOGroupdpoTable();

$gatewayModuleName = basename(__FILE__, '.php'); // 'dpo'
$tbl               = DB_PREFIX . 'dpogroupdpo';

// 1. Capture TransID/Token from any format DPO sends (GET/POST)
$rawTransID = $_REQUEST['TransactionToken']
           ?? $_REQUEST['transToken']
           ?? $_REQUEST['TransID']
           ?? $_REQUEST['transID']
           ?? $_REQUEST['TransactionID']
           ?? '';

$transID = filter_var($rawTransID, FILTER_SANITIZE_SPECIAL_CHARS);

// Fallback System URL
$systemUrl = Capsule::table($tbl)
                    ->where('recordtype', 'systemurl')
                    ->where('recordid', $transID)
                    ->value('recordval');

if (empty($systemUrl)) {
    $systemUrl = \WHMCS\Config\Setting::getValue('SystemURL');
}
$systemUrl = rtrim($systemUrl, '/') . '/';

if (empty($transID)) {
    logTransaction($gatewayModuleName, ['error' => 'Callback Failed: Missing Transaction Token'], 'Failure');
    header('Location: ' . $systemUrl . 'clientarea.php?action=invoices');
    exit;
}

// 2. Fetch stored mapping records from database
$companyToken = Capsule::table($tbl)
                       ->where('recordtype', 'dpoclient')
                       ->where('recordid', $transID)
                       ->value('recordval');

$invoiceId = Capsule::table($tbl)
                    ->where('recordtype', 'dporef')
                    ->where('recordid', $transID)
                    ->value('recordval');

// 3. Verify token status with DPO API
$dpoCommon = new DPOCommon(false);
$data      = [
    'transToken'   => $transID,
    'companyToken' => $companyToken,
];

$verifyRaw = $dpoCommon->verifyToken($data);

if (!empty($verifyRaw)) {
    try {
        $verify = new SimpleXMLElement($verifyRaw);
    } catch (Exception $e) {
        logTransaction(
            $gatewayModuleName,
            ['error' => $e->getMessage()],
            'XML Parse Error'
        );
        header('Location: ' . $systemUrl . 'clientarea.php?action=invoices');
        exit;
    }

    $resultCode = (string)($verify->Result ?? '');
    $companyRef = (string)($verify->CompanyRef ?? '');
    $amount     = (float)($verify->TransactionAmount ?? 0);

    // Log callback payload into WHMCS Gateway Log
    logTransaction($gatewayModuleName, [
        'transID'     => $transID,
        'invoiceId'   => $invoiceId,
        'companyRef'  => $companyRef,
        'resultCode'  => $resultCode,
        'amount'      => $amount,
    ], $resultCode === '000' ? 'Successful Payment' : 'Payment Failed / Declined');

    // 4. Validate payment status and matching Invoice ID
    if ($resultCode === '000' && !empty($invoiceId) && (string)$invoiceId === $companyRef) {

        // Check invoice existence in WHMCS
        $invoiceId = checkCbInvoiceID($invoiceId, $gatewayModuleName);

        // Check if transaction was already processed
        checkCbTransID($transID);

        // Clean up temporary database records
        $recordids = Capsule::table($tbl)
                            ->select('recordid')
                            ->where('recordval', $invoiceId)
                            ->get();

        $recs = [];
        foreach ($recordids as $recordid) {
            $recs[] = $recordid->recordid;
        }

        if (!empty($recs)) {
            Capsule::table($tbl)->whereIn('recordid', $recs)->delete();
        }

        // Apply Payment to Invoice in WHMCS
        addInvoicePayment($invoiceId, $transID, $amount, 0, $gatewayModuleName);

        // Redirect customer to completed invoice view
        callback3DSecureRedirect($invoiceId, true);
        exit;
    } else {
        Capsule::table($tbl)->where('recordid', $transID)->delete();
        header('Location: ' . $systemUrl . 'clientarea.php?action=invoices');
        exit;
    }
} else {
    logTransaction($gatewayModuleName, ['transID' => $transID], 'Empty Verification Response');
    header('Location: ' . $systemUrl . 'clientarea.php?action=invoices');
    exit;
}
