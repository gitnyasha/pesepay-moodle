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
 * Contains class for pesepay payment gateway.
 *
 * @package    paygw_pesepay
 * @copyright  2025 Pesepay <support@pesepay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_pesepay;

/**
 * The gateway class for pesepay payment gateway.
 *
 * @copyright  2025 Pesepay <support@pesepay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway
{
    public static function get_supported_currencies(): array
    {
        return [
            'ZIG',
            'USD'
        ];
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void
    {
        $mform = $form->get_mform();

        $mform->addElement('text', 'brandname', get_string('brandname', 'paygw_pesepay'));
        $mform->setType('brandname', PARAM_TEXT);
        $mform->addHelpButton('brandname', 'brandname', 'paygw_pesepay');

        $mform->addElement('text', 'integrationid', get_string('integrationid', 'paygw_pesepay'));
        $mform->setType('integrationid', PARAM_TEXT);
        $mform->addHelpButton('integrationid', 'integrationid', 'paygw_pesepay');

        $mform->addElement('text', 'encryptionkey', get_string('encryptionkey', 'paygw_pesepay'));
        $mform->setType('encryptionkey', PARAM_TEXT);
        $mform->addHelpButton('encryptionkey', 'encryptionkey', 'paygw_pesepay');
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(
        \core_payment\form\account_gateway $form,
        \stdClass $data,
        array $files,
        array &$errors
    ): void {
        if (
            $data->enabled &&
            (empty($data->brandname) || empty($data->integrationid) || empty($data->encryptionkey))
        ) {
            $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
        }
    }
}
