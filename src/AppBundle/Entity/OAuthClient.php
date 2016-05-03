<?php

namespace AppBundle\Entity;


use Bindeo\DataModel\OAuthClientAbstract;
use Bindeo\DataModel\UserInterface;

class OAuthClient extends OAuthClientAbstract implements UserInterface
{
}