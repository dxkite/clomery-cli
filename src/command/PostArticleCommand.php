<?php
namespace clomery\command;

use clomery\markdown\LinkParse;
use clomery\remote\RemoteClass;
use suda\core\storage\FileStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
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
        ->addOption('force', 'f', InputOption::VALUE_NONE, 'force update post data')
        ->addOption('database', 'db', InputOption::VALUE_OPTIONAL, 'the path to save scan database', './clomery-data');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $s = new FileStorage;
        $path = $input->getArgument('path');
        $path = $s->abspath($path);
        $name = \pathinfo($path, PATHINFO_FILENAME);
        $outputPath = $input->getOption('database');
        $outputPath = $s->path($outputPath);
        $force = $input->getOption('force');

        $url = $input->getOption('url');
        $token = $input->getOption('token');
        $remoteClass = new RemoteClass($url, $outputPath.'/session', [
            'Clomery-Token' => $token,
        ]);
        $io->text('upload to <info>'.$url.'</>');
        if (!$s->exist($outputPath.'/posts/'.$name.'.json') || $force) {
           $this->getApplication()
           ->find('post:generate')
           ->run(new ArrayInput([ 'path' => $path,  '--database' => $outputPath, '--force'=> $force]), $output);
           $this->getApplication()
           ->find('post:analysis')
           ->run(new ArrayInput([ 'name' => $name,  '--database' => $outputPath,]), $output);
        }
        
        // 读取文件信息
        $articlePath = $outputPath.'/posts/'.$name.'.json';
        $articleData = \json_decode($s->get($articlePath), true);
      
        $category = $articleData['meta']['categories'][count($articleData['meta']['categories'])-1] ?? '';
        $return = $remoteClass->_call('save',[
            'title' => $articleData['meta']['title'] ?? 'untitiled',
            'slug' => $name,
            'excerpt' => $articleData['excerpt'] ?? '',
            'content' => $articleData['content'] ?? '',
            'category' => $category ,
            'create' => \date_create_from_format('Y-m-d H:i:s', $articleData['meta']['date'])->getTimestamp(),
            'tags' => $articleData['meta']['tags'],
            'status' => 2,
        ]);
        $io->text('uploaded article id: <info>'.$return.'</>');
    }
}
