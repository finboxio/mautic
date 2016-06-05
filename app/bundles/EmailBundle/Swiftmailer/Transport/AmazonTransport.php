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
use Mautic\EmailBundle\Entity\StatRepository;
use Symfony\Component\HttpFoundation\Request;
use Aws\Sns\MessageValidator\Message;
use Aws\Sns\MessageValidator\MessageValidator;
use Guzzle\Http\Client;

/**
 * Class AmazonTransport
 */
class AmazonTransport extends \Swift_SmtpTransport implements InterfaceCallbackTransport
{
    private $factory;

    /**
     * {@inheritdoc}
     */
    public function __construct($host = 'localhost', $port = 25, $security = null)
    {
        parent::__construct($host, 587, 'tls');
        $this->setAuthMode('login');
    }

    /**
     * @param MauticFactory $factory
     */
    public function setMauticFactory(MauticFactory $factory)
    {
        $this->factory = $factory;
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
     * @param \Swift_Mime_Message $message
     * @param null                $failedRecipients
     *
     * @return int
     * @throws \Exception
     */
    public function send(\Swift_Mime_Message $message, &$failedRecipients = null)
    {
        $listener = new AmazonSmtpResponseListener($this->factory, $message);
        $this->registerPlugin($listener);
        return parent::send($message, $failedRecipients);
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
        // Make sure the request is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        try {
            // Create a message from the post data and validate its signature
            $message = Message::fromRawPostData();
            $validator = new MessageValidator();
            $validator->validate($message);
        } catch (Exception $e) {
            $logger = $factory->getLogger();
            $logger->error("Caught exception while validating AWS SNS message: $e");
            return;
        }

        if ($message->get('Type') === 'SubscriptionConfirmation') {
            $url = $message->get('SubscribeURL');
            (new Client)->get($message->get('SubscribeURL'))->send();
            return array();
        } elseif ($message->get('Type') === 'Notification') {
            $rows = array(
                'bounced' => array(
                    'hashIds' => array(),
                    'emails'  => array()
                ),
                'unsubscribed' => array(
                    'hashIds' => array(),
                    'emails'  => array()
                )
            );
            $json = json_decode($message->get('Message'));
            $type = $json->notificationType;
            if ($type === "Bounce") {
                $bounceType = $json->bounce->bounceType;
                if ($bounceType !== "Transient") {
                    $sentId = $json->mail->messageId;
                    $reason = $type;
                    $statRepository = $factory->getEntityManager()->getRepository('MauticEmailBundle:Stat');
                    $stat = $statRepository->getTransportIdStatus($sentId);
                    if ($stat) {
                        $hash = $stat->getTrackingHash();
                        $factory->getLogger()->debug("Adding SES bounce for email hash $hash");
                        $rows['bounced']['hashIds'][$hash] = $reason;
                    } else {
                        foreach ($json->bounce->bouncedRecipients as $address) {
                            $email = $address->emailAddress;
                            $factory->getLogger()->debug("Setting SES bounce for email address $email");
                            $rows['bounced']['emails'][$email] = $reason;
                        }
                    }
                }
            } else if ($type === "Complaint") {
                $sentId = $json->mail->messageId;
                $reason = $type;
                $statRepository = $factory->getEntityManager()->getRepository('MauticEmailBundle:Stat');
                $stat = $statRepository->getTransportIdStatus($sentId);
                if ($stat) {
                    $hash = $stat->getTrackingHash();
                    $factory->getLogger()->debug("Adding SES complaint for email hash $hash");
                    $rows['unsubscribed']['hashIds'][$hash] = $reason;
                } else {
                    foreach ($json->complaint->complainedRecipients as $address) {
                        $email = $address->emailAddress;
                        $factory->getLogger()->debug("Setting SES complaint for email address $email");
                        $rows['unsubscribed']['emails'][$email] = $reason;
                    }
                }
            }

            return $rows;
        }
    }
}

class AmazonSmtpResponseListener implements \Swift_Events_ResponseListener {
    private $factory;
    private $message;
    private $finished;

    public function __construct( MauticFactory $factory, \Swift_Mime_Message $message ) {
        $this->factory = $factory;
        $this->message = $message;
        $this->finished = false;
    }

    /**
     * Invoked immediately following a response coming back.
     *
     * @param Swift_Events_ResponseEvent $evt
     */
    public function responseReceived(\Swift_Events_ResponseEvent $event) {
        if ($this->finished) return;
        $res = explode(" ", $event->getResponse());
        if ($res[0] === "221") {
            $this->finished = true;
        }
        if ($res[0] === "250" && $res[1] === "Ok" && $res[2]) {
            $id = trim($res[2]);
            $hash = $this->message->getHeaders()->get('X-mautic-hash');
            if (method_exists($this->message, 'getMailer')) {
                // If the message has a MailHelper, we set the transportId
                // directly because no stat has been saved
                $this->factory->getLogger()->debug("Setting mailer transportId to $id");
                $mailer = $this->message->getMailer();
                $mailer->setTransportId($id);
            } else if ($hash) {
                // If the message has a hash, we look up the stat entity for
                // that hash and update its transportId
                $this->factory->getLogger()->debug("Updating transportId for hash $hash");
                $statRepository = $this->factory->getEntityManager()->getRepository('MauticEmailBundle:Stat');
                $stat = $statRepository->getEmailStatus($this->message->leadIdHash);
                if ($stat) {
                    $this->factory->getLogger()->debug("Setting stat transportId to $id");
                    $stat->setTransportId($id);
                    $statRepository->saveEntity($stat);
                } else {
                    $this->factory->getLogger()->warning("No stat found for message $hash ($id)");
                }
            } else {
                $this->factory->getLogger()->warning("No hash found for message ($id)");
            }
            $this->finished = true;
        }
    }
}

