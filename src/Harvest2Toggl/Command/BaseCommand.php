<?php

namespace Harvest2Toggl\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class BaseCommand extends Command {

  /**
   * Initializes the command just after the input has been validated.
   *
   * This is mainly useful when a lot of commands extends one main command
   * where some things need to be initialized based on the input arguments and options.
   *
   * @param InputInterface  $input  An InputInterface instance
   * @param OutputInterface $output An OutputInterface instance
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $io = new SymfonyStyle($input, $output);
    if (is_null($this->getTogglApi())) {
      $io->error("Cannot connect to Toggl.");
      return 1;
    }

    if (is_null($this->getHarvestApi())) {
      $io->error("Cannot connect to Harvest.");
      return 1;
    }
  }

/**
 *
 */
  protected function getTogglApi() {
    $app = $this->getApplication();
    return $app->getTogglApi();
  }

/**
 *
 */
  protected function getHarvestApi() {
    $app = $this->getApplication();
    return $app->getHarvestApi();
  }

/**
 *
 */
  protected function getConfig() {
    $app = $this->getApplication();
    return $app->getConfig();
  }
}
