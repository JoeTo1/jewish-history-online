<?php

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 *
 */
class PersonController extends Controller
{
    use SharingBuilderTrait;

    /**
     * @Route("/person", name="person-index")
     */
    public function indexAction(Request $request)
    {
        $route = $request->get('_route');
        $authorsOnly = 'about-authors' == $route;

        $qb = $this->getDoctrine()
                ->getManager()
                ->createQueryBuilder();

        $qb->select([
                'P',
                "CONCAT(COALESCE(P.familyName,P.givenName), ' ', COALESCE(P.givenName, '')) HIDDEN nameSort"
            ])
            ->from('AppBundle:Person', 'P')
            ->where('P.status IN (0,1)')
            ->orderBy('nameSort')
            ;

        if ($authorsOnly) {
            // limit to authors: Person with published Article
            $qb->distinct()
                ->innerJoin('P.articles', 'A')
                ->andWhere('A.status IN (1)')
                ;
        }

        $persons = $qb->getQuery()->getResult();

        return $this->render('AppBundle:Person:index.html.twig', [
            'pageTitle' => /** @Ignore */
                $this->get('translator')->trans($authorsOnly ? 'Authors' : 'Persons'),
            'persons' => $persons,
        ]);
    }

    public function detailAction(Request $request, $id = null, $gnd = null)
    {
        $personRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Person');

        if (!empty($id)) {
            $person = $personRepo->findOneById($id);
            if (isset($person)) {
                $gnd = $person->getGnd();
            }
        }
        else if (!empty($gnd)) {
            $person = $personRepo->findOneByGnd($gnd);
        }

        if (!isset($person) || $person->getStatus() < 0) {
            return $this->redirectToRoute('person-index');
        }

        $routeName = 'person'; $routeParams = [];
        if (!empty($gnd)) {
            $routeName = 'person-by-gnd';
            $routeParams = [ 'gnd' => $gnd ];
        }

        if (in_array($request->get('_route'), [ 'person-jsonld', 'person-by-gnd-jsonld' ])) {
            return new JsonLdResponse($person->jsonLdSerialize($request->getLocale()));
        }

        return $this->render('AppBundle:Person:detail.html.twig', [
            'pageTitle' => $person->getFullname(true), // TODO: lifespan in brackets
            'person' => $person,
            'pageMeta' => [
                'jsonLd' => $person->jsonLdSerialize($request->getLocale()),
                'og' => $this->buildOg($person, $request, $routeName, $routeParams),
                'twitter' => $this->buildTwitter($person, $request, $routeName, $routeParams),
            ],
        ]);
    }

    public function gndBeaconAction()
    {
        $translator = $this->container->get('translator');
        $twig = $this->container->get('twig');

        $personRepo = $this->getDoctrine()
                ->getRepository('AppBundle:Person');

        $query = $personRepo
                ->createQueryBuilder('P')
                ->where('P.status >= 0')
                ->andWhere('P.gnd IS NOT NULL')
                ->orderBy('P.gnd')
                ->getQuery()
                ;

        $persons = $query->execute();

        $ret = '#FORMAT: BEACON' . "\n"
             . '#PREFIX: http://d-nb.info/gnd/'
             . "\n";
        $ret .= sprintf('#TARGET: %s/gnd/{ID}',
                        $this->generateUrl('person-index', [], true))
              . "\n";

        $globals = $twig->getGlobals();
        $ret .= '#NAME: '
              . /** @Ignore */ $translator->trans($globals['siteName'])
              . "\n";
        // $ret .= '#MESSAGE: ' . "\n";

        foreach ($persons as $person) {
            $ret .=  $person->getGnd() . "\n";
        }

        return new \Symfony\Component\HttpFoundation\Response($ret, \Symfony\Component\HttpFoundation\Response::HTTP_OK,
                                                              [ 'Content-Type' => 'text/plain; charset=UTF-8' ]);
    }
}
