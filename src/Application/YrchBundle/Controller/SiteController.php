<?php

namespace Application\YrchBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Application\YrchBundle\Entity\Site;
use Application\YrchBundle\Form\SiteForm;

/**
 * SiteController
 *
 * @author Christophe Coevoet
 * @copyright (c) 2010, Tolkiendil, Association loi 1901
 * @license GPLv2 (http://www.opensource.org/licenses/gpl-2.0.php)
 */
class SiteController extends Controller
{
    public function showAction($id)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $siteRepo = $em->getRepository('Application\YrchBundle\Entity\Site');
        $site = $siteRepo->find($id);
        // TODO: uncomment it when the security will be enabled
        $outlink = /*($this->get('security.context')->isAuthenticated())? $this->get('security.context')->getUser()->getOutlink():*/"_blank";
        return $this->render('YrchBundle:Site:show.twig', compact('site', 'outlink'));
    }

    /**
     * Show the new form
     */
    public function newAction()
    {
        $site = new Site();
        $form = new SiteForm('site', $site, $this->get('validator'));

        return $this->render('YrchBundle:Site:new.twig', array('form' => $form));
    }

    /**
     * Create a site
     */
    public function createAction()
    {
        $site = new Site();
        $form = new SiteForm('site', $site, $this->get('validator'));
        $form->bind($this->get('request')->request->get($form->getName()));

        if ($form->isValid()) {
            $site = $form->getData();
            $userManager = $this->get('fos_user.user_manager');
            $specialUser = $userManager->findUserByUsername($this->container->getParameter('yrch.special_user.username'));
            $site->setSuperOwner($specialUser);
            $em = $this->get('doctrine.orm.entity_manager');
            $em->persist($site);
            $em->flush();
            $em->refresh($site);
            $url = $this->generateUrl('site_show_'.$this->get('session')->getLocale(), array('id' => $site->getId()));

            $this->get('session')->setFlash('site created', 'success');
            return $this->redirect($url);
        }

        return $this->render('YrchBundle:Site:new.twig', array('form' => $form));
    }
}
