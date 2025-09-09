<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin strings are defined here.
 *
 * @package     paygw_pesepay
 * @category    string
 * @copyright   2025 Pesepay support@pesepay.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Pesepay';
$string['pluginname_desc'] = 'Pesepay payment gateway plugin for Moodle.';
$string['encryptionkey'] = 'Encryption Key';
$string['integrationid'] = 'Integration ID';
$string['brandname'] = 'Pesepay';
$string['gatewayname'] = 'Pesepay';
$string['gatewaydescription'] = 'Pesepay is an authorised payment gateway provider for processing transactions.';
$string['privacy:metadata'] = 'The Pesepay plugin does not store any personal data.';
$string['paymentsuccessful'] = 'Payment successful';
$string['paymentpending'] = 'Payment pending';
$string['paymentcancelled'] = 'Payment cancelled';
$string['paymentfailed'] = 'Payment failed';
$string['errnotransactionfound'] = 'No matching Pesepay transaction was found.';
$string['errnotyourtransaction'] = 'This transaction does not belong to your account.';
$string['errcannotverifytransaction'] = 'Cannot verify transaction: missing reference or poll URL.';
$string['errtransactionverificationfailed'] = 'Transaction verification failed: {$a}';
$string['errpaymentsavedfailed'] = 'Payment processing failed while saving the payment: {$a}';
$string['errtransactioninitfailed'] = 'Failed to initiate Pesepay transaction: {$a}';
$string['errtransactionalreadyprocessed'] = 'This transaction has already been processed.';

