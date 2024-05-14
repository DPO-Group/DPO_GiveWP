<?php

/*
 * Copyright (c) 2024 DPO Group
 *
 * Author: App Inlet (Pty) Ltd
 *
 * Released under the GNU General Public License
 */

namespace Dpo\Give;

use Dpo\Common\Dpo;

class DpoGiveSettings
{
    /**
     * @param array $settings
     *
     * @return array|array[]
     */
    public static function addSettings(array $settings): array
    {
        if (DpoGive::id() !== give_get_current_setting_section()) {
            return $settings;
        }

        $checkoutTitle = give_get_option('give_dpo_checkout_title');
        if (empty($checkoutTitle)) {
            give_update_option('give_dpo_checkout_title', 'DPO Pay');
        }
        $checkoutDescription = give_get_option('give_dpo_checkout_description');
        if (empty($checkoutDescription)) {
            give_update_option(
                'give_dpo_checkout_description',
                'You will be redirected to DPO Pay to complete the payment'
            );
        }
        $payUrl = give_get_option('give_dpo_pay_url', '');
        if (empty($payUrl)) {
            give_update_option('give_dpo_pay_url', Dpo::$livePayUrl);
        }
        $dpoUrl = give_get_option('give_dpo_url', '');
        if (empty($dpoUrl)) {
            give_update_option('give_dpo_url', Dpo::$liveApiUrl);
        }

        return [
            [
                'type' => 'title',
                'id'   => DpoGive::id(),
            ],
            [
                'name' => __('Title', 'give-dpo'),
                'desc' => __(
                    'This controls the title which the user sees during checkout',
                    'give-dpo'
                ),
                'id'   => 'give_dpo_checkout_title',
                'type' => 'text',
            ],
            [
                'name' => __('Description', 'give-dpo'),
                'desc' => __(
                    'This controls the description which the user sees during checkout',
                    'give-dpo'
                ),
                'id'   => 'give_dpo_checkout_description',
                'type' => 'text',
            ],
            [
                'name' => __('Company Token', 'give-dpo'),
                'desc' => __(
                    'Enter the company token received from DPO',
                    'give-dpo'
                ),
                'id'   => 'give_dpo_company_token',
                'type' => 'text',
            ],
            [
                'name' => __('Default DPO Service Type', 'give-dpo'),
                'desc' => __(
                    'Insert a default service type number according to the options accepted by the DPO Group.',
                    'give-dpo'
                ),
                'id'   => 'give_dpo_default_service_type',
                'type' => 'text',
            ],
            [
                'name'    => __('PTL Type (optional)', 'give-dpo'),
                'desc'    => __(
                    'Define whether payment time limit tag is hours or minutes.',
                    'give-dpo'
                ),
                'id'      => 'give_dpo_ptl_type',
                'type'    => 'select',
                'options' => [
                    'hours'   => 'Hours',
                    'minutes' => 'Minutes',
                ],
                'default' => 'minutes',
            ],
            [
                'name'    => __('PTL (Optional)', 'give-dpo'),
                'desc'    => __(
                    'The number of hours or minutes.',
                    'give-dpo'
                ),
                'id'      => 'give_dpo_ptl_limit',
                'type'    => 'number',
                'default' => 0,
            ],
            [
                'id'   => DpoGive::id(),
                'type' => 'sectionend'
            ]
        ];
    }
}
