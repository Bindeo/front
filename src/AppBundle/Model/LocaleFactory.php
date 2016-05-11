<?php

namespace AppBundle\Model;

use Symfony\Component\HttpFoundation\Session\Session;

class LocaleFactory
{
    private $number;

    public function __construct(Session $session)
    {
        $this->number = new \NumberFormatter($session->get('_locale'), \NumberFormatter::DECIMAL);
    }

    /**
     * Format number into locale
     *
     * @param string $number
     *
     * @return string
     */
    public function format($number)
    {
        return $this->number->format($number);
    }

    /**
     * Format currency
     *
     * @param string $amount
     *
     * @return string
     */
    public function formatCurrency($amount)
    {
        return $this->number->formatCurrency($amount);
    }
}