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
require_once __DIR__ . '/../dpo/vendor/autoload.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;
use Dpo\Common\Dpo as DPOCommon;

const DB_PREFIX = 'tbl';

/**
 * Check for existence of dpogroupdpo table and create if not
 * In earlier versions this table was named paygatedpo -> rename if necessary
 */
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
            logActivity($e->getMessage());
        }
    }
}

createDPOGroupdpoTable();

$transID = filter_var($_GET['TransID'], FILTER_SANITIZE_SPECIAL_CHARS);

$tbl = DB_PREFIX . 'dpogroupdpo';

$companyToken = Capsule::table($tbl)
    ->where('recordtype', 'dpoclient')
    ->where('recordid', $transID)
    ->value('recordval');

$dpoCommon = new DPOCommon(false);
$data = [];
$data['transToken'] = $transID;
$data['companyToken'] = $companyToken;

$verify = $dpoCommon->verifyToken($data);

if ($verify != '') {
    try {
        $verify = new SimpleXMLElement($verify);
    } catch (Exception $e) {
        logActivity($e->getMessage());
    }

    try {
        $invoiceId = Capsule::table($tbl)
            ->where('recordid', $transID)
            ->where('recordtype', 'dporef')
            ->value('recordval');
    } catch (Exception $e) {
        throw new UnexpectedValueException('Error Exception: Undefined $transID and $invoiceId');
    }

    if ($verify->Result->__toString() === '000' && !empty((string)$invoiceId) == $verify->CompanyRef->__toString()) {
        // Transaction paid
        // Delete records for this invoice
        $recordids = Capsule::table($tbl)
            ->select('recordid')
            ->where('recordval', $invoiceId)
            ->get();

        $recs = [];
        foreach ($recordids as $recordid) {
            $recs[] = $recordid->recordid;
        }

        Capsule::table($tbl)
            ->whereIn('recordid', $recs)
            ->delete();

        // Detect module name from filename
        $gatewayModuleName = basename(__FILE__, '.php');

        $command = 'AddInvoicePayment';
        $data = [
            'invoiceid' => $invoiceId,
            'transid' => $transID,
            'gateway' => $gatewayModuleName,
        ];
        $result = localAPI($command, $data);
        callback3DSecureRedirect($invoiceId, true);
    } else {
        $systemUrl = Capsule::table($tbl)
            ->where('recordtype', 'systemurl')
            ->where('recordid', $transID)
            ->value('recordval');

        Capsule::table($tbl)
            ->where('recordid', $transID)
            ->delete();

        header('Location: ' . $systemUrl . 'clientarea.php?action=invoices');
    }


}
