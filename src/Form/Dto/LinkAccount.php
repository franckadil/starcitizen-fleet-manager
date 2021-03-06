<?php

namespace App\Form\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class LinkAccount
{
    /**
     * @var string
     *
     * @Assert\NotBlank(message="You must enter your SC handle.")
     */
    public $handleSC;
}
