<?php

namespace AppBundle\Model;

use Symfony\Component\HttpFoundation\Session\Session;

class LocaleFactory
{
    private $number;
    private $date;

    public function __construct(Session $session)
    {
        $this->number = new \NumberFormatter($session->get('_locale'), \NumberFormatter::DECIMAL);
    }

    public function format($number)
    {
        return $this->number->format($number);
    }

    public function formatCurrency($amount)
    {
        return $this->number->formatCurrency();
    }
}