<?php

namespace clomery\command;

use suda\core\storage\FileStorage;
use suda\framework\filesystem\FileSystem;
use suda\framework\loader\PathTrait;
use dxkite\support\remote\RemoteException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostAllCommand extends Command
{
    protected static $defaultName = 'post:all';

    protected function configure()
    {
        $this
            ->setDescription('post the all markdown posts')
            ->setHelp('you can use this command to scan markdown posts in some path')
            ->addUsage('post:all /path/to/markdowns')
            ->addOption('url', 'u', InputOption::VALUE_OPTIONAL, 'the server post api url')
            ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'the server post api token')
            ->addArgument('path', InputArgument::REQUIRED, 'the path to scan')
            ->addArgument('start', InputArgument::OPTIONAL, 'start', 0)
            ->addOption('database', 'db', InputArgument::OPTIONAL, 'the path to save scan database', './clomery-data')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'force update post data');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $scanPath = $input->getArgument('path');
        $outputPath = $input->getOption('database');
        $force = $input->getOption('force');
        $url = $input->getOption('url');
        $token = $input->getOption('token');
        $start = $input->getArgument('start');

        $scanPath = PathTrait::toAbsolutePath($scanPath);
        FileSystem::make($outputPath);
        foreach (FileSystem::readFiles($scanPath, false, '/\.md$/', false) as $index => $sortPath) {
            if ($index < $start) {
                continue;
            }
            $output->write("index:" . $index);
            $path = PathTrait::toAbsolutePath($scanPath . '/' . $sortPath);
            try {
                $this->getApplication()
                    ->find('post:article')
                    ->run(new ArrayInput([
                        'path' => $path,
                        '--url' => $url,
                        '--token' => $token,
                        '--database' => $outputPath,
                        '--force' => $force,
                    ]), $output);
            } catch (RemoteException $e) {
                $io->text('upload error:<info>' . $path . '</>');
                $io->error($e->getMessage());
                continue;
            }
        }
    }
}
