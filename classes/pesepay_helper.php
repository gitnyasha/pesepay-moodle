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

namespace paygw_pesepay;

use Codevirtus\Payments\Pesepay;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/payment/gateway/pesepay/.extlib/autoload.php');

class pesepay_helper
{
    /** @var string */
    protected $brandname;

    /** @var string */
    protected $integrationid;

    /** @var string */
    protected $encryptionkey;

    /** @var \Codevirtus\Payments\Pesepay */
    protected $pesepay;

    /**
     * pesepay_helper constructor.
     *
     * @param string $brandname
     * @param string $integrationid
     * @param string $encryptionkey
     */
    public function __construct($brandname, $integrationid, $encryptionkey)
    {
        global $CFG;

        $this->brandname = $brandname;
        $this->integrationid = $integrationid;
        $this->encryptionkey = $encryptionkey;

        $this->pesepay = new Pesepay($this->integrationid, $this->encryptionkey);
    }

    /**
     * Create a transaction at Pesepay and return the redirect URL (and meta).
     *
     * @param \stdClass $user Moodle $USER object (or at least ->id must exist)
     * @param float|string $amount Amount in gateway units (e.g. 12.34)
     * @param string $currency 3-letter currency code (e.g. 'USD' or 'ZAR')
     * @param string $description Payment description / reason
     * @param string|null $merchantreference Optional merchant reference (if null one will be created)
     * @return \stdClass { success: bool, url?: string, reference?: string, pollurl?: string, merchantref?: string, message?: string }
     */
    public function get_checkout_url($user, $amount, $currency, $description, $merchantreference = null)
    {
        global $CFG;

        $currency = strtoupper((string) $currency);

        if (empty($merchantreference)) {
            $merchantreference = 'moodle_' . time() . '_u' . intval($user->id);
        }

        $returnurl = $CFG->wwwroot . '/payment/gateway/pesepay/return.php?merchantref=' . urlencode($merchantreference);
        $resulturl = $CFG->wwwroot . '/payment/gateway/pesepay/result.php?merchantref=' . urlencode($merchantreference);

        $this->pesepay->returnUrl = $returnurl;
        $this->pesepay->resultUrl = $resulturl;

        $amountstr = (string) $amount;

        try {
            $transaction = $this->pesepay->createTransaction($amountstr, $currency, $description, $merchantreference);
        } catch (\Throwable $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to create transaction object: ' . $e->getMessage()
            ];
        }

        try {
            $response = $this->pesepay->initiateTransaction($transaction);
        } catch (\Throwable $e) {
            return (object) [
                'success' => false,
                'message' => 'Failed to initiate transaction: ' . $e->getMessage()
            ];
        }

        if ($response->success()) {
            $redirectUrl = $response->redirectUrl();
            $referenceNumber = $response->referenceNumber();
            $pollUrl = $response->pollUrl();

            return (object) [
                'success' => true,
                'url' => $redirectUrl,
                'reference' => $referenceNumber,
                'pollurl' => $pollUrl,
                'merchantref' => $merchantreference
            ];
        } else {
            $message = method_exists($response, 'message') ? $response->message() : 'Unknown error from Pesepay';
            return (object) [
                'success' => false,
                'message' => $message
            ];
        }
    }

    /**
     * Check payment status using reference number.
     *
     * @param string $referenceNumber
     * @return \stdClass { success: bool, paid?: bool, raw?:mixed, message?: string }
     */
    public function check_payment_by_reference($referenceNumber)
    {
        try {
            $response = $this->pesepay->checkPayment($referenceNumber);
        } catch (\Throwable $e) {
            return (object) [
                'success' => false,
                'message' => 'Error while checking payment: ' . $e->getMessage()
            ];
        }

        if (!$response->success()) {
            $message = method_exists($response, 'message') ? $response->message() : 'Unknown error';
            return (object) ['success' => false, 'message' => $message];
        }

        return (object) [
            'success' => true,
            'paid' => method_exists($response, 'paid') ? $response->paid() : false,
            'raw' => $response
        ];
    }

    /**
     * Poll a transaction using the poll URL returned when initiating.
     *
     * @param string $pollUrl
     * @return \stdClass { success: bool, paid?: bool, raw?: mixed, message?: string }
     */
    public function poll_transaction($pollUrl)
    {
        try {
            $response = $this->pesepay->pollTransaction($pollUrl);
        } catch (\Throwable $e) {
            return (object) [
                'success' => false,
                'message' => 'Error while polling transaction: ' . $e->getMessage()
            ];
        }

        if (!$response->success()) {
            $message = method_exists($response, 'message') ? $response->message() : 'Unknown error';
            return (object) ['success' => false, 'message' => $message];
        }

        return (object) [
            'success' => true,
            'paid' => method_exists($response, 'paid') ? $response->paid() : false,
            'raw' => $response
        ];
    }
}