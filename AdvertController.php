<?php
// src/OC/PlatformBundle/Controller/AdvertController.php

namespace OC\PlatformBundle\Controller;

use OC\PlatformBundle\Entity\Advert;
use OC\PlatformBundle\Form\AdvertEditType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use OC\PlatformBundle\Form\AdvertType;
/**
 * @TODO    : Class Description.
 *
 * Class AdvertController
 *
 * @package OC\PlatformBundle\Controller
 */
class AdvertController extends Controller
{
    /**
     * @param int $page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($page)
    {
        if ($page < 1) {
            throw new NotFoundHttpException('Page "' . $page . '" inexistante.');
        }

        $nbPerPage = 3;

        $listAdverts = $this->getDoctrine()->getManager()
            ->getRepository('OCPlatformBundle:Advert')
            ->getAdverts($page, $nbPerPage);

        $nbPages = ceil(count($listAdverts) / $nbPerPage);

        if ($page > $nbPages) {
            throw $this->createNotFoundException("La page " . $page . " n'existe pas.");
        }

        $arrayRender = [
            'listAdverts' => $listAdverts,
            'nbPages'     => $nbPages,
            'page'        => $page,
        ];

        return $this->render('OCPlatformBundle:Advert:index.html.twig', $arrayRender);
    }//end indexAction()

    /**
     * @param int $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        // Pour récupérer une seule annonce, on utilise la méthode find($id).
        $advert = $em->getRepository('OCPlatformBundle:Advert')->find($id);

        // $advert est donc une instance de OC\PlatformBundle\Entity\Advert
        // ou null si l'id $id n'existe pas, d'où ce if :
        if (null === $advert) {
            throw new NotFoundHttpException("L'annonce d'id " . $id . " n'existe pas.");
        }

        // Récupération de la liste des candidatures de l'annonce.
        $listApplications = $em
            ->getRepository('OCPlatformBundle:Application')
            ->findBy(['advert' => $advert]);

        // Récupération des AdvertSkill de l'annonce.
        $listAdvertSkills = $em
            ->getRepository('OCPlatformBundle:AdvertSkill')
            ->findBy(['advert' => $advert]);

        $arrayRender = [
            'advert'           => $advert,
            'listApplications' => $listApplications,
            'listAdvertSkills' => $listAdvertSkills,
        ];

        return $this->render('OCPlatformBundle:Advert:view.html.twig', $arrayRender);
    }//end viewAction()

    /**
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function addAction(Request $request)
    {
        $advert = new Advert();
        $form   = $this->get('form.factory')->create(AdvertType::class, $advert);

        if ($request->isMethod('POST') === true && $form->handleRequest($request)->isValid() === true) {
            $em = $this->getDoctrine()->getManager();
            $advert->getImage()->upload();
            $em->persist($advert);
            $em->flush();

            $request->getSession()->getFlashBag()->add('notice', 'Annonce bien enregistrée.');

            return $this->redirectToRoute('oc_platform_view', array('id' => $advert->getId()));
        }

        return $this->render('OCPlatformBundle:Advert:add.html.twig', ['form' => $form->createView()]);
    }//end addAction()

    /**
     * @param int     $id
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function editAction($id, Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $advert = $em->getRepository('OCPlatformBundle:Advert')->find($id);

        if (null === $advert) {
            throw new NotFoundHttpException("L'annonce d'id " . $id . " n'existe pas.");
        }

        $form = $this -> get('form.factory') -> create(AdvertEditType::class, $advert);
        if ($request -> isMethod('POST') === true && $form->handleRequest($request)->isValid() === true) {
            $em = $this -> getDoctrine() -> getManager();
            $em -> persist($advert);
            $em -> flush();

            $request->getSession()->getFlashBag()->add('notice', 'Annonce bien modifiée.');

            return $this->redirectToRoute('oc_platform_view', ['id' => $advert->getId()]);
        }

        return $this->render('OCPlatformBundle:Advert:edit.html.twig',
            [
                'form' => $form -> createView(),
                'advert' => $advert,
            ]
        );
    }//end editAction()

    /**
     * @param int $id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $advert = $em->getRepository('OCPlatformBundle:Advert')->find($id);

        if (null === $advert) {
            throw new NotFoundHttpException("L'annonce d'id " . $id . " n'existe pas.");
        }

        // On boucle sur les catégories de l'annonce pour les supprimer.
        foreach ($advert->getCategories() as $category) {
            $advert->removeCategory($category);
        }

        $em->flush();

        return $this->render('OCPlatformBundle:Advert:delete.html.twig');
    }//end deleteAction()

    /**
     * @param int $limit
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function menuAction($limit)
    {
        $em = $this->getDoctrine()->getManager();

        $listAdverts = $em->getRepository('OCPlatformBundle:Advert')->findBy(
            [],
            ['date' => 'desc'],
            $limit,
            0
        );

        return $this->render('OCPlatformBundle:Advert:menu.html.twig', ['listAdverts' => $listAdverts]);
    }//end menuAction()

    public function purgeAction($days, Request $request)
    {
        $em = $this -> getDoctrine() -> getManager();
        $purges =  new \OC\PlatformBundle\Purger\advertPurger($em);

        $purges -> purge($days);


        $request->getSession()->getFlashBag()->add('info', 'Purge des annonces réalisée');
        return $this->redirectToRoute('oc_platform_home');

    }
}
