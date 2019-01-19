<?php
namespace clomery\command;

use suda\core\storage\FileStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostScanCommand extends Command
{
    protected static $defaultName = 'post:scan';

    protected function configure()
    {
        $this
        ->setDescription('scan the markdown posts')
        ->setHelp('you can use this command to scan markdown posts in some path')
        ->addUsage('post:scan /path/to/markdowns')
        ->addArgument('path', InputArgument::REQUIRED, 'the path to scan')
        ->addOption('database', 'db', InputArgument::OPTIONAL, 'the path to save scan database', './clomery-data')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storage = new FileStorage;
        $io = new SymfonyStyle($input, $output);
        $scanPath = $input->getArgument('path');
        $outputPath = $input->getOption('database');

        $scanPath = $storage->abspath($scanPath);
        $outputPath = $storage->path($outputPath);
        $database = [];
        $io->title('Markdown Post Helper');
        $io->section('scan post files');
        $io->text('scan path <info>'.$scanPath.'</>');
        $databasePath = $outputPath.'/posts.json';
        if ($storage->exist($databasePath)) {
            $io->text('read database from <info>'.$databasePath.'</>');
            $database = \json_decode($storage->get($databasePath), true);
        } else {
            $storage->put($databasePath, '{}');
            $io->text('create database to <info>'.$databasePath.'</>');
        }
        $articleTable =[];
        foreach ($storage->readDirFiles($scanPath, false, '/\.md$/', false) as $sortPath) {
            $path = $storage->abspath($scanPath .'/'.$sortPath);
            $name = pathinfo($sortPath, PATHINFO_FILENAME);
            $database[$sortPath] = [$name,  \md5_file($path) , $sortPath, $path];
            $articleTable[] =  [$name, $sortPath];
        }
        $io->text('scan <info>'.count($database).' posts</>');
        $io->table(['name', 'path'], $articleTable);
        $storage->put($databasePath, \json_encode($database, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $io->section('generate post data');
        foreach ($database as $key => $value) {
            list($slug, $hash, $sortPath , $absPath) = $value;
            $command = $this->getApplication()->find('post:generate');
            $arguments =[
                'path' => $absPath,
                '--database' => $outputPath,
            ];
            $greetInput = new ArrayInput($arguments);
            $returnCode = $command->run($greetInput, $output);
        }
    }
}
