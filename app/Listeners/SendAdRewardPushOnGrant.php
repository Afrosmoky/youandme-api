<?php

namespace App\Listeners;

use App\Modules\Rewards\Events\AdRewardGranted;
use Youandme\Auth\Models\User;
use Youandme\Notifications\Actions\SendPushAction;
use Youandme\Notifications\Data\PushMessageData;

/**
 * Composition-root wiring: a verified ad reward becomes a push. The only layer
 * that knows both Rewards (which emitted the event) and Notifications (which can
 * reach a phone) — neither package learns about the other, exactly as with the
 * Auth mails in R1 Etap 3.
 *
 * It also does the id → ulid translation the event could not: Rewards is a leaf
 * holding plain ids, and resolving one to an addressable identity is cross-module
 * work that belongs here.
 *
 * Both platforms since T11 (#24). P7 pushed to Android only because iOS needed an
 * APNs key from an Apple developer account; the key is in Firebase now, so the
 * filter is dropped and every device the user registered hears it. iOS users saw
 * the credit even then — the client refetches the balance on focus — which is why
 * this was a delay and never a hole.
 */
final class SendAdRewardPushOnGrant
{
    public function handle(AdRewardGranted $event): void
    {
        // The payload carries an internal id; turning it into an addressable ulid
        // is cross-module work, which is why it happens here and not in Rewards.
        $user = User::find($event->data->userId);

        if ($user === null) {
            return;
        }

        SendPushAction::run(
            $user->ulid,
            new PushMessageData(
                title: 'Kredyt przyznany',
                body: 'Nagroda za obejrzaną reklamę jest już na Waszym koncie.',
                data: [
                    'type' => 'ad_reward_granted',
                    'amount' => (string) $event->data->amount,
                ],
            ),
        );
    }
}
