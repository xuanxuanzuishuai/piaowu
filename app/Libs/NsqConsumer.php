<?php
/**
 * @copyright Copyright (C) 2018 Xiaoyezi.com
 * Created by PhpStorm.
 * User: duhaiqiang
 * Date: 2018/11/11
 * Time: 10:41 AM
 */

namespace App\Libs;


use OkStuff\PhpNsq\Command\Base;
use OkStuff\PhpNsq\Message\Message;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NsqConsumer extends Base
{
    const COMMAND_NAME = 'phpnsq:sub';

    public function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->addArgument("topic", InputArgument::REQUIRED, "The topic you want to subscribe")
            ->addArgument("channel", InputArgument::REQUIRED, "The channel you want to subscribe")
            ->setDescription('subscribe new notification.')
            ->setHelp("This command allows you to subscribe notifications...");
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $phpnsq = self::$phpnsq;
        $phpnsq->setTopic($input->getArgument("topic"))
            ->setChannel($input->getArgument("channel"))
            ->subscribe($this, function (Message $message) use ($phpnsq, $output){
                $phpnsq->getLogger()->info("READ", $message);
            });

        $this->addPeriodicTimer(5, function () use ($output){
            $memory = memory_get_usage() / 1024;
            $formatted = number_format($memory, 3) . 'K';
            $output->writeln("############## Current memory usage: {$formatted} #################");
        });

        $this->runLoop();
    }
}