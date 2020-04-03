<?php

namespace ExperienceBundle\Services\JobSchedulers;

use ExperienceBundle\Command\{
    DeleteRejectedExperiencesCommand,
    RejectProcessingExperiencesCommand
};
use JMS\JobQueueBundle\{
    Cron\JobScheduler,
    Entity\Job
};
use SubscriptionBundle\Command\AppleInAppCommands\DeletePendingSubscriptionsCommand;

/**
 * Class ExperiencesScheduler
 * @package ExperienceBundle\Services\JobSchedulers
 */
class ExperiencesScheduler implements JobScheduler
{
    /**
     * @return array
     */
    public function getCommands(): array
    {
        return [
            RejectProcessingExperiencesCommand::NAME,
            DeleteRejectedExperiencesCommand::NAME,
            DeletePendingSubscriptionsCommand::NAME
        ];
    }

    /**
     * @param string $command
     * @param \DateTime $lastRunAt
     * @return bool
     */
    public function shouldSchedule(string $command, \DateTime $lastRunAt): bool
    {
        switch ($command) {
            case RejectProcessingExperiencesCommand::NAME:
            case DeletePendingSubscriptionsCommand::NAME:
                return time() - $lastRunAt->getTimestamp() >= 3600; // Executed at most every hour.
                break;
            case DeleteRejectedExperiencesCommand::NAME:
                return time() - $lastRunAt->getTimestamp() >= 86400; // Executed at most every day.
                break;
        }

        return false;
    }

    /**
     * @param string $command
     * @param \DateTime $lastRunAt
     * @return Job
     */
    public function createJob(string $command, \DateTime $lastRunAt): Job
    {
        switch ($command) {
            case RejectProcessingExperiencesCommand::NAME:
            case DeleteRejectedExperiencesCommand::NAME:
            case DeletePendingSubscriptionsCommand::NAME:
                return new Job($command);
                break;
        }
    }
}