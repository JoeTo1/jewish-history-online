<?php

// src/AppBundle/Command/GreetCommand.php
namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class ArticleAdjustCommand extends BaseEntityCommand
{
    protected function configure()
    {
        $this
            ->setName('article:adjust')
            ->setDescription('Adjust Header')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'TEI file'
            )
        ;
    }

    protected function getDataFromAdminDb($data, $translator)
    {
        if (!preg_match('/(article|source)\-(\d+)$/', $data['uid'], $matches)) {
            return $data;
        }

        $conn =  $this->getContainer()->get('doctrine.dbal.admin_connection');

        switch ($matches[1]) {
            case 'article':
                $sql = "SELECT 'article' AS query_type, Message.*"
                     . ", Translator.slug AS translator_slug, Translator.firstname, Translator.lastname"
                     . " FROM Message"
                     . " LEFT OUTER JOIN User Translator"
                     . " ON Message.translator = Translator.id"
                     . " WHERE Message.id=:id AND Message.status <> -1"
                     ;
                $params = [ 'id' => $matches[2] ];
                break;

            case 'source':
                $sql = "SELECT 'source' AS query_type, Publication.*"
                     . ", Translator.slug AS translator_slug, Translator.firstname, Translator.lastname"
                     . ", Provider.name AS provider_name, Provider.gnd AS provider_gnd"
                     . " FROM Publication"
                     . " LEFT OUTER JOIN User Translator"
                     . " ON Publication.translator = Translator.id"
                     . " LEFT OUTER JOIN Publisher Provider"
                     . " ON Publication.publisher_id = Provider.id"
                     . " WHERE Publication.id=:id AND Publication.status <> -1"
                     ;
                $params = [ 'id' => $matches[2] ];
                break;
        }

        $result = $conn->fetchAssoc($sql, $params);

        if (false === $result || empty($result)) {
            return $data;
        }

        // common stuff
        if (!empty($result['lang'])
            && \AppBundle\Utils\Iso639::code2bTo3($result['lang']) != $data['lang'])
        {
            $data['translator'] = [
                $result['translator_slug'] => join(' ', [ $result['firstname'], $result['lastname'] ]),
            ];
            // admin uses 639-2B ('ger' instead of 'deu')
            $data['translatedFrom'] = \AppBundle\Utils\Iso639::code2bTo3($result['lang']);
        }

        switch ($result['query_type']) {
            case 'article':
                $genre = 'Interpretation';
                if (preg_match('/^Einführung/', $result['subject'])) {
                    $genre = 'Introduction';
                }
                $data['genre'] = $translator->trans($genre);

                if (!empty($result['section'])) {
                    $sql = "SELECT name FROM Term WHERE id IN (?) AND status <> -1";
                    $stmt = $conn->executeQuery($sql,
                                             [ explode(',', $result['section']) ],
                                             array(\Doctrine\DBAL\Connection::PARAM_INT_ARRAY));
                    // TODO: set primary topic according to editor
                    $terms = $stmt->fetchAll();

                    $topics = [];
                    foreach ($terms as $term) {
                        /** @Ignore */
                        $topics[] = $translator->trans(\AppBundle\Controller\TopicController::lookupLocalizedTopic($term['name'], $translator, 'de')); // db-terms in German
                    }
                    $data['topic'] = $topics;
                }
                if (!empty($result['slug'])) {
                    // TODO: needs to be language dependant!
                    $data['slug'] = $result['slug'];
                }
                $dates = [];
                if (!empty($result['published'])) {
                    $published = new \DateTime($result['published']);
                    $dates['firstPublication'] = $published->format('Y-m-d');
                }
                if (!empty($dates)) {
                    $data['dates'] = $dates;
                }
                if (!empty($result['license'])) {
                    switch ($result['license']) {
                        case 'CC BY-NC-ND':
                            $data['license'] = [
                                'http://creativecommons.org/licenses/by-nc-nd/4.0/'
                                => $translator->trans('license.by-nc-nd'),
                                ];
                            break;

                        default:
                            die('TODO: handle ' . $data['license']);
                    }
                }
                break;

            case 'source':
                $locale = $translator->getLocale();
                $data['genre'] = $translator->trans('Source') . ':Text';

                      // articles related to this source
                $sql = "SELECT Message.id AS id, subject, status"
                     . " FROM Message, MessagePublication"
                     . " WHERE MessagePublication.publication_id=? AND MessagePublication.message_id=Message.id AND Message.status <> 1"
                     . " ORDER BY Message.id DESC";

                $stmt = $conn->executeQuery($sql, [ $result['id'] ]);
                $articles = $stmt->fetchAll();
                $seriesStmt = [];
                foreach ($articles as $article) {
                    $corresp = sprintf('#jgo:article-%d', $article['id']);
                    $seriesStmt[$corresp] = $article['subject']; // TODO: get actual title, not the one from db
                }
                if (!empty($seriesStmt)) {
                    $data['seriesStmt'] = $seriesStmt;
                }

                $bibl = [];
                if (!empty($result['author'])) {
                    $bibl['author'] = $result['author'];
                }
                if (!empty($result['place_identifier'])) {
                    $place = $this->findPlaceByUri($result['place_identifier']);
                    if (is_null($place)) {
                        die('TODO: get info for ' . $result['place_identifier']);
                    }
                    $bibl['placeName'] = [
                        '@ref' => $result['place_identifier'],
                        '@value' => $place->getNameLocalized($locale),
                    ];
                }
                if (!empty($result['indexingdate'])) {
                    $when = $result['indexingdate'];
                    if (!empty($result['displaydate'])) {
                        $date = $result['displaydate'];
                    }
                    else {
                        $date = \AppBundle\Utils\Formatter::dateIncomplete($when, $locale);
                    }

                    while (preg_match('/(.+)\-00$/', $when, $matches)) {
                        $when = $matches[1];
                    }
                    $bibl['date'] = [ '@when' => $when,
                                      '@value' => $date ];
                }
                if (!empty($result['provider_name'])
                    && $result['provider_name'] != 'unbekannt')
                {
                    $bibl['orgName'] = [ '@value' => $result['provider_name'] ];
                    if (!empty($result['provider_gnd'])) {
                        $bibl['orgName']['@ref'] = 'http://d-nb.info/gnd/' . $result['provider_gnd'];
                    }
                }
                if (!empty($result['archive_location'])) {
                    $bibl['idno'] = $result['archive_location'];
                }
                $data['bibl'] = $bibl;

                if (!empty($result['attribution'])) {
                    $attribution = json_decode($result['attribution'], true);
                    if (false !== $attribution && !empty($attribution[$locale])) {
                        $data['license'] = [ '' => $attribution[$locale] ];
                    }
                }
                break;

            default:
                die('TODO: handle ' . $result['query_type']);
        }

        return $data;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fname = $input->getArgument('file');

        $fs = new Filesystem();

        if (!$fs->exists($fname)) {
            $output->writeln(sprintf('<error>%s does not exist</error>', $fname));
            return 1;
        }

        $teiHelper = new \AppBundle\Utils\TeiHelper();

        $uid = $langCode1 = null;
        if (preg_match('/(article|source)\-(\d+)\.(de|en)\.(xml|tei)$/', $fname, $matches)) {
            $uid = sprintf('jgo:%s-%d', $matches[1], $matches[2]);
            $langCode1 = $matches[3];
        }
        if (empty($uid) || empty($langCode1)) {
            die('TODO: determine uid/langCode1 from teiHeader');
        }
        $data = [
                    'uid' => $uid,
                    'lang' => \AppBundle\Utils\Iso639::code1To3($langCode1),
                ];

        // set the publisher - needs to be localized
        $translator = $this->getContainer()->get('translator');
        $translator->setLocale(\AppBundle\Utils\Iso639::code3To1($data['lang']));

        $data['publisher'] = [
            'orgName' => $translator->trans('Institute for the History of the German Jews'),
            'email' => 'redaktion@juedische-geschichte-online.net',
            'address' => [
                'addrLine' => 'Beim Schlump 83, 20144 Hamburg',
            ],
        ];

        // all the data from the admin-database
        $dataFromDb = $this->getDataFromAdminDb($data, $translator);
        /*
        var_dump($dataFromDb);
        exit;
        */

        $xml = $teiHelper->adjustHeader($fname, $dataFromDb);
        if (false === $xml) {
            $output->writeln(sprintf('<error>%s could not be loaded</error>', $fname));
            return 1;
        }

        echo $xml->asXML();

        return 0;
    }
}
