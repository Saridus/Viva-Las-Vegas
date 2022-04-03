<?php

class Hotels{
    private string $name;
    private string $stars;
    private string $image;

    /**
     * Actor constructor.
     * @param string $name
     * @param string $stars
     * @param string $image
     */
    public function __construct(string $name, string $stars, string $image)
    {
        $this->name = $name;
        $this->stars = $stars;
        $this->image = $image;
    }

    /**
     * @return string
     */
    public function getImage(): string
    {
        return $this->image;
    }

    /**
     * @param string $image
     */
    public function setImage(string $image): void
    {
        $this->image = $image;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getStars(): string
    {
        return $this->stars;
    }

    /**
     * @param string $stars
     */
    public function setStars(string $stars): void
    {
        $this->stars = $stars;
    }

    public function __toString(): string
    {
        return $this->getName().' '.$this->getStars();
    }

}
