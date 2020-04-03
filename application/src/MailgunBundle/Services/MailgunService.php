<?php

namespace MailgunBundle\Services;

use Mailgun\Mailgun;
use Http\Adapter\Guzzle6\Client;

class MailgunService
{
    //mailgunClient
    private $mgClient;

    //Mailgun domain
    private $mgDomain;

    public function __construct($mailgunDomain, $mailgunApiKey) {
        $this->mgClient = new Mailgun($mailgunApiKey, new Client());
        $this->mgDomain = $mailgunDomain;
    }

    public function sendEmail($message, $toMail) {
        return $result = $this->mgClient->sendMessage($this->mgDomain,
            [
                'from'    => 'Mailgun Sandbox <postmaster@sandbox56b9375278064e5d895fbe4bbd6aab38.mailgun.org>',
                'to'      => $toMail,
                'subject' => 'Hello!',
                'text'    => $message
            ]);
    }
}
