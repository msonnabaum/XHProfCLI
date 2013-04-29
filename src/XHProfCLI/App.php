<?php

namespace XHProfCLI;

use Symfony\Component\Console\Application as SymfonyApplication;
use XHProfCLI\Aggregator;

class App extends SymfonyApplication {
  public function __construct() {
    parent::__construct('XHProf CLI', '1.0');

    $this->addCommands(array(
      new Aggregator(),
      new Summary(),
    ));
  }
}
