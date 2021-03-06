<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\EmailBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\IpAddress;
use Mautic\CoreBundle\Helper\EmojiHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
/**
 * Class Stat
 *
 * @package Mautic\EmailBundle\Entity
 */
class Stat
{

    /**
     * @var int
     */
    private $id;

    /**
     * @var Email
     */
    private $email;

    /**
     * @var \Mautic\LeadBundle\Entity\Lead
     */
    private $lead;

    /**
     * @var string
     */
    private $emailAddress;

    /**
     * @var \Mautic\LeadBundle\Entity\LeadList
     */
    private $list;

    /**
     * @var \Mautic\CoreBundle\Entity\IpAddress
     */
    private $ipAddress;

    /**
     * @var \DateTime
     */
    private $dateSent;

    /**
     * @var bool
     */
    private $isRead = false;

    /**
     * @var bool
     */
    private $isFailed = false;

    /**
     * @var bool
     */
    private $viewedInBrowser = false;

    /**
     * @var \DateTime
     */
    private $dateRead;

    /**
     * @var string
     */
    private $trackingHash;

    /**
     * @var int
     */
    private $retryCount = 0;

    /**
     * @var string
     */
    private $source;

    /**
     * @var int
     */
    private $sourceId;

    /**
     * @var string
     */
    private $transportId;

    /**
     * @var array
     */
    private $tokens = array();

    /**
     * @var string
     *
     * @deprecated 1.2.3 - to be removed in 2.0
     */
    private $copy;

    /**
     * @var Copy
     */
    private $storedCopy;

    /**
     * @var int
     */
    private $openCount;

    /**
     * @var \DateTime
     */
    private $lastOpened;

    /**
     * @var array
     */
    private $openDetails = array();

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata (ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('email_stats')
            ->setCustomRepositoryClass('Mautic\EmailBundle\Entity\StatRepository')
            ->addIndex(array('email_id', 'lead_id'), 'stat_email_search')
            ->addIndex(array('is_failed'), 'stat_email_failed_search')
            ->addIndex(array('is_read'), 'stat_email_read_search')
            ->addIndex(array('tracking_hash'), 'stat_email_hash_search')
            ->addIndex(array('transport_id'), 'stat_email_transport_id_search')
            ->addIndex(array('source', 'source_id'), 'stat_email_source_search');

        $builder->addId();

        $builder->createManyToOne('email', 'Email')
            ->inversedBy('stats')
            ->addJoinColumn('email_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->addLead(true, 'SET NULL');

        $builder->createField('emailAddress', 'string')
            ->columnName('email_address')
            ->build();

        $builder->createManyToOne('list', 'Mautic\LeadBundle\Entity\LeadList')
            ->addJoinColumn('list_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->addIpAddress(true);

        $builder->createField('dateSent', 'datetime')
            ->columnName('date_sent')
            ->build();

        $builder->createField('isRead', 'boolean')
            ->columnName('is_read')
            ->build();

        $builder->createField('isFailed', 'boolean')
            ->columnName('is_failed')
            ->build();

        $builder->createField('viewedInBrowser', 'boolean')
            ->columnName('viewed_in_browser')
            ->build();

        $builder->createField('dateRead', 'datetime')
            ->columnName('date_read')
            ->nullable()
            ->build();

        $builder->createField('trackingHash', 'string')
            ->columnName('tracking_hash')
            ->nullable()
            ->build();

        $builder->createField('retryCount', 'integer')
            ->columnName('retry_count')
            ->nullable()
            ->build();

        $builder->createField('source', 'string')
            ->nullable()
            ->build();

        $builder->createField('sourceId', 'integer')
            ->columnName('source_id')
            ->nullable()
            ->build();

        $builder->createField('transportId', 'string')
            ->columnName('transport_id')
            ->nullable()
            ->build();

        $builder->createField('tokens', 'array')
            ->nullable()
            ->build();

        /**
         * @deprecated 1.2.3 - to be removed in 2.0
         */
        $builder->addNullableField('copy', 'text');

        $builder->createManyToOne('storedCopy', 'Mautic\EmailBundle\Entity\Copy')
            ->addJoinColumn('copy_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->addNullableField('openCount', 'integer', 'open_count');

        $builder->addNullableField('lastOpened', 'datetime', 'last_opened');

        $builder->addNullableField('openDetails', 'array', 'open_details');
    }

    /**
     * Prepares the metadata for API usage
     *
     * @param $metadata
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata)
    {
        $metadata->setGroupPrefix('stat')
            ->addProperties(
                array(
                    'id',
                    'emailAddress',
                    'ipAddress',
                    'dateSent',
                    'isRead',
                    'isFailed',
                    'dateRead',
                    'retryCount',
                    'source',
                    'openCount',
                    'lastOpened',
                    'sourceId',
                    'trackingHash',
                    'viewedInBrowser',
                    'lead',
                    'email'
                )
            )
            ->build();
    }

    /**
     * @return mixed
     */
    public function getDateRead ()
    {
        return $this->dateRead;
    }

    /**
     * @param mixed $dateRead
     */
    public function setDateRead ($dateRead)
    {
        $this->dateRead = $dateRead;
    }

    /**
     * @return mixed
     */
    public function getDateSent ()
    {
        return $this->dateSent;
    }

    /**
     * @param mixed $dateSent
     */
    public function setDateSent ($dateSent)
    {
        $this->dateSent = $dateSent;
    }

    /**
     * @return Email
     */
    public function getEmail ()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail (Email $email = null)
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getId ()
    {
        return $this->id;
    }

    /**
     * @return IpAddress
     */
    public function getIpAddress ()
    {
        return $this->ipAddress;
    }

    /**
     * @param mixed $ip
     */
    public function setIpAddress (IpAddress $ip)
    {
        $this->ipAddress = $ip;
    }

    /**
     * @return mixed
     */
    public function getIsRead ()
    {
        return $this->isRead;
    }

    /**
     * @return mixed
     */
    public function isRead ()
    {
        return $this->getIsRead();
    }

    /**
     * @param mixed $isRead
     */
    public function setIsRead ($isRead)
    {
        $this->isRead = $isRead;
    }

    /**
     * @return Lead
     */
    public function getLead ()
    {
        return $this->lead;
    }

    /**
     * @param mixed $lead
     */
    public function setLead (Lead $lead = null)
    {
        $this->lead = $lead;
    }

    /**
     * @return mixed
     */
    public function getTrackingHash ()
    {
        return $this->trackingHash;
    }

    /**
     * @param mixed $trackingHash
     */
    public function setTrackingHash ($trackingHash)
    {
        $this->trackingHash = $trackingHash;
    }

    /**
     * @return \Mautic\LeadBundle\Entity\LeadList
     */
    public function getList ()
    {
        return $this->list;
    }

    /**
     * @param mixed $list
     */
    public function setList ($list)
    {
        $this->list = $list;
    }

    /**
     * @return mixed
     */
    public function getRetryCount ()
    {
        return $this->retryCount;
    }

    /**
     * @param mixed $retryCount
     */
    public function setRetryCount ($retryCount)
    {
        $this->retryCount = $retryCount;
    }

    /**
     *
     */
    public function upRetryCount ()
    {
        $this->retryCount++;
    }

    /**
     * @return mixed
     */
    public function getIsFailed ()
    {
        return $this->isFailed;
    }

    /**
     * @param mixed $isFailed
     */
    public function setIsFailed ($isFailed)
    {
        $this->isFailed = $isFailed;
    }

    /**
     * @return mixed
     */
    public function isFailed ()
    {
        return $this->getIsFailed();
    }

    /**
     * @return mixed
     */
    public function getEmailAddress ()
    {
        return $this->emailAddress;
    }

    /**
     * @param mixed $emailAddress
     */
    public function setEmailAddress ($emailAddress)
    {
        $this->emailAddress = $emailAddress;
    }

    /**
     * @return mixed
     */
    public function getViewedInBrowser ()
    {
        return $this->viewedInBrowser;
    }

    /**
     * @param mixed $viewedInBrowser
     */
    public function setViewedInBrowser ($viewedInBrowser)
    {
        $this->viewedInBrowser = $viewedInBrowser;
    }

    /**
     * @return mixed
     */
    public function getSource ()
    {
        return $this->source;
    }

    /**
     * @param mixed $source
     */
    public function setSource ($source)
    {
        $this->source = $source;
    }

    /**
     * @return mixed
     */
    public function getSourceId ()
    {
        return $this->sourceId;
    }

    /**
     * @param mixed $sourceId
     */
    public function setSourceId ($sourceId)
    {
        $this->sourceId = (int)$sourceId;
    }

    /**
     * @return mixed
     */
    public function getTransportId ()
    {
        return $this->transportId;
    }

    /**
     * @param mixed $transportId
     */
    public function setTransportId ($transportId)
    {
        $this->transportId = (string)$transportId;
    }

    /**
     * @return mixed
     */
    public function getTokens ()
    {
        return $this->tokens;
    }

    /**
     * @param mixed $tokens
     */
    public function setTokens ($tokens)
    {
        $this->tokens = $tokens;
    }

    /**
     * @deprecated 1.2.3 - to be removed in 2.0
     *
     * @return mixed
     */
    public function getCopy()
    {
        return $this->copy;
    }

    /**
     * @deprecated 1.2.3 - to be removed in 2.0
     *
     * @param mixed $copy
     *
     * @return Stat
     */
    public function setCopy($copy)
    {
        // Ensure it's clean of emoji
        $copy = EmojiHelper::toShort($copy);

        $this->copy = $copy;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOpenCount()
    {
        return $this->openCount;
    }

    /**
     * @param mixed $openCount
     *
     * @return Stat
     */
    public function setOpenCount($openCount)
    {
        $this->openCount = $openCount;

        return $this;
    }

    /**
     * @param $details
     */
    public function addOpenDetails($details)
    {
        $this->openDetails[] = $details;

        $this->openCount++;
    }

    /**
     * Up the sent count
     *
     * @return Stat
     */
    public function upOpenCount()
    {
        $count = (int) $this->openCount + 1;
        $this->openCount = $count;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getLastOpened()
    {
        return $this->lastOpened;
    }

    /**
     * @param mixed $lastOpened
     *
     * @return Stat
     */
    public function setLastOpened($lastOpened)
    {
        $this->lastOpened = $lastOpened;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOpenDetails()
    {
        return $this->openDetails;
    }

    /**
     * @param mixed $openDetails
     *
     * @return Stat
     */
    public function setOpenDetails($openDetails)
    {
        $this->openDetails = $openDetails;

        return $this;
    }

    /**
     * @return Copy
     */
    public function getStoredCopy()
    {
        return $this->storedCopy;
    }

    /**
     * @param Copy $storedCopy
     *
     * @return Stat
     */
    public function setStoredCopy(Copy $storedCopy)
    {
        $this->storedCopy = $storedCopy;

        return $this;
    }
}
