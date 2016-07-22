<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo; // alias for Gedmo extensions annotations

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entities that have a somewhat fixed, physical extension.
 *
 * @see http://schema.org/Place Documentation on Schema.org
 *
 * @ORM\Entity
 * @ORM\Table(name="place")
 */
class Place implements \JsonSerializable
{
    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @var integer
     *
     * @ORM\Column(type="integer", nullable=false)
     */
    protected $status = 0;
    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=false)
     */
    protected $type = 'inhabited place';
    /**
     * @var string The geo coordinates of the place.
     *
     * @Assert\Type(type="string")
     * @ORM\Column(nullable=true)
     */
    protected $geo;
    /**
     * @var string The name of the item.
     *
     * @Assert\Type(type="string")
     * @ORM\Column(nullable=false)
     */
    protected $name;
    /**
     * @var array An alias for the item.
     *
     * @ORM\Column(type="json_array", nullable=true)
     */
    protected $alternateName;

    /**
    * @ORM\Column(name="country_code", type="string", nullable=true)
    */
    protected $countryCode;

    /**
     * @var array Additional info for the item.
     *
     * @ORM\Column(type="json_array", nullable=true)
     */
    protected $additional;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    protected $tgn;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    protected $gnd;

    /**
     * @var string
     * @ORM\Column(type="string", nullable=true)
     */
    protected $geonames;

    /**
     * @ORM\ManyToOne(targetEntity="Place", inversedBy="children")
     * @ORM\JoinColumn(referencedColumnName="id", onDelete="CASCADE")
     */
    private $parent;

    /**
     * @ORM\OneToMany(targetEntity="Place", mappedBy="parent")
     */
    private $children;

    /**
     * @ORM\ManyToMany(targetEntity="Article", mappedBy="contentLocation")
     */
    protected $articles;

    use ArticleReferencesTrait;

   /**
     * @ORM\OneToMany(targetEntity="ArticlePlace", mappedBy="place", cascade={"persist", "remove"}, orphanRemoval=TRUE)
     */
    protected $articleReferences;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="create")
     * @ORM\Column(name="created_at", type="datetime")
     */
    protected $createdAt;

    /**
     * @var \DateTime
     *
     * @Gedmo\Timestampable(on="update")
     * @ORM\Column(name="changed_at", type="datetime")
     */
    protected $changedAt;

    /**
     * Sets id.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Gets id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets status.
     *
     * @param int $status
     *
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * Gets status.
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets type.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets geo.
     *
     * @param string $geo
     *
     * @return $this
     */
    public function setGeo($geo)
    {
        $this->geo = $geo;

        return $this;
    }

    /**
     * Gets geo.
     *
     * @return string
     */
    public function getGeo()
    {
        return $this->geo;
    }

    /**
     * Sets countryCode.
     *
     * @param string $countryCode
     *
     * @return $this
     */
    public function setCountryCode($countryCode)
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    /**
     * Gets countryCode.
     *
     * @return string
     */
    public function getCountryCode()
    {
        return $this->countryCode;
    }

    /**
     * Sets name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Gets name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets alternateName.
     *
     * @param string $alternateName
     *
     * @return $this
     */
    public function setAlternateName($alternateName)
    {
        $this->alternateName = $alternateName;

        return $this;
    }

    /**
     * Gets alternateName.
     *
     * @return string
     */
    public function getAlternateName()
    {
        return $this->alternateName;
    }

    /**
     * Sets additional.
     *
     * @param array $additional
     *
     * @return $this
     */
    public function setAdditional($additional)
    {
        $this->additional = $additional;

        return $this;
    }

    /**
     * Gets additional.
     *
     * @return array
     */
    public function getAdditional()
    {
        return $this->additional;
    }

    /**
     * Sets Getty Thesaurus of Geographic Names Identifier.
     *
     * @param string $tgn
     *
     * @return $this
     */
    public function setTgn($tgn)
    {
        $this->tgn = $tgn;

        return $this;
    }

    /**
     * Gets Getty Thesaurus of Geographic Names.
     *
     * @return string
     */
    public function getTgn()
    {
        return $this->tgn;
    }

    /**
     * Sets gnd.
     *
     * @param string $gnd
     *
     * @return $this
     */
    public function setGnd($gnd)
    {
        $this->gnd = $gnd;

        return $this;
    }

    /**
     * Gets gnd.
     *
     * @return string
     */
    public function getGnd()
    {
        return $this->gnd;
    }

    /**
     * Sets geonames.
     *
     * @param string $geonames
     *
     * @return $this
     */
    public function setGeonames($geonames)
    {
        $this->geonames = $geonames;

        return $this;
    }

    /**
     * Gets geonames.
     *
     * @return string
     */
    public function getGeonames()
    {
        return $this->geonames;
    }

    public function setParent(Place $parent = null)
    {
        $this->parent = $parent;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Gets localized name.
     *
     * @return string
     */
    public function getNameLocalized($locale = 'en')
    {
        if (is_array($this->alternateName)
            && array_key_exists($locale, $this->alternateName)) {
            return $this->alternateName[$locale];
        }
        return $this->getName();
    }

    public function getTypeLabel()
    {
        if ('root' == $this->type) {
            return;
        }
        if ('inhabited place' == $this->type) {
            return 'place';
        }
        return $this->type;
    }

    public function getChildrenLabel()
    {
        if (is_null($this->children) || count($this->children) == 0) {
            return '';
        }
        $label = $this->children[0]->getTypeLabel();
        if (count($this->children) > 1) {
            $label = \Doctrine\Common\Inflector\Inflector::pluralize($label);
        }
        return ucfirst($label);
    }

    public function getPath()
    {
        $path = [];
        $parent = $this->getParent();
        while ($parent != null) {
            $path[] = $parent;
            $parent = $parent->getParent();
        }
        return array_reverse($path);
    }

    public function getArticles()
    {
        return $this->articles;
    }

    public function jsonSerialize()
    {
        return [
                 'id' => $this->id,
                 'name' => $this->name,
                 'geo' => $this->geo,
                 'tgn' => $this->tgn,
                 'gnd' => $this->gnd,
                 ];
    }
}
