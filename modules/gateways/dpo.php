<?php
/*
 * Copyright (c) 2024 DPO Group
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

require_once __DIR__ . '/dpo/vendor/autoload.php';
require_once 'dpo/lib/Services/DPOPaymentService.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

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
        } catch (\Exception $e) {
            logActivity($e->getMessage());
        }
    }
}

createDPOGroupdpoTable();

if (isset($_POST['INITIATE']) && $_POST['INITIATE'] == 'initiate') {
    $params    = json_decode(base64_decode($_POST['jparams']), true);
    $systemUrl = $params['systemurl'];

    $dpoPaymentService = new DPOGroup\DPOPaymentService();
    try {
        $dpoPaymentService->dpoInitiate($params);
    } catch (Exception $e) {
        logActivity($e->getMessage());
    }
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
        'DisplayName'                 => 'DPO Pay',
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
            'Value' => 'DPO Pay',
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
    );
}

function dpo_link($params)
{
    $jParams   = base64_encode(json_encode($params));
    $systemURL = $params['systemurl'];
    return <<<HTML
    <form method="post" action="{$systemURL}modules/gateways/dpo.php" >
    <input type="hidden" name="INITIATE" value="initiate" />
    <input type="hidden" name="jparams" value="$jParams" />
    <input type="submit" value="Pay using DPO Pay" />
    </form>
HTML;

}

