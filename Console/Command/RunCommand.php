<?php
/*
  * Copyright © Ghost Unicorns snc. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace GhostUnicorns\CrtCommand\Console\Command;

use Exception;
use GhostUnicorns\CrtActivity\Model\HasRunningActivity;
use GhostUnicorns\CrtBase\Api\CrtConfigInterface;
use GhostUnicorns\CrtBase\Api\CrtListInterface;
use GhostUnicorns\CrtBase\Logger\Handler\Console;
use GhostUnicorns\CrtBase\Model\Run\RunAsync;
use GhostUnicorns\CrtBase\Model\Run\RunSync;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends Command
{
    /** @var string */
    const EXTRA = 'extra';

    /** @var string */
    const TYPE = 'type';

    /** @var string */
    const SYNCHRONOUS_MODE = 'synchronous';

    /** @var string */
    const FORCE = 'force';

    /**
     * @var CrtConfigInterface
     */
    private $config;

    /**
     * @var Console
     */
    private $consoleLogger;

    /**
     * @var CrtListInterface
     */
    private $crtList;

    /**
     * @var HasRunningActivity
     */
    private $hasRunningActivity;

    /**
     * @var RunAsync
     */
    private $runAsync;

    /**
     * @var RunSync
     */
    private $runSync;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param CrtConfigInterface $config
     * @param Console $consoleLogger
     * @param CrtListInterface $crtList
     * @param HasRunningActivity $hasRunningActivity
     * @param RunAsync $runAsync
     * @param RunSync $runSync
     * @param SerializerInterface $serializer
     * @param null $name
     */
    public function __construct(
        CrtConfigInterface $config,
        Console $consoleLogger,
        CrtListInterface $crtList,
        HasRunningActivity $hasRunningActivity,
        RunAsync $runAsync,
        RunSync $runSync,
        SerializerInterface $serializer,
        $name = null
    ) {
        parent::__construct($name);
        $this->config = $config;
        $this->consoleLogger = $consoleLogger;
        $this->crtList = $crtList;
        $this->hasRunningActivity = $hasRunningActivity;
        $this->runAsync = $runAsync;
        $this->runSync = $runSync;
        $this->serializer = $serializer;
    }

    /**
     * @return string
     */
    public function getHelp()
    {
        $text = [];
        $text[] = __('Available CollectorList types: ')->getText();
        $allDownlaoderList = $this->crtList->getAllCollectorList();
        foreach ($allDownlaoderList as $name => $downlaoderList) {
            $text[] = $name;
            $text[] = ', ';
        }
        $text[] = __('Available RefinerList types: ')->getText();
        $allRefinerList = $this->crtList->getAllRefinerList();
        foreach ($allRefinerList as $name => $refinerList) {
            $text[] = $name;
            $text[] = ', ';
        }
        $text[] = __('Available TransferorList types: ')->getText();
        $allUplaoderList = $this->crtList->getAllTransferorList();
        foreach ($allUplaoderList as $name => $uplaoderList) {
            $text[] = $name;
            $text[] = ', ';
        }
        array_pop($text);
        return implode('', $text);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setDescription('Crt: Collect + Refine + Transfer for a specific Type');
        $this->addArgument(
            self::TYPE,
            InputArgument::REQUIRED,
            'Type name'
        );

        $this->addArgument(
            self::EXTRA,
            InputArgument::OPTIONAL,
            'Extra data',
            ''
        );

        $this->addOption(
            self::SYNCHRONOUS_MODE,
            's',
            InputOption::VALUE_NONE,
            'Run in Sync mode and print the return',
            null
        );

        $this->addOption(
            self::FORCE,
            'f',
            InputOption::VALUE_NONE,
            'Force if already there is a running activity',
            null
        );
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->config->isEnabled()) {
            $output->writeln(
                'Please enable Crt to continue:'.' Stores -> Configurations -> CRT -> Base -> General -> Enalbe Crt'
            );
            return;
        }
        $this->consoleLogger->setConsoleOutput($output);
        $type = $input->getArgument(self::TYPE);
        $extra = $input->getArgument(self::EXTRA);

        $force = (bool)$input->getOption(self::FORCE);
        $sync = (bool)$input->getOption(self::SYNCHRONOUS_MODE);

        if (!$sync && !$force && $this->hasRunningActivity->execute($type)) {
            throw new NoSuchEntityException(
                __(
                    'There is an activity with type:%1 that is already running',
                    $type
                )
            );
        }

        if ($sync) {
            $result = $this->runSync->execute($type, $extra);
            $output->writeln('Result:');
            $output->writeln($this->serializer->serialize($result));
        } else {
            $this->runAsync->execute($type, $extra);
        }
    }
}
