<?php

namespace Arkounay\Bundle\TwigCopyLocationBundle\DataCollector;

use Symfony\Bridge\Twig\DataCollector\TwigDataCollector;

class TwigCollector extends TwigDataCollector
{

    /**
     * Returns the name of the collector.
     *
     * @return string The collector name
     */
    public function getName()
    {
        return 'app.twig_collector';
    }

}