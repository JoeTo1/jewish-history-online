<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // alias for Gedmo extensions annotations

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity
 */
class ArticlePlace extends ArticleEntity
{
    /**
     * @ORM\ManyToOne(targetEntity="Place", inversedBy="articleReferences")
     * @ORM\JoinColumn(name="entity_id", referencedColumnName="id", nullable=FALSE)
     */
    protected $place;

    /**
     * @ORM\ManyToOne(targetEntity="Article", inversedBy="placeReferences")
     * @ORM\JoinColumn(name="article_id", referencedColumnName="id", nullable=FALSE)
     */
    protected $article;

    public function setEntity($entity)
    {
        $this->place = $entity;
    }

    public function getEntity()
    {
        return $this->place;
    }
}
