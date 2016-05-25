<?php

namespace Harvest2Toggl\Application;

use AJT\Toggl\TogglClient;
use Harvest\HarvestApi;
use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Yaml;

class MainApplication extends Application {

  const APP_NAME = "harvest2toggl";
  const APP_VERSION = "master";

  // Toggl API client.
  private $togglApi = NULL;
  // HarvestApp API client.
  private $harvestApi = NULL;
  private $config = NULL;
  /**
   *
   */
  public function __construct($script_basedir) {
    parent::__construct(self::APP_NAME, self::APP_VERSION);
    $this->config = Yaml::parse(file_get_contents($script_basedir . '/config/credentials.yml'));

    // Setup toggl api client.
    $this->togglApi = TogglClient::factory(array('api_key' => $this->config['harvest2toggl']['toggl']['token']));

    // Setup harvest api client.
    $harvestAPI = new HarvestAPI();
    $harvestAPI->setUser($this->config['harvest2toggl']['harvestapp']['user']);
    $harvestAPI->setPassword($this->config['harvest2toggl']['harvestapp']['pass']);
    $harvestAPI->setAccount($this->config['harvest2toggl']['harvestapp']['account']);
    $this->harvestApi = $harvestAPI;
  }

/**
 *
 */
  public function getTogglApi() {
    return $this->togglApi;
  }

/**
 *
 */
  public function getHarvestApi() {
    return $this->harvestApi;
  }

/**
 *
 */
  public function getConfig() {
    return $this->config;
  }
}
