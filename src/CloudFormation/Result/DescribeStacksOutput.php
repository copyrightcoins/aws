<?php

namespace AsyncAws\CloudFormation\Result;

use AsyncAws\CloudFormation\CloudFormationClient;
use AsyncAws\CloudFormation\Input\DescribeStacksInput;
use AsyncAws\Core\Result;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DescribeStacksOutput extends Result implements \IteratorAggregate
{
    /**
     * A list of stack structures.
     */
    private $Stacks = [];

    /**
     * If the output exceeds 1 MB in size, a string that identifies the next page of stacks. If no additional page exists,
     * this value is null.
     */
    private $NextToken;

    /**
     * @var self[]
     */
    private $prefetch = [];

    public function __destruct()
    {
        while (!empty($this->prefetch)) {
            array_shift($this->prefetch)->cancel();
        }

        parent::__destruct();
    }

    /**
     * Iterates over Stacks.
     *
     * @return \Traversable<Stack>
     */
    public function getIterator(): \Traversable
    {
        if (!$this->awsClient instanceof CloudFormationClient) {
            throw new \InvalidArgumentException('missing client injected in paginated result');
        }
        if (!$this->input instanceof DescribeStacksInput) {
            throw new \InvalidArgumentException('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (true) {
            if ($page->getNextToken()) {
                $input->setNextToken($page->getNextToken());

                $nextPage = $this->awsClient->DescribeStacks($input);
                $this->prefetch[spl_object_hash($nextPage)] = $nextPage;
            } else {
                $nextPage = null;
            }

            yield from $page->getStacks(true);

            if (null === $nextPage) {
                break;
            }

            unset($this->prefetch[spl_object_hash($nextPage)]);
            $page = $nextPage;
        }
    }

    public function getNextToken(): ?string
    {
        $this->initialize();

        return $this->NextToken;
    }

    /**
     * @param bool $currentPageOnly When true, iterates over items of the current page. Otherwise also fetch items in the next pages.
     *
     * @return iterable<Stack>
     */
    public function getStacks(bool $currentPageOnly = false): iterable
    {
        if ($currentPageOnly) {
            $this->initialize();
            yield from $this->Stacks;

            return;
        }

        if (!$this->awsClient instanceof CloudFormationClient) {
            throw new \InvalidArgumentException('missing client injected in paginated result');
        }
        if (!$this->input instanceof DescribeStacksInput) {
            throw new \InvalidArgumentException('missing last request injected in paginated result');
        }
        $input = clone $this->input;
        $page = $this;
        while (true) {
            if ($page->getNextToken()) {
                $input->setNextToken($page->getNextToken());

                $nextPage = $this->awsClient->DescribeStacks($input);
                $this->prefetch[spl_object_hash($nextPage)] = $nextPage;
            } else {
                $nextPage = null;
            }

            yield from $page->getStacks(true);

            if (null === $nextPage) {
                break;
            }

            unset($this->prefetch[spl_object_hash($nextPage)]);
            $page = $nextPage;
        }
    }

    protected function populateResult(ResponseInterface $response, HttpClientInterface $httpClient): void
    {
        $data = new \SimpleXMLElement($response->getContent(false));
        $data = $data->DescribeStacksResult;

        $this->Stacks = (function (\SimpleXMLElement $xml): array {
            $items = [];
            foreach ($xml as $item) {
                $items[] = new Stack([
                    'StackId' => ($v = $item->StackId) ? (string) $v : null,
                    'StackName' => (string) $item->StackName,
                    'ChangeSetId' => ($v = $item->ChangeSetId) ? (string) $v : null,
                    'Description' => ($v = $item->Description) ? (string) $v : null,
                    'Parameters' => (function (\SimpleXMLElement $xml): array {
                        $items = [];
                        foreach ($xml as $item) {
                            $items[] = new Parameter([
                                'ParameterKey' => ($v = $item->ParameterKey) ? (string) $v : null,
                                'ParameterValue' => ($v = $item->ParameterValue) ? (string) $v : null,
                                'UsePreviousValue' => ($v = $item->UsePreviousValue) ? 'true' === (string) $v : null,
                                'ResolvedValue' => ($v = $item->ResolvedValue) ? (string) $v : null,
                            ]);
                        }

                        return $items;
                    })($item->Parameters),
                    'CreationTime' => new \DateTimeImmutable((string) $item->CreationTime),
                    'DeletionTime' => ($v = $item->DeletionTime) ? new \DateTimeImmutable((string) $v) : null,
                    'LastUpdatedTime' => ($v = $item->LastUpdatedTime) ? new \DateTimeImmutable((string) $v) : null,
                    'RollbackConfiguration' => new RollbackConfiguration([
                        'RollbackTriggers' => (function (\SimpleXMLElement $xml): array {
                            $items = [];
                            foreach ($xml as $item) {
                                $items[] = new RollbackTrigger([
                                    'Arn' => (string) $item->Arn,
                                    'Type' => (string) $item->Type,
                                ]);
                            }

                            return $items;
                        })($item->RollbackConfiguration->RollbackTriggers),
                        'MonitoringTimeInMinutes' => ($v = $item->RollbackConfiguration->MonitoringTimeInMinutes) ? (int) (string) $v : null,
                    ]),
                    'StackStatus' => (string) $item->StackStatus,
                    'StackStatusReason' => ($v = $item->StackStatusReason) ? (string) $v : null,
                    'DisableRollback' => ($v = $item->DisableRollback) ? 'true' === (string) $v : null,
                    'NotificationARNs' => (function (\SimpleXMLElement $xml): array {
                        $items = [];
                        foreach ($xml as $item) {
                            $a = ($v = $item) ? (string) $v : null;
                            if (null !== $a) {
                                $items[] = $a;
                            }
                        }

                        return $items;
                    })($item->NotificationARNs),
                    'TimeoutInMinutes' => ($v = $item->TimeoutInMinutes) ? (int) (string) $v : null,
                    'Capabilities' => (function (\SimpleXMLElement $xml): array {
                        $items = [];
                        foreach ($xml as $item) {
                            $a = ($v = $item) ? (string) $v : null;
                            if (null !== $a) {
                                $items[] = $a;
                            }
                        }

                        return $items;
                    })($item->Capabilities),
                    'Outputs' => (function (\SimpleXMLElement $xml): array {
                        $items = [];
                        foreach ($xml as $item) {
                            $items[] = new Output([
                                'OutputKey' => ($v = $item->OutputKey) ? (string) $v : null,
                                'OutputValue' => ($v = $item->OutputValue) ? (string) $v : null,
                                'Description' => ($v = $item->Description) ? (string) $v : null,
                                'ExportName' => ($v = $item->ExportName) ? (string) $v : null,
                            ]);
                        }

                        return $items;
                    })($item->Outputs),
                    'RoleARN' => ($v = $item->RoleARN) ? (string) $v : null,
                    'Tags' => (function (\SimpleXMLElement $xml): array {
                        $items = [];
                        foreach ($xml as $item) {
                            $items[] = new Tag([
                                'Key' => (string) $item->Key,
                                'Value' => (string) $item->Value,
                            ]);
                        }

                        return $items;
                    })($item->Tags),
                    'EnableTerminationProtection' => ($v = $item->EnableTerminationProtection) ? 'true' === (string) $v : null,
                    'ParentId' => ($v = $item->ParentId) ? (string) $v : null,
                    'RootId' => ($v = $item->RootId) ? (string) $v : null,
                    'DriftInformation' => new StackDriftInformation([
                        'StackDriftStatus' => (string) $item->DriftInformation->StackDriftStatus,
                        'LastCheckTimestamp' => ($v = $item->DriftInformation->LastCheckTimestamp) ? new \DateTimeImmutable((string) $v) : null,
                    ]),
                ]);
            }

            return $items;
        })($data->Stacks);
        $this->NextToken = ($v = $data->NextToken) ? (string) $v : null;
    }
}