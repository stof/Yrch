<?php

namespace Application\YrchBundle\Entity;

use Application\YrchBundle\Entity\Site;

/**
 * Application\YrchBundle\Entity\SiteTranslation
 *
 * @orm:Table(name="site_translation")
 * @orm:Entity(repositoryClass="Application\YrchBundle\Entity\SiteTranslationRepository")
 */
class SiteTranslation
{
    /**
     * @var integer $id
     *
     * @orm:Column(name="id", type="integer")
     * @orm:Id
     * @orm:GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string $title
     *
     * @orm:Column(name="title", type="string", length=255)
     */
    private $title;

    /**
     * @var string $locale
     *
     * @orm:Column(name="locale", type="string", length=10)
     */
    private $locale;

    /**
     * @var string $description
     *
     * @orm:Column(name="description", type="text")
     */
    private $description;

    /**
     * @orm:ManyToOne(targetEntity="Site", inversedBy="translations")
     */
    private $site;

    /**
     * Get id
     *
     * @return integer $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set title
     *
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * Get title
     *
     * @return string $title
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set locale
     *
     * @param string $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Get locale
     *
     * @return string $locale
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return string $description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set site
     *
     * @param Site $site
     */
    public function setSite(Site $site)
    {
        $this->site = $site;
    }

    /**
     * Get site
     *
     * @return Site $site
     */
    public function getSite()
    {
        return $this->site;
    }

}