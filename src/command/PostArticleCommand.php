<?php
namespace clomery\command;

use clomery\markdown\LinkParse;
use clomery\remote\RemoteClass;
use suda\core\storage\FileStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PostArticleCommand extends Command
{
    protected static $defaultName = 'post:article';

    protected function configure()
    {
        $this
        ->setDescription('post the markdown archive')
        ->addArgument('path', InputArgument::REQUIRED, 'article path')
        ->addOption('url', 'u', InputOption::VALUE_OPTIONAL, 'the server post api url')
        ->addOption('token', 't', InputOption::VALUE_OPTIONAL, 'the server post api token')
        ->addOption('database', 'db', InputOption::VALUE_OPTIONAL, 'the path to save scan database', './clomery-data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $s = new FileStorage;
        $name = $input->getArgument('path');
        $outputPath = $input->getOption('database');
        $outputPath = $s->path($outputPath);

        $url = $input->getOption('url');
        $token = $input->getOption('token');
        $remoteClass = new RemoteClass($url, $outputPath.'/session', [
            'Clomery-Token' => $token,
        ]);

        // $data = $s->get($outputPath.'/posts.json');
        // $dataPost = $s->get($outputPath.'/posts/'.$name.'.json');
        // if (strlen($data) <= 0 || strlen($dataPost) <= 0) {
        //     return false;
        // }
        // $data = json_decode($data, true);
        // $dataPost = json_decode($dataPost, true);
        // if (!array_key_exists($name.'.md', $data)) {
        //     return false;
        // }
        $return = $remoteClass->_call('save',[
            'title' => 'Remote Class Create',
            'excerpt' => 'Remote Class Article Excerpt',
            'content' => 'Remote Class Article Content',
            'category' => '默认分类',
            'tags' => ['测试创建', '远程创建'],
            'status' => 2,
        ]);
        var_dump($return);
    }
}
