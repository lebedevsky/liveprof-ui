<?php declare(strict_types=1);

/**
 * @maintainer Timur Shagiakhmetov <timur.shagiakhmetov@corp.badoo.com>
 */

namespace Badoo\LiveProfilerUI\Pages;

use Badoo\LiveProfilerUI\Aggregator;
use Badoo\LiveProfilerUI\DataProviders\Interfaces\SourceInterface;
use Badoo\LiveProfilerUI\DataProviders\Interfaces\JobInterface;
use Badoo\LiveProfilerUI\DataProviders\Interfaces\MethodInterface;
use Badoo\LiveProfilerUI\DataProviders\Interfaces\SnapshotInterface;

class AjaxPages
{
    /** @var SnapshotInterface */
    protected $Snapshot;
    /** @var MethodInterface */
    protected $Method;
    /** @var JobInterface */
    protected $Job;
    /** @var Aggregator */
    protected $Aggregator;
    /** @var SourceInterface */
    protected $Source;
    /** @var bool */
    protected $use_jobs;

    public function __construct(
        SnapshotInterface $Snapshot,
        MethodInterface $Method,
        JobInterface $Job,
        Aggregator $Aggregator,
        SourceInterface $Source,
        bool $use_jobs = false
    ) {
        $this->Snapshot = $Snapshot;
        $this->Method = $Method;
        $this->Job = $Job;
        $this->Aggregator = $Aggregator;
        $this->Source = $Source;
        $this->use_jobs = $use_jobs;
    }

    public function rebuildSnapshot(string $app, string $label, string $date) : array
    {
        $status = false;
        if ($this->use_jobs) {
            try {
                $this->Job->getJob(
                    $app,
                    $label,
                    $date,
                    [JobInterface::STATUS_NEW, JobInterface::STATUS_PROCESSING]
                );
                $message = "Job for snapshot ($app, $label, $date) is already exists";
            } catch (\InvalidArgumentException $Ex) {
                if ($this->Job->add($app, $label, $date, 'manual')) {
                    $message = "Added a job for aggregating a snapshot ($app, $label, $date)";
                    $status = true;
                } else {
                    $message = "Error in the snapshot ($app, $label, $date) aggregating";
                }
            }
        } else {
            try {
                $result = $this->Aggregator->setApp($app)
                    ->setLabel($label)
                    ->setDate($date)
                    ->setIsManual(true)
                    ->process();
                if (!empty($result)) {
                    $status = true;
                    $message = "Job for the snapshot ($app, $label, $date) is finished";
                } else {
                    $last_error = $this->Aggregator->getLastError();
                    $message = "Error in the snapshot ($app, $label, $date) aggregating: " . $last_error;
                }
            } catch (\Throwable $Ex) {
                $message = "Error in the snapshot ($app, $label, $date) aggregating: " . $Ex->getMessage();
            }
        }

        return [
            'status' => $status,
            'message' => $message,
        ];
    }

    public function checkSnapshot(string $app, string $label, string $date) : array
    {
        if (!$this->use_jobs) {
            try {
                $this->Snapshot->getOneByAppAndLabelAndDate($app, $label, $date);
                $is_processing = false;
                $message = "Job for the snapshot ($app, $label, $date) is finished";
            } catch (\InvalidArgumentException $Ex) {
                $is_processing = true;
                $message = "Job for the snapshot ($app, $label, $date) is processing now";
            }

            return [
                'is_new' => false,
                'is_processing' => $is_processing,
                'is_error' => false,
                'is_finished' => !$is_processing,
                'message' => $message
            ];
        }

        $is_new = $is_processing = $is_error = $is_finished = false;

        try {
            $ExistsJob = $this->Job->getJob(
                $app,
                $label,
                $date,
                [
                    JobInterface::STATUS_NEW,
                    JobInterface::STATUS_PROCESSING,
                    JobInterface::STATUS_FINISHED,
                    JobInterface::STATUS_ERROR
                ]
            );
            if ($ExistsJob->getStatus() === JobInterface::STATUS_NEW) {
                $is_new = true;
                $message = "Added a job for aggregating snapshot ($app, $label, $date)";
            } elseif ($ExistsJob->getStatus() === JobInterface::STATUS_PROCESSING) {
                $is_processing = true;
                $message = "Job for the snapshot ($app, $label, $date) is processing now";
            } elseif ($ExistsJob->getStatus() === JobInterface::STATUS_ERROR) {
                $is_error = true;
                $message = "Job for the snapshot ($app, $label, $date) is finished with error";
            } else {
                $is_finished = true;
                $message = "Job for the snapshot ($app, $label, $date) is finished";
            }
        } catch (\InvalidArgumentException $Ex) {
            $is_finished = true;
            $message = "Job for the snapshot ($app, $label, $date) is finished";
        }

        return [
            'is_new' => $is_new,
            'is_processing' => $is_processing,
            'is_error' => $is_error,
            'is_finished' => $is_finished,
            'message' => $message
        ];
    }

    public function searchMethods(string $term) : array
    {
        try {
            return $this->Method->findByName($term);
        } catch (\Throwable $Ex) {
            return [];
        }
    }

    public function getSourceAppList() : array
    {
        try {
            return $this->Source->getAppList();
        } catch (\Exception $Ex) {
            return [];
        }
    }

    public function getSourceLabelList() : array
    {
        try {
            return $this->Source->getLabelList();
        } catch (\Exception $Ex) {
            return [];
        }
    }
}
