<?php
namespace clomery\command;

use CURLFile;
use dxkite\support\remote\Config;
use dxkite\support\remote\RemoteClass;
use suda\framework\filesystem\FileSystem;
use suda\framework\loader\PathTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
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
        ->addOption('debug', 'd', InputOption::VALUE_NONE, 'enable debug proxy')
        ->addOption('database', 'db', InputOption::VALUE_OPTIONAL, 'the path to save scan database', './clomery-data');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $path = $input->getArgument('path');
        $path = PathTrait::toAbsolutePath($path);

        $name = \pathinfo($path, PATHINFO_FILENAME);
        $outputPath = $input->getOption('database');
        $outputPath = PathTrait::toAbsolutePath($outputPath);
        FileSystem::make($outputPath);

        $force = $input->getOption('force');
        $url = $input->getOption('url');
        $token = $input->getOption('token');
        $debug = $input->getOption('debug');


        $config = new Config;
        $config->setCookiePath($outputPath.'/session');

        if ($debug) {
            $config->setEnableProxy(true);
            $config->setProxyHost('127.0.0.1');
            $config->setProxyPort(8888);
        }
      
        $remoteClass = new RemoteClass($url, $config, [
            'x-clomery-token' => $token,
        ]);

        $io->text('upload to <info>'.$url.'</>');
        $io->newLine(2);

        $hash = \md5_file($path);

        if ($this->checkMd5($outputPath.'/posts/'.$name.'/version', $hash) === false || $force) {
            $this->getApplication()
           ->find('post:generate')
           ->run(new ArrayInput([ 'path' => $path,  '--database' => $outputPath, '--force'=> $force]), $output);
            $this->getApplication()
           ->find('post:analysis')
           ->run(new ArrayInput([ 'name' => $name,  '--database' => $outputPath,]), $output);
        } else {
            $io->text('uploaded article hash : <info>'.$hash.'</>');
            return 0;
        }
        
        // 读取文件信息
        $articlePath = $outputPath.'/posts/'.$name.'.json';
        $articleData = \json_decode(FileSystem::get($articlePath), true);
        $content = $articleData['content'];
        
        if (isset($articleData['meta']['categories'])) {
            $category = $articleData['meta']['categories'];
        } else {
            $category = null;
        }
        
        
        $articleUpoloadData = $remoteClass->_call('save', [
            'title' => $articleData['meta']['title'] ?? 'untitiled',
            'slug' => $name,
            'description' => $articleData['description'] ?? '',
            'content' => $content,
            'category' => $category ,
            'create' => \date_create_from_format('Y-m-d H:i:s', $articleData['meta']['date'])->getTimestamp(),
            'tag' => $articleData['meta']['tags'] ?? null,
            'status' => 0,
        ]);

        $articleId = $articleUpoloadData['id'];

        $io->text('uploaded article id: <info>'.$articleId.'</>');
        $io->newLine(2);

        $replace = [];
        $imageJson =  $outputPath.'/posts/'.$name.'/image.json';
        $attachmentJson =  $outputPath.'/posts/'.$name.'/attachment.json';
        $nameJson =  $outputPath.'/posts/'.$name.'/name.json';
        $names = [];
        if (FileSystem::exist($nameJson)) {
            $names = \json_decode(FileSystem::get($nameJson), true);
        }

        if (FileSystem::exist($imageJson)) {
            $io->text('upload images');
            $articleImageData = \json_decode(FileSystem::get($imageJson), true);
            $progressBar = new ProgressBar($output, count($articleImageData));
            foreach ($articleImageData as $path) {
                $filePath = $outputPath.'/posts/'.$name.'/resource/' .$path;
                if (FileSystem::exist($filePath)) {
                    $uploadedInfo = $remoteClass->_call('saveFile', [
                        'article' => $articleId,
                        'name' => $names[$path] ?? $path,
                        'file' => new CURLFile($filePath),
                    ]);
                    if (!empty($uploadedInfo['result'])) {
                        $replace[$path] = $uploadedInfo['result'];
                        $progressBar->advance();
                    }
                }
            }
            $progressBar->finish();
            $io->newLine(2);
        }
        
        if (FileSystem::exist($attachmentJson)) {
            $io->text('upload attachment');
            $articleAttachmentData = \json_decode(FileSystem::get($attachmentJson), true);
            $progressBar = new ProgressBar($output, count($articleAttachmentData));
            foreach ($articleAttachmentData as $path) {
                $filePath = PathTrait::toAbsolutePath($outputPath.'/posts/'.$name.'/resource/' .$path);
                if (FileSystem::exist($filePath)) {
                    $uploadedInfo = $remoteClass->_call('saveFile', [
                        'article' => $articleId,
                        'name' => $names[$path] ?? $path,
                        'file' => new CURLFile($filePath),
                    ]);
                    if (!empty($uploadedInfo['result'])) {
                        $replace[$path] = $uploadedInfo['result'];
                    }
                }
                
                $progressBar->advance();
            }
            $progressBar->finish();
            $io->newLine(2);
        }
        
        foreach ($replace as $path => $uploadedInfo) {
            $content = \str_replace(']('.$path.')', ']('.$uploadedInfo.')', $content);
        }

        $articleId = $remoteClass->_call('save', [
            'title' => $articleData['meta']['title'] ?? 'untitiled',
            'slug' => $name,
            'description' => $articleData['description'] ?? '',
            'content' => $content,
            'category' => $category ,
            'create' => \date_create_from_format('Y-m-d H:i:s', $articleData['meta']['date'])->getTimestamp(),
            'tags' => $articleData['meta']['tags']?? null,
            'status' => 1,
        ]);
        $io->text('uploaded article resource : <info>'.$hash.'</>');
        \file_put_contents($outputPath.'/posts/'.$name.'/version', $hash);
        return true;
    }

    protected function checkMd5(string $path, string $md5)
    {
        if (\file_exists($path)) {
            return \file_get_contents($path) == $md5;
        }
        return false;
    }
}
