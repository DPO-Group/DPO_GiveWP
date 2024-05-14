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
use Give\Donations\ValueObjects\DonationStatus;

class DpoGiveCron
{
    public static function giveDpoQueryCron()
    {
        $companyToken = give_get_option('give_dpo_company_token');
        $donations    = self::getDonations();
        $dpo          = new Dpo(false);
        foreach ($donations as $d) {
            $donation      = Donation::find($d->ID);
            $donationNotes = $donation->notes;
            if (empty($donationNotes)) {
                $donation->status = DonationStatus::ABANDONED();
                $donation->save();
                continue;
            }
            foreach ($donationNotes as $note) {
                if (str_starts_with($note->content, 'TransToken:')) {
                    $transactionToken = explode(':', $note->content)[1];
                    $verify           = $dpo->verifyToken(
                        [
                            'companyToken' => $companyToken,
                            'transToken'   => $transactionToken,
                        ]
                    );
                    DpoGive::processVerify($verify, $donation);
                    break;
                }
            }
        }
    }

    protected static function getDonations()
    {
        global $wpdb;

        $query = <<<QUERY
select ID from `{$wpdb->prefix}posts`
where post_type = 'give_payment'
and post_status = 'pending'
QUERY;

        return $wpdb->get_results($query);
    }
}
