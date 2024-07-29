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
use Give\Donations\Models\Donation;
use Give\Donations\Models\DonationNote;
use Give\Donations\ValueObjects\DonationStatus;
use Give\Framework\Exceptions\Primitives\Exception;
use Give\Framework\PaymentGateways\Commands\RedirectOffsite;
use Give\Framework\PaymentGateways\PaymentGateway;
use SimpleXMLElement;

class DpoGive extends PaymentGateway
{
    protected static string $testUrl;
    protected static string $testPayUrl;
    protected static string $liveUrl;
    protected static string $livePayUrl;
    protected static string $dpoUrl;
    protected static string $payUrl;

    public function __construct()
    {
        parent::__construct();

        self::$testUrl    = Dpo::$testApiUrl;
        self::$liveUrl    = Dpo::$liveApiUrl;
        self::$testPayUrl = Dpo::$testPayUrl;
        self::$livePayUrl = Dpo::$livePayUrl;

        self::$dpoUrl = self::$liveUrl;
        self::$payUrl = self::$livePayUrl;
    }

    public static function id(): string
    {
        return 'dpo_give';
    }

    public static function addGatewaysSection($section)
    {
        $section[self::id()] = __('DPO Pay', 'give-dpo');

        return $section;
    }

    public static function giveDpoPluginActionLinks($action)
    {
        $newAction = [
            'settings' => sprintf(
                '<a href="%1$s">%2$s</a>',
                admin_url(
                    'edit.php?post_type=give_forms&page=give-settings&tab=gateways&section=dpo_give'
                ),
                __('Settings', 'give-dpo')
            ),
        ];

        return array_merge($newAction, $action);
    }

    public static function getOptions(array $settings): array
    {
        $newSetting = [
            'dpo_give' => [
                'admin_label'    => 'DPO',
                'checkout_label' => 'DPO',
                'is_visible'     => true,
            ],
        ];

        return array_merge($newSetting, $settings);
    }

    /**
     * Handles the GET response from DPO Portal
     *
     * @return void
     * @throws Exception
     */
    public static function giveDpoListener(): void
    {
        $get = give_clean($_GET ?? []);
        if (isset($get['give-listener']) && $get['give-listener'] === 'dpolistener') {
            $dpo              = new Dpo(false);
            $transactionToken = $get['TransactionToken'];
            $verify           = $dpo->verifyToken(
                [
                    'companyToken' => give_get_option('give_dpo_company_token'),
                    'transToken'   => $transactionToken,
                ]
            );
            self::processVerify($verify);

            DpoGiveCron::giveDpoQueryCron();
        }
    }

    /**
     * @param string $verify
     * @param Donation|null $donation
     *
     * @return void
     * @throws Exception
     */
    public static function processVerify(string $verify, Donation $donation = null): void
    {
        if (!empty($verify) && str_starts_with($verify, '<?xml')) {
            $verify = new SimpleXMLElement($verify);
            if ($verify->Result->__toString() === '000') {
                // Successful payment
                if (!$donation) {
                    $donation = Donation::find((int)$verify->CompanyRef->__toString());
                }
                if ((int)$donation->amount->getAmount(
                    ) !== (int)(((float)$verify->TransactionAmount - (float)$verify->AllocationAmount) * 100)) {
                    // Amounts don't match
                    $donationNote = new DonationNote(
                        [
                            'donationId' => $donation->id,
                            'content'    => 'ValueMismatch: ' . (int)$donation->amount->getAmount() .
                                            ' does not match ' . (int)$verify->TransactionAmount * 100,
                        ]
                    );
                    $donationNote->save();
                    $donation->status = DonationStatus::FAILED();
                    $donation->save();
                } else {
                    $donation->status               = DonationStatus::COMPLETE();
                    $donation->gatewayTransactionId = $verify->TransactionRef->__toString();
                    $donation->save();
                }
            }
        }
    }

    public function getId(): string
    {
        return self::id();
    }

    public function getName(): string
    {
        return __(give_get_option('give_dpo_checkout_title'), 'dpo-give');
    }

    public function getPaymentMethodLabel(): string
    {
        return __(give_get_option('give_dpo_checkout_title'), 'dpo_give');
    }

    /**
     * Initiate payment from the checkout page
     *
     * @param Donation $donation
     * @param $gatewayData
     *
     * @return RedirectOffsite|void
     * @throws Exception
     */
    public function createPayment(Donation $donation, $gatewayData)
    {
        $paymentAmount   = number_format($donation->amount->getAmount() / 100.0, 2);
        $paymentCurrency = $donation->amount->getCurrency()->getCode();

        $ptlLimit    = give_get_option('give_dpo_ptl_limit');
        $redirectUrl = add_query_arg('give-listener', 'dpolistener', $gatewayData['successUrl']);

        $dpo  = new Dpo(false);
        $data = [
            'serviceType'       => give_get_option('give_dpo_default_service_type'),
            'companyToken'      => give_get_option('give_dpo_company_token'),
            'paymentAmount'     => $paymentAmount,
            'paymentCurrency'   => $paymentCurrency,
            'companyRef'        => $donation->id,
            'redirectURL'       => $redirectUrl,
            'backURL'           => $gatewayData['cancelUrl'],
            'customerFirstName' => $donation->firstName,
            'customerLastName'  => $donation->lastName,
            'customerEmail'     => $donation->email,
            'customerZip'       => $donation->billingAddress->zip,
            'customerAddress'   => $donation->billingAddress->address1 . ' ' . $donation->billingAddress->address2,
            'transactionSource' => 'givewp',
        ];

        if ($ptlLimit) {
            $data['PTL']     = (int)$ptlLimit;
            $data['PTLtype'] = give_get_option('dpo_give_ptl_type', 'minutes');
        }

        $token = $dpo->createToken($data);
        if ($token['success'] && $token['result'] === '000') {
            $donationNote = new DonationNote(
                [
                    'donationId' => $donation->id,
                    'content'    => 'TransToken:' . $token['transToken'],
                ]
            );
            $donationNote->save();
            $url = self::$payUrl . "?ID=" . $token['transToken'];

            return new RedirectOffsite($url);
        }
    }

    public function refundDonation(Donation $donation)
    {
        // Not used
    }

    public function supportsFormVersions(): array
    {
        // Supports option-based forms
        return [2, 3];
    }

    public function getLegacyFormFieldMarkup()
    {
        return give_get_option('give_dpo_checkout_description');
    }
}
