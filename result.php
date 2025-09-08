<?php
// This file is part of Moodle - http://moodle.org/
//
// (Keep your GPL header here.)
/**
 * Pesepay result URL (server-to-server) processor
 *
 * Pesepay will POST a JSON representation of the transaction to this endpoint.
 * This script updates the local transaction record and — when payment is final —
 * records the payment in Moodle and delivers the order.
 *
 * Various helper methods for interacting with the pesepay API
 *
 * @package    paygw_pesepay
 * @copyright  2025 Pesepay <support@pesepay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/payment/gateway/pesepay/.extlib/autoload.php');

use core_payment\helper as payment_helper;

global $DB;

@ignore_user_abort(true);
@set_time_limit(60);

$payload = file_get_contents('php://input');
if (empty($payload)) {
    http_response_code(400);
    echo 'Empty request body';
    exit;
}

$data = json_decode($payload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

$merchantref = $data['merchantReference'] ?? $data['merchantreference'] ?? $data['merchantRef'] ?? null;
$referenceNumber = $data['referenceNumber'] ?? $data['referencenumber'] ?? $data['reference'] ?? null;
$txstatus = $data['transactionStatus'] ?? $data['transactionstatus'] ?? null;

$tablename = 'paygw_pesepay';
$txn = null;
if (!empty($merchantref)) {
    if ($DB->get_manager()->table_exists($tablename)) {
        $txn = $DB->get_record($tablename, ['merchantref' => $merchantref]);
    }
    if (!$txn && $DB->get_manager()->table_exists('paygw_pesepay')) {
        $txn = $DB->get_record('paygw_pesepay', ['merchantref' => $merchantref]);
        $tablename = 'paygw_pesepay';
    }
} elseif (!empty($referenceNumber)) {
    if ($DB->get_manager()->table_exists($tablename)) {
        $txn = $DB->get_record($tablename, ['reference' => $referenceNumber]);
    }
    if (!$txn && $DB->get_manager()->table_exists('paygw_pesepay')) {
        $txn = $DB->get_record('paygw_pesepay', ['reference' => $referenceNumber]);
        $tablename = 'paygw_pesepay';
    }
}

if (!$txn) {
    http_response_code(404);
    echo 'Transaction not found';
    exit;
}

$statusmap = [
    'SUCCESS' => 'paid',
    'PARTIALLY_PAID' => 'pending',
    'PENDING' => 'pending',
    'PROCESSING' => 'pending',
    'INITIATED' => 'pending',
    'AUTHORIZATION_FAILED' => 'failed',
    'DECLINED' => 'failed',
    'FAILED' => 'failed',
    'ERROR' => 'failed',
    'TIME_OUT' => 'failed',
    'CANCELLED' => 'failed',
    'INSUFFICIENT_FUNDS' => 'failed',
    'REVERSED' => 'failed',
    'SERVICE_UNAVAILABLE' => 'pending',
    'TERMINATED' => 'failed',
    'CLOSED' => 'failed',
    'CLOSED_PERIOD_ELAPSED' => 'failed'
];

$txstatusupper = is_string($txstatus) ? mb_strtoupper($txstatus) : null;
$localstatus = 'pending';
if (!empty($txstatusupper) && isset($statusmap[$txstatusupper])) {
    $localstatus = $statusmap[$txstatusupper];
} else {
    debugging('Pesepay: unknown transactionStatus: ' . var_export($txstatus, true));
    $localstatus = 'pending';
}

$txn->rawresponse = json_encode($data);
$txn->timemodified = time();
$txn->status = $localstatus;

$DB->update_record($tablename, $txn);

if ($localstatus === 'paid') {
    try {
        $proceed = true;
        if ($DB->get_manager()->field_exists($tablename, 'paymentid')) {
            $existingpaymentid = $DB->get_field($tablename, 'paymentid', ['id' => $txn->id]);
            if (!empty($existingpaymentid)) {
                $proceed = false; // already processed
            }
        }

        if ($proceed) {
            $component = $txn->component;
            $paymentarea = $txn->paymentarea;
            $itemid = (int) $txn->itemid;
            $userid = (int) $txn->userid;

            $payable = payment_helper::get_payable($component, $paymentarea, $itemid);

            $amount = $txn->amount;
            $currency = $txn->currency;

            $paymentid = payment_helper::save_payment(
                $payable->get_account_id(),
                $component,
                $paymentarea,
                $itemid,
                $userid,
                $amount,
                $currency,
                'pesepay'
            );

            if ($DB->get_manager()->field_exists($tablename, 'paymentid')) {
                $DB->set_field($tablename, 'paymentid', $paymentid, ['id' => $txn->id]);
            }

            payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, $userid);

            debugging('Pesepay webhook: processed payment. txn.id=' . $txn->id . ' paymentid=' . $paymentid);
        } else {
            debugging('Pesepay webhook: payment already processed for txn.id=' . $txn->id);
        }

    } catch (Throwable $e) {
        debugging('Pesepay webhook: failed to save/deliver payment: ' . $e->getMessage());
        http_response_code(500);
        echo 'Internal error: ' . $e->getMessage();
        exit;
    }
}

http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo 'OK';
exit;
