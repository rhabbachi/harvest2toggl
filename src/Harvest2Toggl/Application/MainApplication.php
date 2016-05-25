<?php

namespace Harvest2Toggl\Application;

use AJT\Toggl\TogglClient;
use Harvest\HarvestApi;
use Symfony\Component\Console\Application;
use Symfony\Component\Yaml\Yaml;

class MainApplication extends Application {

  const APP_NAME = "harvest2toggl";
  const APP_VERSION = "master";
  const CONFIG_CREDENTIALS = "/config/credentials.yml";

  // Toggl API client.
  private $togglApi = NULL;
  // HarvestApp API client.
  private $harvestApi = NULL;
  private $config = NULL;
  /**
   *
   */
  public function __construct($basedir) {
    parent::__construct(self::APP_NAME, self::APP_VERSION);
    if (is_file($basedir . self::CONFIG_CREDENTIALS)) {
      $this->config = Yaml::parse(file_get_contents($basedir . self::CONFIG_CREDENTIALS));

      // Setup toggl api client.
      if ($this->config['harvest2toggl']['toggl']['token']) {
        $this->togglApi = TogglClient::factory(array('api_key' => $this->config['harvest2toggl']['toggl']['token']));
      }

      // Setup harvest api client.
      if (isset($this->config['harvest2toggl']['harvestapp']['user'])
        && isset($this->config['harvest2toggl']['harvestapp']['pass'])
        && isset($this->config['harvest2toggl']['harvestapp']['account'])) {
        $harvestAPI = new HarvestAPI();
        $harvestAPI->setUser($this->config['harvest2toggl']['harvestapp']['user']);
        $harvestAPI->setPassword($this->config['harvest2toggl']['harvestapp']['pass']);
        $harvestAPI->setAccount($this->config['harvest2toggl']['harvestapp']['account']);
        $this->harvestApi = $harvestAPI;
      }
    }
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
