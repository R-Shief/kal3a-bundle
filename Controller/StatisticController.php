<?php

namespace Rshief\Bundle\Kal3aBundle\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use FOS\RestBundle\Controller\Annotations\RouteResource;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class StatisticsController
 * @package Rshief\Bundle\Kal3aBundle\Controller
 * @RouteResource("TagStatistic")
 */
class StatisticController extends FOSRestController
{
    /**
     * @param  \Symfony\Component\HttpFoundation\Request $request
     * @return mixed
     */
    public function cgetAction(Request $request)
    {
        $q = $request->get('q');

        /** @var Connection $conn */
        $conn = $this->get('database_connection');

        if ($q) {
            $result = $conn->executeQuery('SELECT DISTINCT tag FROM tag_statistics WHERE tag LIKE ?', array($q .'%'))->fetchAll(Query::HYDRATE_SCALAR);
        } else {
            $result = $conn->executeQuery('SELECT DISTINCT tag FROM tag_statistics')->fetchAll(Query::HYDRATE_SCALAR);
        }
        $result = array_map(function ($value) { return $value[0]; }, $result);

        return $result;
    }

    public function getAction($tag)
    {
        /** @var Connection $conn */
        $conn = $this->get('database_connection');
        $queryBuilder = $conn->createQueryBuilder();

        /** @var \Doctrine\DBAL\Statement $query */
        $query = $queryBuilder->select('t.timestamp', 't.sum')
            ->from('tag_statistics', 't')
            ->where('t.tag = ?')
            ->setParameter(0, $tag)
            ->execute();

        $result = $query->fetchAll();

        return $result;
    }

    /**
     * @param $tag
     * @return \FOS\RestBundle\View\View
     */
    public function getStatisticsAction($tag)
    {
        $group = 4;
        $dm = $this->get('doctrine_couchdb');

        /** @var \Doctrine\CouchDB\CouchDBClient $default_client */
        $default_client = $dm->getConnection();

        $query = $default_client->createViewQuery('stats', 'tag');
        $query->setStale('ok');

        // All other executions will allow stale results.
        $query->setGroup(true);
        $query->setGroupLevel($group);
        $query->setIncludeDocs(false);
        $query->setReduce(true);

        $query->setStartKey(array($tag));
        $query->setEndKey(array($tag, array()));

        $result = $query->execute();

        return array_map(function ($value) {
                $date = new \DateTime();
                $date->setDate($value['key'][1], $value['key'][2], $value['key'][3]);
                $date = $date->format('Y-m-d');

                return array(
                    $date => $value['value']['sum'],
                );
            }, $result->toArray());

    }

    /**
     * @return \FOS\RestBundle\View\View
     */
    public function getSummaryAction()
    {
        $output = array();
        $tr = $this->getDoctrine()->getRepository('BangpoundTwitterStreamingBundle:Track');

        $tracks = $tr->findActiveTracks();
        foreach ($tracks as $track) {
            $output[$track] = $this->getStatisticsAction($track);
        }

        return $output;
    }
}
