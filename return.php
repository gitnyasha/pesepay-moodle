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
 * Various helper methods for interacting with the pesepay API
 *
 * @package    paygw_pesepay
 * @copyright  2025 Pesepay <support@pesepay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/payment/gateway/pesepay/.extlib/autoload.php');

use core_payment\helper as payment_helper;
use paygw_pesepay\pesepay_helper;
use moodle_url;

require_login();

global $DB, $USER;

$merchantref = optional_param('merchantref', null, PARAM_ALPHANUMEXT);
$referenceparam = optional_param('reference', null, PARAM_ALPHANUMEXT);

$txn = null;
if (!empty($merchantref)) {
    $txn = $DB->get_record('paygw_pesepay', ['merchantref' => $merchantref]);
} elseif (!empty($referenceparam)) {
    $txn = $DB->get_record('paygw_pesepay', ['reference' => $referenceparam]);
}

if (!$txn) {
    redirect(new moodle_url('/'), get_string('errnotransactionfound', 'paygw_pesepay', ''), 5, 'error');
    exit;
}

// Prevent users other than the owner from manipulating the return URL.
if ($txn->userid != $USER->id) {
    debugging('User mismatch on pesepay return: txn.userid=' . $txn->userid . ' user.id=' . $USER->id);
    redirect(new moodle_url('/'), get_string('errnotyourtransaction', 'paygw_pesepay', ''), 5, 'error');
    exit;
}

// If transaction already marked paid, just redirect to success page.
if (!empty($txn->status) && $txn->status === 'paid') {
    $component = $txn->component;
    $paymentarea = $txn->paymentarea;
    $itemid = $txn->itemid;
    $successurl = new moodle_url('/');
    if (method_exists('\core_payment\helper', 'get_success_url')) {
        $successurl = payment_helper::get_success_url($component, $paymentarea, $itemid);
    } else if ($component == 'enrol_fee' && $paymentarea == 'fee') {
        $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'fee', 'id' => $itemid]);
        if (!empty($courseid)) {
            $successurl = course_get_url($courseid);
        }
    }
    redirect($successurl, get_string('paymentsuccessful', 'paygw_pesepay'), 3, 'success');
    exit;
}

$component = $txn->component;
$paymentarea = $txn->paymentarea;
$itemid = $txn->itemid;

$config = (object) payment_helper::get_gateway_configuration($component, $paymentarea, $itemid, 'pesepay');
$pesepayhelper = new pesepay_helper($config->brandname, $config->integrationid, $config->encryptionkey);

$checkresponse = null;
$referenceToCheck = $referenceparam ?: ($txn->reference ?? null);
$pollUrl = $txn->pollurl ?? null;

if (!empty($referenceToCheck)) {
    $checkresponse = $pesepayhelper->check_payment_by_reference($referenceToCheck);
} elseif (!empty($pollUrl)) {
    $checkresponse = $pesepayhelper->poll_transaction($pollUrl);
} else {
    $txn->status = 'pending';
    $txn->rawresponse = json_encode(['error' => 'no_reference_or_pollurl_on_return']);
    $txn->timemodified = time();
    $DB->update_record('paygw_pesepay', $txn);

    redirect(new moodle_url('/'), get_string('errcannotverifytransaction', 'paygw_pesepay'), 5, 'error');
    exit;
}

if (empty($checkresponse) || empty($checkresponse->success)) {
    $errormessage = $checkresponse->message ?? 'Unknown error from Pesepay';
    $txn->status = 'pending';
    $txn->rawresponse = json_encode(['error' => $errormessage]);
    $txn->timemodified = time();
    $DB->update_record('paygw_pesepay', $txn);

    redirect(new moodle_url('/'), get_string('errtransactionverificationfailed', 'paygw_pesepay', $errormessage), 5, 'error');
    exit;
}

$ispaid = !empty($checkresponse->paid);

$txn->reference = $txn->reference ?: ($referenceToCheck ?: null);
$txn->rawresponse = json_encode(isset($checkresponse->raw) ? $checkresponse->raw : $checkresponse);
$txn->timemodified = time();
$txn->status = $ispaid ? 'paid' : 'pending';
$DB->update_record('paygw_pesepay', $txn);

if ($ispaid) {
    try {
        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);

        $amount = $txn->amount;
        $currency = $txn->currency;

        $paymentid = payment_helper::save_payment(
            $payable->get_account_id(),
            $component,
            $paymentarea,
            $itemid,
            $USER->id,
            $amount,
            $currency,
            'pesepay'
        );

        $txn->status = 'paid';
        $txn->timemodified = time();
        $txn->paymentid = $paymentid;
        $DB->update_record('paygw_pesepay', $txn);

        payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, $USER->id);

        // Redirect user to success URL.
        $successurl = new moodle_url('/');
        if (method_exists('\core_payment\helper', 'get_success_url')) {
            $successurl = payment_helper::get_success_url($component, $paymentarea, $itemid);
        } else if ($component == 'enrol_fee' && $paymentarea == 'fee') {
            $courseid = $DB->get_field('enrol', 'courseid', ['enrol' => 'fee', 'id' => $itemid]);
            if (!empty($courseid)) {
                $successurl = course_get_url($courseid);
            }
        }

        debugging('Pesepay payment successful: merchantref=' . $txn->merchantref . ' reference=' . ($txn->reference ?? ''));

        redirect($successurl, get_string('paymentsuccessful', 'paygw_pesepay'), 3, 'success');
        exit;

    } catch (Exception $e) {
        $txn->status = 'pending';
        $txn->rawresponse = json_encode(['error' => $e->getMessage(), 'api' => $checkresponse->raw ?? null]);
        $txn->timemodified = time();
        $DB->update_record('paygw_pesepay', $txn);

        debugging('Pesepay: failed to save/deliver payment: ' . $e->getMessage());
        redirect(new moodle_url('/'), get_string('errpaymentsavedfailed', 'paygw_pesepay', $e->getMessage()), 5, 'error');
        exit;
    }
} else {
    redirect(new moodle_url('/'), get_string('paymentpending', 'paygw_pesepay'), 5, 'info');
    exit;
}
