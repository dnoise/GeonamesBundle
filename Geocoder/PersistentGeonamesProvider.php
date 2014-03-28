<?php
namespace Giosh94mhz\GeonamesBundle\Geocoder;

use Doctrine\Common\Persistence\ObjectManager;
use Geocoder\Provider\LocaleAwareProviderInterface;
use Geocoder\Exception\NoResultException;
use Geocoder\Exception\UnsupportedException;
use Geocoder\Provider\ProviderInterface;
use Geocoder\Geocoder;
use Giosh94mhz\GeonamesBundle\Model\Toponym;

class PersistentGeonamesProvider implements LocaleAwareProviderInterface
{
    protected $om;

    protected $locale;

    protected $maxResults;

    protected $ipProvider;

    public function __construct(ObjectManager $om, $locale = null)
    {
        $this->om = $om;
        $this->locale = $locale ?: 'en';
        $this->maxResults = Geocoder::MAX_RESULTS;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return "persistent_geonames";
    }

    /**
     * {@inheritDoc}
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * {@inheritDoc}
     */
    public function setMaxResults($maxResults)
    {
        $this->maxResults = $maxResults;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getMaxResults()
    {
        return $this->maxResults;
    }

    /**
     * {@inheritDoc}
     */
    public function getGeocodedData($address)
    {
        if (filter_var($address, FILTER_VALIDATE_IP)) {
            return $this->getGeocodedIpData($address);
        }

        // Find by address
        $dql = <<<DQL
SELECT t
FROM Giosh94mhzGeonamesBundle:Toponym t
WHERE t.name = :name order by t.population desc
DQL;

        /* @var $query \Doctrine\ORM\Query */
        $query = $this->om->createQuery($dql);

        //Cache
        $key = __METHOD__. serialize($query->getParameters());
        $time = 600;

        $query->useResultCache(true, $time, $key);
        $query->useQueryCache(true);

        //$query->setCacheable(true);
        //$newkey = md5(__METHOD__. serialize($query->getParameters())."offset=".$query->getFirstResult()."length=".$query->getMaxResults().serialize($query->getHints()).$query->getDQL());
        //$query->setHydrationCacheProfile(new QueryCacheProfile($time, $newkey));

        $toponyms = $query
            ->setParameter('name', "%{$address}%")
            ->setMaxResults($this->maxResults)
            ->execute()
        ;

        if (empty($toponyms))
            throw new NoResultException();

        return $this->toponymsToResultArray($toponyms);
    }

    private function getGeocodedIpData($address)
    {
        // This API doesn't handle IPs directly
        if (! $this->ipProvider) {
            throw new UnsupportedException(
                __CLASS__ . 'does not support IP addresses. Use \'setIpProvider\' to chain a Provider (for IP only).'
            );
        }

        $data = $this->ipProvider->getGeocodedData($address);
        if (empty($data))
            throw new NoResultException();

        $backupMaxResults = $this->maxResults;
        $this->maxResults = 1;

        try {
            $result = $this->getReversedData(array($data[0]['latitude'], $data[0]['longitude']));

        } catch (\Exception $e) {
            $this->maxResults = $backupMaxResults;
            throw $e;
        }

        $this->maxResults = $backupMaxResults;

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getReversedData(array $coordinates)
    {
        /* @var $repo \Giosh94mhz\GeonamesBundle\Model\Repository\ToponymRepository */
        $repo = $this->om->getRepository('Giosh94mhzGeonamesBundle:Toponym');

        $toponyms = $repo->findByDistance($coordinates[0], $coordinates[1], 10, $this->maxResults);
        if (empty($toponyms))
            throw new NoResultException();

        return $this->toponymsToResultArray($toponyms);
    }

    public function getIpProvider()
    {
        return $this->ipProvider;
    }

    public function setIpProvider(ProviderInterface $ipProvider)
    {
        $this->ipProvider = $ipProvider;

        return $this;
    }

    protected function toponymsToResultArray($toponyms)
    {
        return array_map(function (Toponym $toponym) {
            return array(
                'geonameid' => $toponym->getId(),
                'toponym' => $toponym
            );
        }, $toponyms);
    }
}
