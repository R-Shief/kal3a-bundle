<?php

namespace Rshief\Bundle\Kal3aBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints\DateTime;
use Carbon\Carbon;

/**
 * Class PopulateStatsCommand
 * @package Rshief\Bundle\Kal3aBundle\Command
 */
class PopulateTrendsCommand extends ContainerAwareCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->setName('rshief:trends:populate')
            ->addArgument('design_document', InputArgument::REQUIRED, 'Design document')
            ->addArgument('view_name', InputArgument::REQUIRED, 'View name')
            ->addArgument('date', InputArgument::OPTIONAL, 'Date', Carbon::now(new \DateTimeZone('UTC'))->modify('-1 day'))
            ->addArgument('interval', InputArgument::OPTIONAL, 'Interval', 'P1D')
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Get CouchDB Clients
        $default_client = $this->getContainer()->get('doctrine_couchdb.client.default_connection');
        $stats_client = $this->getContainer()->get('doctrine_couchdb.client.trends_connection');

        // DB view arguments
        $design_document = $input->getArgument('design_document');
        $view_name = $input->getArgument('view_name');

        /** @var \DateTime $date */
        $date = $input->getArgument('date');
        $interval = $input->getArgument('interval');

        $limit = 1000;

        // Executing the query without grouping allows the view to be refreshed.
        $query = $default_client->createViewQuery($design_document, $view_name);
        $output->writeln('Updating view.');
        $query->execute();

        // All other executions will allow stale results.
        $query->setGroup(true);
        $query->setStale('ok');

        $end = clone $date;
        $end->add(new \DateInterval($interval));

        $output->writeln(sprintf('%s -> %s', $date->format('Y-m-d'), $end->format('Y-m-d')));

        $query->setStartKey([(int) $date->format('Y'), (int) $date->format('m'), (int) $date->format('d') ]);
        $query->setEndKey([(int) $end->format('Y'), (int) $end->format('m'), (int) $end->format('d'), array() ]);
        $query->setLimit($limit + 1);

        do {
            $result = $query->execute();
            $next_start_key = null;
            if (count($result) > 0) {
                $max = count($result) > $limit ? $limit : count($result);
                $max_key = count($result) > $limit ? $limit - 1 : count($result) - 1;
                $output->writeln(sprintf('%s -> %s', $result[0]['key'][5], $result[$max_key]['key'][5]));

                if (count($result) == $limit + 1) {
                    $next_start_key = $result[$limit]['key'];
                }

                $ids = array();
                foreach ($result as $row) {
                    $ids[] = $this->generateId($design_document, $view_name, $row['key']);
                }

                $results = $stats_client->getHttpClient()->request('POST', '/' . $stats_client->getDatabase() . '/_all_docs',
                    json_encode(
                        array('keys' => array_values($ids))
                    )
                );

                $rev_map = array();
                foreach ($results->body['rows'] as $row) {
                    if (!isset($row['error'])) {
                        $rev_map[$row['id']] = $row['value']['rev'];
                    }
                }

                $bulk = $stats_client->createBulkUpdater();

                foreach ($result as $row) {
                    $document = array();
                    $document['_id'] = $id = $this->generateId($design_document, $view_name, $row['key']);

                    if (isset($rev_map[$id])) {
                        $document['_rev'] = $rev_map[$id];
                    }

                    $document['tag'] = array_pop($row['key']);

                    $date = new \DateTime;
                    $date->setDate($row['key'][0], $row['key'][1], $row['key'][2]);
                    $date->setTime($row['key'][3], $row['key'][4]);
                    $document['date'] = $date->format('Y-m-d H:i:s.u');

                    $document['value'] = $row['value'];
                    $bulk->updateDocument($document);
                }
                $result = $bulk->execute();

                $query->setStartKey($next_start_key);
            }
        } while ($next_start_key);
    }

    private function generateId($design_document, $view_name, $key)
    {
        return $design_document .' '. $view_name .' '. implode(' ', $key);
    }
}
