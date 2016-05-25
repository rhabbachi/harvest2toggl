<?php

namespace Harvest2Toggl\Command;

use Guzzle\Http\Exception\ClientErrorResponseException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// date_default_timezone_set('America/Los_Angeles'); // Set your timezone if it is not set in your php.ini

class UpdateCommand extends BaseCommand {

  protected function configure() {
    $this
      ->setName('update')
      ->setDescription('Update Toggl with missing entries from HarvestApp.');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $io = new SymfonyStyle($input, $output);
    // Get the current toggl user. This is useful for timezones and such.
    $togglCurrentUser = $this->getTogglApi()->getCurrentUser();

    $preprocessChecks = TRUE;


    $configTogglClient = 'NuCivic';
    $togglClientProjects = array();
    $togglClients = $this->getTogglApi()->getClients();
    foreach ($togglClients as $togglClient) {
      if ($togglClient['name'] == $configTogglClient) {
        $clientProjects = $this->getTogglApi()->getClientProjects(array('id' => $togglClient['id']));
        if (!empty($clientProjects)) {
          foreach ($clientProjects as $togglClientProject) {
            $togglClientProjects[$togglClientProject['id']] = $togglClientProject['name'];
          }
        }
        break;
      }
    }

    if (empty($togglClientProjects)) {
      $io->error('Failed to retrive client projects ids from Toggl.');
      return 1;
    }

    $optionDateStartString = 'first day of this month';
    $dateStartTimestamp = strtotime($optionDateStartString);

    if (!$dateStartTimestamp) {
      $io->error("Failed to compute the start date.");
      return 1;
    }

    $dateYear = date('o', $dateStartTimestamp);

    $dateStartDayOfYear = date('z', $dateStartTimestamp);
    $dateEndDayOfYear = date('z');

    $dateStartISO = date('c', $dateStartTimestamp);
    $dateEndISO = date('c');

    $togglTimeEntries = $this->getTogglApi()->getTimeEntries(array('start_date' => $dateStartISO, 'end_date' => $dateEndISO));
    // Filter toggl entries to get only our specific client.
    foreach ($togglTimeEntries as $index => $togglTimeEntry) {
      if (isset($togglTimeEntry['pid']) && !in_array($togglTimeEntry['pid'], array_keys($togglClientProjects))) {
        unset($togglTimeEntries[$index]);
      }
    }

    $harvestDailyActivitiesSkip = array();
    $harvestDailyActivitiesImport = array();
    $harvestProjects = array();

    foreach (range($dateStartDayOfYear, $dateEndDayOfYear) as $dayOfYear) {
      $harvestAPIDailyActivity = $this->getHarvestApi()->getDailyActivity($dayOfYear, $dateYear);
      if (!$harvestAPIDailyActivity->isSuccess()) {
        $io->error('Failed to retrive Daily Activity from Harvest.');
        return 1;
      }

      $harvestDailyActivities[$dayOfYear] = $harvestAPIDailyActivity->data;

      foreach ($harvestDailyActivities[$dayOfYear]->get('dayEntries') as $harvestDayEntry) {
        if (!isset($harvestProjects[$harvestDayEntry->project_id])) {
          $harvestProjects[$harvestDayEntry->project_id] = $harvestDayEntry->project;
        }

        // Check if this entry is already imported into toggl or not.
        foreach ($togglTimeEntries as $togglTimeEntry) {
          if ($this->compareTimeEntry($harvestDayEntry, $togglTimeEntry)) {
            $harvestDailyActivitiesSkip[$harvestDayEntry->id] = $harvestDayEntry;
            break;
          }
        }

        // Check if this entry made it to the import array.
        if (!isset($harvestDailyActivitiesSkip[$harvestDayEntry->id])) {
          $harvestDailyActivitiesImport[$harvestDayEntry->id] = $harvestDayEntry;
        }
      }
    }

    // Check if we have all the Harvestapp projects in toggl or not.
    $projectsNotFound = array_diff($harvestProjects, $togglClientProjects);

    if (!empty($projectsNotFound)) {
      $preprocessChecks = FALSE;
      $io->section("The following projects where used in Harvest but don't exist in toggl");
      $io->table(['Missing projects'], array_map(function ($item) {return array($item);}, $projectsNotFound));
    }

    if (!$preprocessChecks) {
      $io->error("Some preprocessing checks failed. Please review and fix those error.");
      return 1;
    }

    if (!empty($harvestDailyActivitiesSkip)) {
      $headers = ['', 'Notes', 'Project', 'Date', 'Duration'];
      $rows = array();
      $count = 0;
      foreach ($harvestDailyActivitiesSkip as $harvestDailyActivity) {
        $rows[] = array(
          ++$count,
          $harvestDailyActivity->notes,
          $harvestDailyActivity->project,
          $harvestDailyActivity->spent_at,
          $harvestDailyActivity->hours,
        );
      }
      $io->section("The following entries are already in toggl and will be skipped:");
      $io->table($headers, $rows);
    }

    if (!empty($harvestDailyActivitiesImport)) {
      $headers = ['', 'Notes', 'Project', 'Date', 'Duration'];
      $rows = array();
      $count = 0;
      foreach ($harvestDailyActivitiesImport as $harvestDailyActivity) {
        $rows[] = array(
          ++$count,
          $harvestDailyActivity->notes,
          $harvestDailyActivity->project,
          $harvestDailyActivity->spent_at,
          $harvestDailyActivity->hours,
        );
      }

      $io->section("The following entries are missing from toggl and will be imported:");
      $io->table($headers, $rows);

      if ($io->confirm("proceed?", FALSE)) {
        foreach ($harvestDailyActivitiesImport as $harvestDailyActivity) {
          try {
            $toggl_start = new \Datetime($harvestDailyActivity->spent_at, new \DateTimeZone($togglCurrentUser['timezone']));
            $this->getTogglApi()->createTimeEntry(array(
              'time_entry' => array(
                'billable' => TRUE,
                'description' => $harvestDailyActivity->notes,
                'created_with' => 'harvest2toggl',
                'pid' => array_search($harvestDailyActivity->project, $togglClientProjects),
                // Convert Harvest hours to seconds.
                'duration' => $this->durationHarvest2Toggl($harvestDailyActivity->hours),
                'duronly' => TRUE,
                'start' => $toggl_start->format('c'),
              )));
          } catch (ClientErrorResponseException $exception) {
            $io->error("Failed to create Creating time entry in Toggl");
            $io->error("Possible cause of the faileur: " . $exception->getMessage());
            if (!$io->confirm("Continue with the remaining entries?")) {
              $io->text("Update operation failed.");
              return 1;
            }
          }
        }
      }
    }

    $io->text("Update operation finished.");
  }

  /**
   * @return TRUE if the time entries are equivalent. Else FALSE.
   */
  private function compareTimeEntry($harvestTimeEntry, $togglTimeEntry) {
    $togglDate = date('Y-m-d', strtotime($togglTimeEntry['start']));
    if ($harvestTimeEntry->notes == $togglTimeEntry['description']
      && $this->durationHarvest2Toggl($harvestTimeEntry->hours) == $togglTimeEntry['duration']
      && $harvestTimeEntry->spent_at == $togglDate) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   *
   */
  private function durationHarvest2Toggl($hours) {
    return intval($hours * 60 * 60);
  }

  /**
   *
   */
  private function durationToggl2Harvest2($seconds) {
    return doubleval($seconds / (60 * 60));
  }
}
