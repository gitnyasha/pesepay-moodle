<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Payment processor
 *
 * @package    paygw_pesepay
 * @copyright  2025 Pesepay <support@pesepay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/payment/gateway/pesepay/.extlib/autoload.php');

use core_payment\helper as payment_helper;
use paygw_pesepay\pesepay_helper;

require_login();

$component = required_param('component', PARAM_ALPHANUMEXT);
$paymentarea = required_param('paymentarea', PARAM_ALPHANUMEXT);
$itemid = required_param('itemid', PARAM_INT);
$description = required_param('description', PARAM_TEXT);

$config = (object) payment_helper::get_gateway_configuration($component, $paymentarea, $itemid, 'pesepay');
$payable = payment_helper::get_payable($component, $paymentarea, $itemid);
$surcharge = payment_helper::get_gateway_surcharge('pesepay');
$cost = payment_helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);

$pesepayhelper = new pesepay_helper($config->brandname, $config->integrationid, $config->encryptionkey);

$checkout = $pesepayhelper->get_checkout_url($USER, $cost, $payable->get_currency(), $description);

global $DB;
$record = new \stdClass();
$record->userid = $USER->id;
$record->component = $component;
$record->paymentarea = $paymentarea;
$record->itemid = $itemid;
$record->merchantref = $checkout->merchantref;
$record->reference = $checkout->reference ?? null;
$record->pollurl = $checkout->pollurl ?? null;
$record->amount = $cost;
$record->currency = $payable->get_currency();
$record->description = $description;
$record->status = 'initiated';
$record->rawresponse = isset($checkout->raw) ? json_encode($checkout->raw) : null;
$record->timecreated = time();

$txnId = $DB->insert_record('paygw_pesepay', $record);

redirect($checkout->url);