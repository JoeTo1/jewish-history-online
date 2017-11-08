<?php

/**
 *
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\CssSelector\CssSelectorConverter;

abstract class RenderTeiController
extends Controller
{
    use SharingBuilderTrait,
        \AppBundle\Utils\RenderTeiTrait; // use shared method renderTei()

    protected function locateTeiResource($fnameXml)
    {
        $kernel = $this->container->get('kernel');

        try {
            $pathToXml = $kernel->locateResource('@AppBundle/Resources/data/tei/' . $fnameXml);
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return $pathToXml;
    }

    protected function renderTei($fnameXml, $fnameXslt = 'dtabf_article.xsl', $options = [])
    {
        $kernel = $this->container->get('kernel');

        $locateResource = !array_key_exists('locateXmlResource', $options)
            || $options['locateXmlResource'];
        if ($locateResource) {
            $pathToXml = $this->locateTeiResource($fnameXml);
            if (false === $pathToXml) {
                return false;
            }
        }
        else {
            $pathToXml = $fnameXml;
        }

        $proc = $this->get('app.xslt');
        $pathToXslt = $kernel->locateResource('@AppBundle/Resources/data/xsl/' . $fnameXslt);
        $res = $proc->transformFileToXml($pathToXml, $pathToXslt, $options);
        return $res;
    }

    function removeByCssSelector($html, $selectorsToRemove)
    {
        $crawler = new \Symfony\Component\DomCrawler\Crawler();
        $crawler->addHtmlContent($html);

        foreach ($selectorsToRemove as $selector) {
            $crawler->filter($selector)->each(function ($crawler) {
                foreach ($crawler as $node) {
                    // var_dump($node);
                    $node->parentNode->removeChild($node);
                }
            });

            /*
            // TODO: switch to reduce - doesn't work yet
            $crawler->filter($selector)->reduce(function ($crawler, $i) {
                return false;

            });
            */
        }

        return $crawler->html();
    }

    protected function buildRefLookup($refs, $language)
    {
        $refMap = [];

        if (empty($refs)) {
            return ;
        }

        // make sure we only pick-up the published ones
        $query = $this->getDoctrine()
            ->getManager()
            ->createQuery("SELECT a"
                          . " FROM AppBundle:Article a"
                          . " WHERE a.status IN (1)"
                          . " AND a.uid IN (:refs)"
                          . (!empty($language) ? ' AND a.language=:language' : '')
                          . " ORDER BY a.name")
            ->setParameter('refs', $refs, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY)
            ;
        if (!empty($language)) {
            $query->setParameter('language', $language);
        }

        $result = $query->getResult();

        $translator = $this->get('translator');
        foreach ($result as $article) {
            $prefix = null;
            switch ($article->getArticleSection()) {
                case 'background':
                    $prefix = $translator->trans('Topic');
                    $route = 'topic-background';
                    $params = [ 'slug' => $article->getSlug() ];
                    break;

                case 'interpretation':
                    $prefix = $translator->trans('Interpretation');
                    $route = 'article';
                    $params = [ 'slug' => $article->getSlug(true) ];
                    break;

                case 'source':
                    $prefix = $translator->trans('Source');
                    $route = 'source';
                    $params = [ 'uid' => $article->getUid() ];
                    break;

                default:
                    $route = null;
            }

            if (!is_null($route)) {
                $entry = [
                    'href' => $this->generateUrl($route, $params, true),
                ];
                if (!empty($prefix)) {
                    $entry['headline'] = $prefix . ': ' . $article->getName();
                    if (count($article->getAuthor()) > 0) {
                        $authors = [];
                        foreach ($article->getAuthor() as $author) {
                            $authors[] = $author->getFullname(true);
                        }
                        $entry['headline'] .= ' (' . implode(', ', $authors) . ')';
                    }
                }
                $refMap[$article->getUid()] = $entry;
            }
        }

        return $refMap;
    }

    protected function buildEntityLookup($entities)
    {
        $entitiesByType = [ 'person' => [], 'place' => [], 'organization' => [] ];
        foreach ($entities as $entity) {
            if (!array_key_exists($entity['type'], $entitiesByType)) {
                continue;
            }
            if (!array_key_exists($entity['uri'], $entitiesByType[$entity['type']])) {
                $entitiesByType[$entity['type']][$entity['uri']] = [ 'count' => 0 ];
            }
            ++$entitiesByType[$entity['type']][$entity['uri']]['count'];
        }

        foreach ($entitiesByType as $type => $uriCount) {
            switch ($type) {
                case 'person':
                    $personGnds = $personDjhs = $personStolpersteine = [];
                    foreach ($uriCount as $uri => $count) {
                        if (preg_match('/^'
                                       . preg_quote('http://d-nb.info/gnd/', '/')
                                       . '(\d+[xX]?)$/', $uri, $matches))
                        {
                            $personGnds[$matches[1]] = $uri;
                        }
                        else if (preg_match('/^'
                                    . preg_quote('http://www.dasjuedischehamburg.de/inhalt/', '/')
                                    . '(.+)$/', $uri, $matches))
                        {
                            $personDjhs[urldecode($matches[1])] = $uri;
                        }
                        else if (preg_match('/^'
                                            . preg_quote('http://www.stolpersteine-hamburg.de/', '/')
                                            . '.*?BIO_ID=(\d+)/', $uri, $matches))
                        {
                            $personStolpersteine[$matches[1]] = $uri;
                        }
                    }
                    if (!empty($personGnds)) {
                        $persons = $this->getDoctrine()
                            ->getRepository('AppBundle:Person')
                            ->findBy([ 'gnd' => array_keys($personGnds) ]);
                        foreach ($persons as $person) {
                            if ($person->getStatus() >= 0) {
                                $uri = $personGnds[$person->getGnd()];
                                $details = [ 'url' => $this->generateUrl('person-by-gnd', [ 'gnd' => $person->getGnd()]) ];
                                $entitiesByType[$type][$uri] += $details;
                            }
                        }
                    }
                    if (!empty($personDjhs)) {
                        $persons = $this->getDoctrine()
                            ->getRepository('AppBundle:Person')
                            ->findBy([ 'djh' => array_keys($personDjhs) ]);
                        foreach ($persons as $person) {
                            if ($person->getStatus() >= 0) {
                                $uri = $personDjhs[$person->getDjh()];
                                $details = [ 'url' => $this->generateUrl('person', [ 'id' => $person->getId()]) ];
                                $entitiesByType[$type][$uri] += $details;
                            }
                        }
                    }
                    if (!empty($personStolpersteine)) {
                        $persons = $this->getDoctrine()
                            ->getRepository('AppBundle:Person')
                            ->findBy([ 'stolpersteine' => array_keys($personStolpersteine) ]);
                        foreach ($persons as $person) {
                            if ($person->getStatus() >= 0) {
                                $uri = $personStolpersteine[$person->getStolpersteine()];
                                $details = [ 'url' => $this->generateUrl('person', [ 'id' => $person->getId()]) ];
                                $entitiesByType[$type][$uri] += $details;
                            }
                        }
                    }
                    break;

                case 'place':
                    $placeTgns = [];
                    foreach ($uriCount as $uri => $count) {
                        if (preg_match('/^'
                                       . preg_quote('http://vocab.getty.edu/tgn/', '/')
                                       . '(\d+?)$/', $uri, $matches))
                        {
                            $placeTgns[$matches[1]] = $uri;
                        }
                    }
                    if (!empty($placeTgns)) {
                        $places = $this->getDoctrine()
                            ->getRepository('AppBundle:Place')
                            ->findBy([ 'tgn' => array_keys($placeTgns) ]);
                        foreach ($places as $place) {
                            if (true /*$person->getStatus() >= 0 */) {
                                $uri = $placeTgns[$place->getTgn()];
                                $details = [ 'url' => $this->generateUrl('place-by-tgn', [ 'tgn' => $place->getTgn()]) ];
                                $entitiesByType[$type][$uri] += $details;
                            }
                        }
                    }
                    break;

                case 'organization':
                    $organizationGnds = [];
                    foreach ($uriCount as $uri => $count) {
                        if (preg_match('/^'
                                       . preg_quote('http://d-nb.info/gnd/', '/')
                                       . '(\d+[\-]?[\dxX]?)$/', $uri, $matches))
                        {
                            $organizationGnds[$matches[1]] = $uri;
                        }
                    }
                    if (!empty($organizationGnds)) {
                        $organizations = $this->getDoctrine()
                            ->getRepository('AppBundle:Organization')
                            ->findBy([ 'gnd' => array_keys($organizationGnds) ]);
                        foreach ($organizations as $organization) {
                            if ($organization->getStatus() >= 0) {
                                $uri = $organizationGnds[$organization->getGnd()];
                                $details = [ 'url' => $this->generateUrl('organization-by-gnd', [ 'gnd' => $organization->getGnd()]) ];
                                $entitiesByType[$type][$uri] += $details;
                            }
                        }
                    }
                    break;
            }
        }

        return $entitiesByType;
    }

    protected function buildGlossaryLookup($glossaryTerms, $locale)
    {
        $glossaryLookup = [];

        if (empty($glossaryTerms)) {
            return $glossaryLookup;
        }

        $language = \AppBundle\Utils\Iso639::code1to3($locale);

        $slugify = $this->container->get('cocur_slugify');

        $slugs = array_map(function ($term) use ($slugify) {
                                return $slugify->slugify($term);
                           },
                           $glossaryTerms);

        $termsBySlug = [];

        // TODO: only query for $slugs
        foreach ($this->getDoctrine()
                ->getRepository('AppBundle:GlossaryTerm')
                ->findBy([
                   'status' => [ 0, 1 ],
                   'language' => $language,
                   'slug' => $slugs,
                ]) as $term)
        {
            $termsBySlug[$term->getSlug()] = $term;
        }

        foreach ($glossaryTerms as $glossaryTerm) {
            $slug = $slugify->slugify($glossaryTerm);
            if (array_key_exists($slug, $termsBySlug)) {
                $term = $termsBySlug[$slug];
                $headline = $term->getHeadline();
                $headline = str_replace(']]', '', $headline);
                $headline = str_replace('[[', '→', $headline);
                $glossaryLookup[$glossaryTerm] = [
                    'slug' => $term->getSlug(),
                    'name' => $term->getName(),
                    'headline' => $headline,
                ];
            }
        }

        return $glossaryLookup;
    }

    protected function adjustMedia($html, $baseUrl, $imgClass = 'image-responsive')
    {
        $crawler = new \Symfony\Component\DomCrawler\Crawler();
        $crawler->addHtmlContent($html);

        $crawler->filter('audio > source')->each(function ($node, $i) use ($baseUrl) {
            $src = $node->attr('src');
            $node->getNode(0)->setAttribute('src', $baseUrl . '/' . $src);
        });

        // for https://github.com/iainhouston/bootstrap3_player
        $crawler->filter('audio')->each(function ($node, $i) use ($baseUrl) {
            $poster = $node->attr('data-info-album-art');
            if (!is_null($poster)) {
                $node->getNode(0)->setAttribute('data-info-album-art', $baseUrl . '/' . $poster);
            }
        });

        $crawler->filter('video > source')->each(function ($node, $i) use ($baseUrl) {
            $src = $node->attr('src');
            $node->getNode(0)->setAttribute('src', $baseUrl . '/' . $src);
        });

        $crawler->filter('video')->each(function ($node, $i) use ($baseUrl) {
            $poster = $node->attr('poster');
            if (!is_null($poster)) {
                $node->getNode(0)->setAttribute('poster', $baseUrl . '/' . $poster);
            }
        });

        $crawler->filter('img')->each(function ($node, $i) use ($baseUrl, $imgClass) {
            $src = $node->attr('src');
            $node->getNode(0)->setAttribute('src', $baseUrl . '/' . $src);
            if (!empty($imgClass)) {
                $node->getNode(0)->setAttribute('class', $imgClass);
            }
        });

        return $crawler->html();
    }

    protected function renderPdf($html, $filename = '', $dest = 'I')
    {
        /*
        // for debugging
        echo $html;
        exit;
        */

        // mpdf
        $pdfGenerator = new \AppBundle\Utils\PdfGenerator();

        /*
        // hyphenation
        list($lang, $region) = explode('_', $display_lang, 2);
        $pdfGenerator->SHYlang = $lang;
        $pdfGenerator->SHYleftmin = 3;
        */

        $fnameLogo = $this->get('kernel')->getRootDir() . '/../web/img/icon/icons_wide.png';
        $pdfGenerator->logo_top = file_get_contents($fnameLogo);

        $pdfGenerator->writeHTML($html);
        $pdfGenerator->Output($filename, 'I');
    }

    protected function adjustRefs($html, $refs, $language)
    {
        if (empty($refs)) {
            // nothing to do
            return $html;
        }

        $refLookup = $this->buildRefLookup($refs, $language);

        $crawler = new \Symfony\Component\DomCrawler\Crawler();
        $crawler->addHtmlContent('<body>' . $html . '</body>');

        $crawler->filterXPath("//a[@class='external']")
            ->each(function ($crawler) use ($refLookup) {
                foreach ($crawler as $node) {
                    $href = $node->getAttribute('href');

                    if (preg_match('/^jgo:(article|source)\-(\d+)$/', $href)) {
                        if (array_key_exists($href, $refLookup)) {
                            $info = $refLookup[$href];
                            $node->setAttribute('href', $refLookup[$href]['href']);
                            if (!empty($info['headline'])) {
                                $node->setAttribute('title', $refLookup[$href]['headline']);
                                $node->setAttribute('class', 'setTooltip');
                            }
                        }
                        else {
                            $node->removeAttribute('href');
                            $node->setAttribute('class', 'externalDisabled');
                        }
                    }
                }
        });

        return preg_replace('/<\/?body>/', '', $crawler->html());
    }

    protected function extractPartsFromHtml($html)
    {
        $crawler = new \Symfony\Component\DomCrawler\Crawler();
        $crawler->addHtmlContent($html);

        // extract toc
        $section_headers = $crawler->filterXPath('//h2')->each(function ($node, $i) {
            return [ 'id' => $node->attr('id'), 'text' => $node->text() ];
        });
        $authors = $crawler->filterXPath("//ul[@id='authors']/li")->each(function ($node, $i) {
            $author = [ 'text' => $node->text() ];
            $slug = $node->attr('data-author-slug');
            if (!empty($slug)) {
                $author['slug'] = $slug;
            }
            return $author;
        });

        // extract license
        $license = null;
        $node = $crawler
            ->filterXpath("//div[@id='license']");
        if (count($node) > 0) {
            $license = [ 'text' => trim($node->text()),
                         'url' => $node->attr('data-target') ];
        }

        // extract entities
        $entities = $crawler->filterXPath("//span[@class='entity-ref']")->each(function ($node, $i) {
            $entity = [];
            $type = $node->attr('data-type');
            if (!empty($type)) {
                $entity['type'] = $type;
            }
            $uri = $node->attr('data-uri');
            if (!empty($uri)) {
                $entity['uri'] = $uri;
            }
            return $entity;
        });

        // extract bibitem
        $bibitems = array_filter(array_unique($crawler->filterXPath("//span[@class='dta-bibl']")->each(function ($node, $i) {
            return trim($node->attr('data-corresp'));
        })));
        $bibitems_by_corresp = [];
        if (!empty($bibitems)) {
            $slugify = $this->get('cocur_slugify');
            foreach ($bibitems as $corresp) {
                $bibitems_map[$corresp] =  \AppBundle\Entity\Bibitem::slugifyCorresp($slugify, $corresp);
            }
            $query = $this->getDoctrine()
                ->getManager()
                ->createQuery('SELECT b.slug FROM AppBundle:Bibitem b WHERE b.slug IN (:slugs) AND b.status >= 0')
                ->setParameter('slugs', array_values($bibitems_map));

            foreach ($query->getResult() as $bibitem) {
                $corresps = array_keys($bibitems_map, $bibitem['slug']);
                foreach ($corresps as $corresp) {
                    $bibitems_by_corresp[$corresp] = $bibitem;
                }
            }
        }

        // extract glossary terms
        $glossaryTerms = array_unique($crawler->filterXPath("//span[@class='glossary']")->each(function ($node, $i) {
            return $node->attr('data-title');
        }));

        // extract article refs
        $refs = array_unique($crawler->filterXPath("//a[@class='external']")->each(function ($node, $i) {
            $href = $node->attr('href');
            if (preg_match('/^jgo:(article|source)\-(\d+)$/', $node->attr('href'))) {
                return $node->attr('href');
            }
        }));

        // try to get bios in the current locale
        $locale = $this->get('translator')->getLocale();
        $author_slugs = [];
        $authors_by_slug = [];
        foreach ($authors as $author) {
            if (array_key_exists('slug', $author)) {
                $author_slugs[] = $author['slug'];
                $authors_by_slug[$author['slug']] = $author;
            }
            else {
                $authors_by_slug[] = $author;
            }
        }
        if (!empty($author_slugs)) {
            $query = $this->getDoctrine()
                ->getManager()
                ->createQuery('SELECT p.slug, p.description, p.gender FROM AppBundle:Person p WHERE p.slug IN (:slugs)')
                ->setParameter('slugs', $author_slugs);

            foreach ($query->getResult() as $person) {
                $authors_by_slug[$person['slug']]['gender'] = $person['gender'];
                if (!is_null($person['description']) && array_key_exists($locale, $person['description'])) {
                    $authors_by_slug[$person['slug']]['description'] = $person['description'][$locale];
                }
            }
        }

        return [ $authors_by_slug, $section_headers, $license, $entities, $bibitems_by_corresp, $glossaryTerms, $refs ];
    }
}
