<?php

namespace XHProfCLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XHProfCLI\Utils;
use XHProfLib\Runs\FileRuns;
use XHProfLib\Aggregator as XHProfLibAggregator;
use XHProfLib\Parser\Parser;

class Aggregator extends Command {
  protected function configure() {
    $this->setName('agg')
         ->setDescription('Aggregate runs')
         ->addArgument('directory', InputArgument::REQUIRED, 'A directory with xhprof files to aggregate.')
         ->addOption('discard-outliers', null, InputOption::VALUE_NONE, 'Whether or not to discard outliers from the aggregate.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $directory = $input->getArgument('directory');
    ini_set("xhprof.output_dir", $directory);

    $x = new FileRuns();
    $allRuns = $x->getRuns();
    $agg = new XHProfLibAggregator();

    $discardOutliers = $input->getOption('discard-outliers');
    if ($discardOutliers) {
      $totals = array();
      foreach ($allRuns as $key => $run) {
        $parser = new Parser($x->getRun($run['run_id'], $run['namespace']));
        foreach ($parser->getTotals() as $metric => $value) {
          if ($metric == 'wt') {
            $allRuns[$key]['wt'] = $value;
            $totals[$run['namespace']][$metric][] = $value;
          }
        }
      }
      $outliers = array();
      foreach ($totals as $namespace => $namespace_totals) {
        $outliers[$namespace]['low'] = Utils::quantile($namespace_totals['wt'], 0.25);
        $outliers[$namespace]['high'] = Utils::quantile($namespace_totals['wt'], 0.75);
        $output->writeln("Discarding outliers not in {$outliers[$namespace]['low']} - {$outliers[$namespace]['high']} for '$namespace'.");
      }
    }

    foreach ($allRuns as $run) {
      if ($discardOutliers) {
        $isOutlier = $run['wt'] > $outliers[$run['namespace']]['high'] || $run['wt'] < $outliers[$run['namespace']]['low'];
        if ($isOutlier) {
          $output->writeln("Discarding run '{$run['run_id']}-{$run['namespace']}', because wt ({$run['wt']}) is an outlier.");
          continue;
        }
      }
      $agg->addRun($run['run_id'], $run['namespace']);
    }

    $sum = $agg->sum(TRUE);

    $filename = array(uniqid(), $run['namespace'] . '-summary', 'xhprof');

    $ret = file_put_contents(implode('.', $filename), serialize($sum));

    if ($ret) {
      $output->writeln("Aggregated file written successfully.");
    }
  }
}

