<?php
/*
 * Copyright (c) 2021 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 *
 * This module facilitates DPO Group payments for WHMCS clients
 *
 */

// Require libraries needed for gateway module functions
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../includes/invoicefunctions.php';

require_once 'dpo/lib/constants.php';
require_once 'dpo/lib/Dpo.php';

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

if ( isset( $_POST['INITIATE'] ) && $_POST['INITIATE'] == 'initiate' ) {
    $params    = json_decode( base64_decode( $_POST['jparams'] ), true );
    $systemUrl = $params['systemurl'];

    dpo_initiate( $params );
}

/**
 * Define module related meta data
 *
 * Values returned here are used to determine module related capabilities and
 * settings
 *
 * @return array
 */
function dpo_MetaData()
{
    return array(
        'DisplayName'                 => 'Direct Pay Online (DPO)',
        'APIVersion'                  => '1.1', // Use API Version 1.1
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage'            => true,
    );
}

/**
 * Define gateway configuration options
 *
 *
 * @return array
 */
function dpo_config()
{
    return array(
        // The friendly display name for a payment gateway should be
        // defined here for backwards compatibility
        'FriendlyName' => array(
            'Type'  => 'System',
            'Value' => 'DPO Group',
        ),
        'CompanyToken' => array(
            'FriendlyName' => 'Company Token',
            'Type'         => 'text',
            'Size'         => '50',
            'Default'      => '',
            'Description'  => 'Enter your Company Token here',
        ),
        'AccountType'  => array(
            'FriendlyName' => 'Service Type',
            'Type'         => 'text',
            'Size'         => '32',
            'Default'      => '',
            'Description'  => 'Enter the Service Type here',
        ),
        'testMode'     => array(
            'FriendlyName' => 'Test Mode',
            'Type'         => 'yesno',
            'Description'  => 'Tick to enable test mode',
        ),
    );
}

function dpo_link( $params )
{
    $jparams   = base64_encode( json_encode( $params ) );
    $systemurl = $params['systemurl'];
    $html      = <<<HTML
    <form method="post" action="{$systemurl}modules/gateways/dpo.php" >
    <input type="hidden" name="INITIATE" value="initiate" />
    <input type="hidden" name="jparams" value="$jparams" />
    <input type="submit" value="Pay Using DPO Group" />
    </form>
HTML;

    return $html;
}

function dpo_initiate( $params )
{
    $testMode = $params['testMode'] === 'on' ? true : false;
    // Callback urls
    $systemUrl = $params['systemurl'];
    $notifyUrl = $systemUrl . 'modules/gateways/callback/dpo.php';
    $returnUrl = $systemUrl . 'modules/gateways/callback/dpo.php';

    $data                      = [];
    $data['companyToken']      = $testMode ? '9F416C11-127B-4DE2-AC7F-D5710E4C5E0A' : $params['CompanyToken'];
    $data['accountType']       = $params['AccountType'];
    $data['paymentAmount']     = $params['amount'];
    $data['paymentCurrency']   = $params['currency'];
    $data['customerFirstName'] = $params['clientdetails']['firstname'];
    $data['customerLastName']  = $params['clientdetails']['lastname'];
    $data['customerAddress']   = $params['clientdetails']['address1'] . ' ' . $params['clientdetails']['address2'];
    $data['customerCity']      = $params['clientdetails']['city'];
    $data['customerPhone']     = $params['clientdetails']['phonenumber'];
    $data['customerEmail']     = $params['clientdetails']['email'];
    $data['redirectURL']       = $returnUrl;
    $data['backUrl']           = $params['returnurl'];
    $data['companyRef']        = $params['invoiceid'];

    // Create token
    $dpo    = new DPOGroup\Dpo( $testMode );
    $tokens = $dpo->createToken( $data );

    if ( $tokens['success'] === 'true' ) {
        $data['transToken'] = $tokens['transToken'];
        $verify             = $dpo->verifyToken( $data );

        if ( !empty( $verify ) && $verify != '' ) {
            $verify = new \SimpleXMLElement( $verify );
            if ( $verify->Result->__toString() === '900' ) {
                $payUrl = $dpo->getDpoGateway() . $data['transToken'];

                // Store the test mode for the transaction so we can use it in callback
                $tbl = _DB_PREFIX_ . 'dpogroupdpo';
                Capsule::table( $tbl )
                    ->insert(
                        [
                            [
                                'recordtype' => 'dpotest',
                                'recordid'   => $data['transToken'],
                                'recordval'  => $testMode,
                            ],
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
                header( 'Location: ' . $payUrl );
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
