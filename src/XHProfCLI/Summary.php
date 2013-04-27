<?php

namespace XHProfCLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use XHProfLib\Parser\Parser;
use XHProfLib\Runs\FileRuns;

class Summary extends Command {
  protected function configure() {
    $this->setName('summary')
         ->setDescription('Show summary for a run or group of runs.')
         ->addArgument('run', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'directory blah?');
  }

  protected function quartile($array, $quartile) {
    sort($array);
    $ninety_fifth = $array[round(($quartile/100) * count($array) - .5)];
    return $ninety_fifth;
  }

  protected function getRunsFromArgs($arg) {
    $runs = array();
    if (is_dir(current($arg))) {
      foreach ($arg as $dir) {
        $x = new FileRuns($dir);
        $directory_runs = $x->getRuns();

        foreach ($directory_runs as &$run) {
          // Move this to the FileRuns class.
          $run['directory'] = $dir;
        }

        $runs = array_merge($runs, $directory_runs);
      }
    }
    elseif (is_file(current($arg))) {
      foreach ($arg as $file) {
        $x = new FileRuns(realpath(dirname($arg)));

        preg_match("/(?:(?<run>\w+)\.)(?:(?<namespace>.+)\.)(?<ext>\w+)/", basename($arg), $matches);
        $runs = array_merge($runs, array(array(
          'run_id' => $matches['run'],
          'namespace' => $matches['namespace'],
          'basename' => htmlentities(basename($arg)),
          'date' => date("Y-m-d H:i:s", filemtime($arg)),
        )));
      }
    }

    return $runs;
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $arg = $input->getArgument('run');

    $runs = $this->getRunsFromArgs($arg);

    $totals = array();
    foreach ($runs as $run) {
      $x = new FileRuns($run['directory']);
      $parser = new Parser($x->getRun($run['run_id'], $run['namespace']));

      foreach ($parser->getTotals() as $metric => $value) {
        $totals[$run['namespace']][$metric][] = $value;
      }
    }

    $summary = (object)$totals;

    $totals = array();
    foreach ($summary as $namespace => $sum) {
      foreach ($sum as $metric => $value) {
        if (!isset($totals[$namespace][$metric])) $totals[$namespace][$metric] = array();
        $totals[$namespace][$metric] = array_merge($totals[$namespace][$metric], $value);
      }
    }

    $output = array();
    foreach ($totals as $namespace => $namespace_totals) {
      foreach ($namespace_totals as $metric => $values) {
        if (!isset($output[$metric])) $output[$metric] = array();
        $output[$metric][] = array(
          'namespace' => $namespace,
          'min' => number_format(min($values)),
          'max' => number_format(max($values)),
          'mean' => number_format(array_sum($values) / count($values)),
          'median' => number_format($this->quantile($values, 0.5)),
          '95th' => number_format($this->quantile($values, 0.95)),
        );
      }
    }

    print $this->printTable($output);
  }

  function printTable($data) {
    require_once 'Console/Table.php';

    $tbl = new \Console_Table();
    $headers = array_keys(current(current($data)));
    $tbl->setHeaders($headers);
    for ($i = 1; $i < count($headers); $i++) {
      $tbl->setAlign($i, CONSOLE_TABLE_ALIGN_RIGHT);
    }

    $metric_labels = $this->metricNames();
    foreach ($data as $metric => $run) {
      $tbl->addRow(array($metric_labels[$metric]));
      $tbl->addRow(array());
      foreach ($run as $values) {
        $tbl->addRow($values);
      }
      $tbl->addRow(array());
    }
    return $tbl->getTable();
  }

  function quantile($values, $p) {
    sort($values);
    $H = (count($values) - 1) * $p + 1;
    $h = floor($H);
    $v = $values[$h - 1];
    $e = $H - $h;
    return $e ? $v + $e * ($values[$h] - $v) : $v;
  }

  function metricNames() {
    return array(
      "ct" => "Calls",
      "wt" => "Wall time",
      "cpu" => "CPU time",
      "mu" => "Memory usage",
      "pmu" => "Peak memory usage"
    );
  }
}

