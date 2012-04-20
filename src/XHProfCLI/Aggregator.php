<?php

namespace XHProfCLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XHProfLib\Runs\FileRuns;
use XHProfLib\Aggregator as XHProfLibAggregator;
use XHProfLib\Parser\Parser;

class Aggregator extends Command {
  protected function configure() {
    $this->setName('agg')
         ->setDescription('Aggregate runs')
         ->addArgument('directory', InputArgument::REQUIRED, 'directory blah?');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $directory = $input->getArgument('directory');
    ini_set("xhprof.output_dir", $directory);

    $x = new FileRuns();
    $agg = new XHProfLibAggregator();
    foreach ($x->getRuns() as $run) {
      $agg->addRun($run['run_id'], $run['namespace']);
    }
    try {
      $sum = $agg->sum();
    }
    catch (ErrorException $e) {
      // TODO: Handle this properly.
      print_r($e);
    }

    $filename = array(uniqid(), $run['namespace'] . '-summary', 'xhprof');

    $ret = file_put_contents(implode('.', $filename), serialize($sum));

    if ($ret) {
      $output->writeln("Aggregated file written successfully.");
    }
  }
}

