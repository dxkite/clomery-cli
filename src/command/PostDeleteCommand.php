<?php
namespace clomery\command;

use CURLFile;
use clomery\markdown\LinkParse;
use dxkite\support\remote\Config;
use suda\core\storage\FileStorage;
use dxkite\support\remote\RemoteClass;
use suda\framework\filesystem\FileSystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostDeleteCommand extends Command
{
    protected static $defaultName = 'post:delete';

    protected function configure()
    {
        $this
        ->setDescription('delete the post')
        ->addArgument('id', InputArgument::REQUIRED, 'article id')
        ->addOption('url', 'u', InputOption::VALUE_OPTIONAL, 'the server post api url')
        ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'the server post api token')
        ->addOption('debug', 'd', InputOption::VALUE_NONE, 'enable debug proxy')
        ->addOption('database', 'db', InputOption::VALUE_OPTIONAL, 'the path to save scan database', './clomery-data');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws \dxkite\support\remote\RemoteException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);


        $articleId = $input->getArgument('id');
      

        $url = $input->getOption('url');
        $token = $input->getOption('token');
        $debug = $input->getOption('debug');
        $outputPath = $input->getOption('database');
        FileSystem::make($outputPath);

        $config = new Config;
        $config->setCookiePath($outputPath.'/session');

        if ($debug) {
            $config->setEnableProxy(true);
            $config->setProxyHost('127.0.0.1');
            $config->setProxyPort(8888);
        }
      
        $remoteClass = new RemoteClass($url, $config, [
            'Clomery-Token' => $token,
        ]);

       
        
        $returned = $remoteClass->_call('delete', [
            'article' => $articleId,
        ]);

        $io->text('delete article returned: <info>'.$returned.'</>');
        $io->newLine(2);
    }
}
