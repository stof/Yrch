<?php

namespace Application\YrchBundle\Form;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\TextField;
use Symfony\Component\Form\TextareaField;
use Symfony\Component\Form\UrlField;

/**
 * Form for site creation
 *
 * @author Christophe Coevoet
 * @copyright (c) 2010, Tolkiendil, Association loi 1901
 * @license GPLv2 (http://www.opensource.org/licenses/gpl-2.0.php)
 */
class SiteForm extends Form
{
    public function  configure()
    {
        $this->add(new TextField('name'));
        $this->add(new UrlField('url'));
        $this->add(new TextareaField('description'));
    }
}
