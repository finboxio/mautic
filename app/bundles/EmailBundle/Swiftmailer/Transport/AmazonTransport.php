<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Swiftmailer\Transport;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\EmailBundle\Helper\MailHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AmazonTransport
 */
class AmazonTransport extends \Swift_SmtpTransport implements InterfaceCallbackTransport
{
    /**
     * {@inheritdoc}
     */
    public function __construct($host = 'localhost', $port = 25, $security = null)
    {
        parent::__construct($host, 587, 'tls');
        $this->setAuthMode('login');
    }

    /**
     * Returns a "transport" string to match the URL path /mailer/{transport}/callback
     *
     * @return mixed
     */
    public function getCallbackPath()
    {
        return 'amazon';
    }

    /**
     * Handle response
     *
     * @param Request       $request
     * @param MauticFactory $factory
     *
     * @return mixed
     */
    public function handleCallbackResponse(Request $request, MauticFactory $factory)
    {
        $msg_type1 = $request->headers->get('X-AMZ-SNS-MESSAGE-TYPE');
        $msg_type2 = $request->headers->get('x-amz-sns-message-type');

        $logger = $factory->getLogger();
        $logger->error("Received message type $msg_type1 or $msg_type2");

        // if (is_array($mandrillEvents)) {
        //     foreach ($mandrillEvents as $event) {
        //         $isBounce      = in_array($event['event'], array('hard_bounce', 'soft_bounce', 'reject', 'spam', 'invalid'));
        //         $isUnsubscribe = ('unsub' === $event['event']);
        //         if ($isBounce || $isUnsubscribe) {
        //             $type = ($isBounce) ? 'bounced' : 'unsubscribed';

        //             if (!empty($event['msg']['diag'])) {
        //                 $reason = $event['msg']['diag'];
        //             } elseif (!empty($event['msg']['bounce_description'])) {
        //                 $reason = $event['msg']['bounce_description'];
        //             } else {
        //                 $reason = ($isUnsubscribe) ? 'unsubscribed' : $event['event'];
        //             }

        //             if (isset($event['msg']['metadata']['hashId'])) {
        //                 $rows[$type]['hashIds'][$event['msg']['metadata']['hashId']] = $reason;
        //             } else {
        //                 $rows[$type]['emails'][$event['msg']['email']] = $reason;
        //             }
        //         }
        //     }
        // }

        return array();
        //$rows;
    }
}

