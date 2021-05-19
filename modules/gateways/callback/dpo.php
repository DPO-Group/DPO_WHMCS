<?php
/*
 * Copyright (c) 2021 DPO Group
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
require_once '../dpo/lib/constants.php';
require_once '../dpo/lib/Dpo.php';

if ( !defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

use WHMCS\Database\Capsule;

if ( !defined( '_DB_PREFIX_' ) ) {
    define( '_DB_PREFIX_', 'tbl' );
}

/**
 * Check for existence of dpogroupdpo table and create if not
 * In earlier versions this table was named paygatedpo -> rename if necessary
 */
if ( !function_exists( 'createDPOGroupdpoTable' ) ) {
    function createDPOGroupdpoTable()
    {
        try {
            if ( Capsule::schema()->hasTable( _DB_PREFIX_ . 'paygatedpo' ) ) {
                Capsule::schema()->rename( _DB_PREFIX_ . 'paygatedpo', _DB_PREFIX_ . 'dpogroupdpo' );
            }

            if ( !Capsule::schema()->hasTable( _DB_PREFIX_ . 'dpogroupdpo' ) ) {
                Capsule::schema()->create(
                    _DB_PREFIX_ . 'dpogroupdpo',
                    function ( $table ) {
                        $table->increments( 'id' );
                        $table->string( 'recordtype', 20 );
                        $table->string( 'recordid', 50 );
                        $table->string( 'recordval', 50 );
                        $table->string( 'dbid', 10 )->default( '1' );
                    }
                );
            }
        } catch ( \Exception $e ) {
        }
    }
}

createDPOGroupdpoTable();

$transId = filter_var( $_GET['TransID'], FILTER_SANITIZE_STRING );

// Get test mode from db
$tbl      = _DB_PREFIX_ . 'dpogroupdpo';
$testMode = (bool) Capsule::table( $tbl )
    ->where( 'recordtype', 'dpotest' )
    ->where( 'recordid', $transId )
    ->value( 'recordval' );

$companyToken = Capsule::table( $tbl )
    ->where( 'recordtype', 'dpoclient' )
    ->where( 'recordid', $transId )
    ->value( 'recordval' );

$dpo                  = new DPOGroup\Dpo( $testMode );
$data                 = [];
$data['transToken']   = $transId;
$data['companyToken'] = $companyToken;

$verify = $dpo->verifyToken( $data );

if ( $verify != '' ) {
    $verify = new \SimpleXMLElement( $verify );

    if ( $verify->Result->__toString() === '000' ) {
        // Transaction paid
        $invoiceId = Capsule::table( $tbl )
            ->where( 'recordid', $transId )
            ->where( 'recordtype', 'dporef' )
            ->value( 'recordval' );

        // Delete records for this invoice
        $recordids = Capsule::table( $tbl )
            ->select( 'recordid' )
            ->where( 'recordval', $invoiceId )
            ->get();

        $recs = [];
        foreach ( $recordids as $recordid ) {
            $recs[] = $recordid->recordid;
        }

        Capsule::table( $tbl )
            ->whereIn( 'recordid', $recs )
            ->delete();

        // Detect module name from filename
        $gatewayModuleName = basename( __FILE__, '.php' );

        $command = 'AddInvoicePayment';
        $data    = [
            'invoiceid' => $invoiceId,
            'transid'   => $transId,
            'gateway'   => $gatewayModuleName,
        ];
        $result = localAPI( $command, $data );
        callback3DSecureRedirect( $invoiceId, true );
    } else {
        $systemUrl = Capsule::table( $tbl )
            ->where( 'recordtype', 'systemurl' )
            ->where( 'recordid', $transId )
            ->value( 'recordval' );

        Capsule::table( $tbl )
            ->where( 'recordid', $transId )
            ->delete();

        header( 'Location: ' . $systemUrl . 'clientarea.php?action=invoices' );
    }
}
