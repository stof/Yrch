<?php

namespace Application\YrchBundle\Entity;

use Application\YrchBundle\Entity\SiteTranslation;
use Application\YrchBundle\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * Application\YrchBundle\Entity\Site
 *
 * @orm:Table(name="site")
 * @orm:Entity(repositoryClass="Application\YrchBundle\Entity\SiteRepository")
 */
class Site
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
     * @var string $url
     *
     * @orm:Column(name="url", type="string", length=255)
     */
    private $url;

    /**
     * @var string $createdAt
     *
     * @orm:Column(name="created_at", type="datetime")
     */
    private $createdAt;

    /**
     * @var string $updatedAt
     *
     * @orm:Column(name="updated_at", type="datetime")
     */
    private $updatedAt;

    /**
     * @var string $selection
     *
     * @orm:Column(name="selection", type="boolean")
     */
    private $selection;

    /**
     * @var string $leech
     *
     * @orm:Column(name="leech", type="boolean")
     */
    private $leech;

    /**
     * @var string $status
     *
     * @orm:Column(name="status", type="string", length=100)
     */
    private $status;

    /**
     * @var string $notes
     *
     * @orm:Column(name="notes", type="text")
     */
    private $notes;

    /**
     * @orm:ManyToOne(targetEntity="Application\YrchBundle\Entity\User", inversedBy="sites")
     */
    private $owner;
    
    /**
     * @orm:OneToMany(targetEntity="Application\YrchBundle\Entity\SiteTranslation", mappedBy="site")
     */
    private $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

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
     * Set url
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string $url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set createdAt
     *
     * @param datetime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }

    /**
     * Get createdAt
     *
     * @return datetime $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param datetime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * Get updatedAt
     *
     * @return datetime $updatedAt
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set selection
     *
     * @param boolean $selection
     */
    public function setSelection($selection)
    {
        $this->selection = $selection;
    }

    /**
     * Get selection
     *
     * @return boolean $selection
     */
    public function getSelection()
    {
        return $this->selection;
    }

    /**
     * Set leech
     *
     * @param boolean $leech
     */
    public function setLeech($leech)
    {
        $this->leech = $leech;
    }

    /**
     * Get leech
     *
     * @return boolean $leech
     */
    public function getLeech()
    {
        return $this->leech;
    }

    /**
     * Set status
     *
     * @param string $status
     */
    public function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * Get status
     *
     * @return string $status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set notes
     *
     * @param text $notes
     */
    public function setNotes($notes)
    {
        $this->notes = $notes;
    }

    /**
     * Get notes
     *
     * @return text $notes
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Set owner
     *
     * @param User $user
     */
    public function setOwner(User $user)
    {
        $this->owner = $user;
    }

    /**
     * Get owner
     *
     * @return User $owner
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Add translation
     *
     * @param SiteTranslation $translation
     */
    public function addTranslation(SiteTranslation $translation)
    {
        $this->translations[] = $translation;
    }

    /**
     * Get translations
     *
     * @return Collection $translations
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * Get the translation for the specified locale
     *
     * @param string $locale
     * @return SiteTranslation
     */
    public function getTranslation($locale)
    {
        foreach ($this->getTranslations() as $translation) {
            if ($translation->getLocale() == $locale){
                return $translation;
            }
        }
    }
}