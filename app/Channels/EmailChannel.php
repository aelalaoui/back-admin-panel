<?php

namespace App\Channels;

use App\Managers\EmailManager;
use Exception;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

class EmailChannel extends MailChannel
{

    /**
     * @throws Exception
     */
    public function send($notifiable, Notification $notification): void
    {
        if ($notification instanceof Emailable === false) {
            throw new Exception(
                'The notification should be an instance of Emailable to be transformed in an email'
            );
        }

        $manager = new EmailManager(
            ['a.alaoui.2089@gmail.com'],
            $notification->getRecipientEmails($notifiable)
        );

        $mail = $notification->toTransactionEmail($notifiable);

        $manager->sendEmail(
            $mail->getSlug(),
            $mail->getData(),
            'stratoffice@mailcoach.cloud'
        );

        $this->notifyDispatched($notification);
    }
}
