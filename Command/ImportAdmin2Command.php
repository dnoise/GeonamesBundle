<?php
namespace Giosh94mhz\GeonamesBundle\Command;

use Giosh94mhz\GeonamesBundle\Model\Import\ImportDirector;
use Symfony\Component\Console\Input\InputInterface;

class ImportAdmin2Command extends AbstractImportCommand
{
    protected function configure()
    {
        $this->setName('geonames:import:admin2')
             ->setDescription('Import second administrative (for the enabled countries)')
             ->configureCommon();
    }

    protected function addSteps(ImportDirector $director, InputInterface $input)
    {
        $director
//          ->addStep($this->getContainer()->get('giosh94mhz_geonames.import.step.toponym'))
            ->addStep($this->getContainer()->get('giosh94mhz_geonames.import.step.admin2'));
    }
}
